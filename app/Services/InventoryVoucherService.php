<?php

namespace App\Services;

use App\Enums\VoucherBaseType;
use App\Models\CompanySetting;
use App\Models\Godown;
use App\Models\StockItem;
use App\Models\Voucher;
use App\Models\VoucherType;
use App\Support\Audit;
use App\Support\Money;
use App\Support\PeriodLock;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Inventory-only vouchers: Stock Journal (transfers, consumption/production) and Physical Stock (counts).
 */
class InventoryVoucherService
{
    public function __construct(private VoucherService $vouchers, private StockService $stock) {}

    /**
     * $data: voucher_type_id, date, narration?,
     *        source: list of [stock_item_id, godown_id, quantity]              (consumed / transferred out)
     *        destination: list of [stock_item_id, godown_id, quantity, rate?]  (produced / transferred in)
     */
    public function saveStockJournal(array $data, ?Voucher $voucher = null, ?int $userId = null): Voucher
    {
        $type = $this->type($data, VoucherBaseType::StockJournal);
        $this->validateDate($data['date'] ?? null);

        $source = $this->lines($data['source'] ?? []);
        $destination = $this->lines($data['destination'] ?? []);

        if (! $source && ! $destination) {
            throw ValidationException::withMessages(['lines' => 'Enter at least one item.']);
        }

        // An item on both sides is a transfer between godowns: it must not change the average cost.
        $transferred = array_intersect(array_column($source, 'stock_item_id'), array_column($destination, 'stock_item_id'));

        return DB::transaction(function () use ($type, $data, $voucher, $userId, $source, $destination, $transferred) {
            $voucher = $this->header($type, $data, $voucher, $userId);
            $date = Carbon::parse($data['date']);

            foreach ($source as $line) {
                $voucher->stockMovements()->create([
                    'stock_item_id' => $line['stock_item_id'],
                    'godown_id' => $line['godown_id'],
                    'date' => $date,
                    'quantity' => Money::toDecimal(-$line['qty']),
                    'is_transfer' => in_array($line['stock_item_id'], $transferred, true),
                ]);
            }

            foreach ($destination as $line) {
                $isTransfer = in_array($line['stock_item_id'], $transferred, true);
                $rate = $line['rate'] ?? $this->stock->positions($date)->get($line['stock_item_id'])?->rate ?? 0;
                $value = Money::multiply($rate, Money::toDecimal($line['qty']));

                $voucher->stockMovements()->create([
                    'stock_item_id' => $line['stock_item_id'],
                    'godown_id' => $line['godown_id'],
                    'date' => $date,
                    'quantity' => Money::toDecimal($line['qty']),
                    'rate' => Money::toDecimal($rate),
                    'value' => Money::toDecimal($value),
                    'affects_cost' => ! $isTransfer,
                    'is_transfer' => $isTransfer,
                ]);
            }

            return $voucher->load('stockMovements');
        });
    }

    /**
     * Physical stock count: records the difference between counted and book quantity.
     *
     * $data: voucher_type_id, date, narration?, lines: list of [stock_item_id, godown_id, quantity (counted)]
     */
    public function savePhysicalStock(array $data, ?Voucher $voucher = null, ?int $userId = null): Voucher
    {
        $type = $this->type($data, VoucherBaseType::PhysicalStock);
        $this->validateDate($data['date'] ?? null);
        $lines = $this->lines($data['lines'] ?? [], allowZero: true);

        if (! $lines) {
            throw ValidationException::withMessages(['lines' => 'Enter at least one counted item.']);
        }

        return DB::transaction(function () use ($type, $data, $voucher, $userId, $lines) {
            $voucher = $this->header($type, $data, $voucher, $userId);
            $date = Carbon::parse($data['date']);

            foreach ($lines as $line) {
                $item = StockItem::query()->findOrFail($line['stock_item_id']);
                $book = $this->stock->available($item, $date, $line['godown_id'], $voucher->id);

                // Always keep a row so the counted figure can be shown again when altering the voucher.
                $voucher->stockMovements()->create([
                    'stock_item_id' => $item->id,
                    'godown_id' => $line['godown_id'],
                    'date' => $date,
                    'quantity' => Money::toDecimal($line['qty'] - $book),
                ]);
            }

            return $voucher->load('stockMovements');
        });
    }

    private function header(VoucherType $type, array $data, ?Voucher $voucher, ?int $userId): Voucher
    {
        PeriodLock::assertOpen($data['date'], $voucher?->date);
        $before = $voucher ? Audit::voucherSnapshot($voucher->fresh()) : null;

        DB::afterCommit(function () use (&$voucher, $before) {
            $fresh = $voucher->fresh();
            Audit::log($before ? 'altered' : 'created', 'Voucher', $fresh->id,
                "{$fresh->type->name} {$fresh->number} ".($before ? 'altered' : 'created'),
                $before, Audit::voucherSnapshot($fresh));
        });

        $attributes = [
            'voucher_type_id' => $type->id,
            'date' => $data['date'],
            'narration' => $data['narration'] ?? null,
            'total' => 0,
            'updated_by' => $userId,
        ];

        if ($voucher) {
            $voucher->update($attributes);
            $voucher->stockMovements()->delete();

            return $voucher;
        }

        return $voucher = Voucher::query()->create($attributes + [
            'number' => $this->vouchers->nextNumber($type),
            'created_by' => $userId,
        ]);
    }

    /** @return list<array{stock_item_id:int, godown_id:int, qty:int, rate:?int}> */
    private function lines(array $rows, bool $allowZero = false): array
    {
        $lines = [];
        $mainGodown = null;

        foreach ($rows as $row) {
            if (empty($row['stock_item_id']) || ($row['quantity'] ?? '') === '') {
                continue;
            }

            try {
                $qty = Money::toBaisa($row['quantity']);
                $rate = ($row['rate'] ?? '') === '' ? null : Money::toBaisa($row['rate']);
            } catch (\InvalidArgumentException) {
                throw ValidationException::withMessages(['lines' => 'Check the quantities and rates.']);
            }

            if ($qty < 0 || (! $allowZero && $qty === 0)) {
                throw ValidationException::withMessages(['lines' => 'Quantities must be greater than zero.']);
            }

            $lines[] = [
                'stock_item_id' => (int) $row['stock_item_id'],
                'godown_id' => (int) ($row['godown_id'] ?? 0) ?: ($mainGodown ??= Godown::main()->id),
                'qty' => $qty,
                'rate' => $rate,
            ];
        }

        return $lines;
    }

    private function type(array $data, VoucherBaseType $expected): VoucherType
    {
        $type = VoucherType::query()->findOrFail($data['voucher_type_id']);
        abort_unless($type->base_type === $expected, 422);

        return $type;
    }

    private function validateDate(?string $date): void
    {
        if (! $date) {
            throw ValidationException::withMessages(['date' => 'Date is required.']);
        }

        $company = CompanySetting::current();
        if ($company && Carbon::parse($date)->lt($company->books_begin_from)) {
            throw ValidationException::withMessages(['date' => 'Date is before books begin ('.$company->books_begin_from->format('d-M-Y').').']);
        }
    }
}
