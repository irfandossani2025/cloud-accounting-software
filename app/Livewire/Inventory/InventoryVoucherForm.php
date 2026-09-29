<?php

namespace App\Livewire\Inventory;

use App\Enums\VoucherBaseType;
use App\Models\Godown;
use App\Models\StockItem;
use App\Models\Voucher;
use App\Models\VoucherType;
use App\Services\InventoryVoucherService;
use App\Services\StockService;
use App\Services\VoucherService;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Stock Journal (source → destination) and Physical Stock (counted quantities).
 */
class InventoryVoucherForm extends Component
{
    #[Locked]
    public ?int $voucherId = null;

    #[Locked]
    public int $voucher_type_id;

    public string $date = '';

    public string $narration = '';

    /** Stock Journal consumption / transfer-out, or Physical Stock counted lines. */
    public array $source = [];

    /** Stock Journal production / transfer-in. */
    public array $destination = [];

    public function mount(?VoucherType $type = null, ?Voucher $voucher = null): void
    {
        if ($voucher?->exists) {
            abort_if($voucher->is_cancelled, 404);
            $voucher->load('type', 'stockMovements');
            $this->voucherId = $voucher->id;
            $this->voucher_type_id = $voucher->voucher_type_id;
            $this->date = $voucher->date->toDateString();
            $this->narration = (string) $voucher->narration;

            $line = fn ($m, $qty) => [
                'stock_item_id' => $m->stock_item_id,
                'godown_id' => $m->godown_id,
                'quantity' => StockService::qty($qty),
                'rate' => Money::toBaisa($m->rate) ? $m->rate : '',
            ];

            if ($voucher->type->base_type === VoucherBaseType::PhysicalStock) {
                $stock = app(StockService::class);
                $this->source = $voucher->stockMovements->map(fn ($m) => $line($m,
                    $stock->available($m->item, $voucher->date, $m->godown_id, $voucher->id) + Money::toBaisa($m->quantity)
                ))->all();
            } else {
                $this->source = $voucher->stockMovements->filter(fn ($m) => Money::toBaisa($m->quantity) < 0)
                    ->map(fn ($m) => $line($m, -Money::toBaisa($m->quantity)))->values()->all();
                $this->destination = $voucher->stockMovements->filter(fn ($m) => Money::toBaisa($m->quantity) > 0)
                    ->map(fn ($m) => $line($m, Money::toBaisa($m->quantity)))->values()->all();
            }
        } else {
            abort_unless($type->base_type->isInventoryOnly(), 404);
            $this->voucher_type_id = $type->id;
            $this->date = session('voucher_date', now()->toDateString());
        }

        $this->source = $this->source ?: [$this->blankLine()];
        if ($this->isStockJournal()) {
            $this->destination = $this->destination ?: [$this->blankLine()];
        }
    }

    public function addLine(string $side): void
    {
        abort_unless(in_array($side, ['source', 'destination'], true), 422);
        $this->{$side}[] = $this->blankLine();
    }

    public function removeLine(string $side, int $index): void
    {
        abort_unless(in_array($side, ['source', 'destination'], true), 422);
        unset($this->{$side}[$index]);
        $this->{$side} = array_values($this->{$side}) ?: [$this->blankLine()];
    }

    public function save(InventoryVoucherService $service)
    {
        $this->resetErrorBag();
        $existing = $this->voucherId ? Voucher::query()->findOrFail($this->voucherId) : null;

        try {
            $voucher = $this->isStockJournal()
                ? $service->saveStockJournal([
                    'voucher_type_id' => $this->voucher_type_id,
                    'date' => $this->date,
                    'narration' => $this->narration ?: null,
                    'source' => $this->source,
                    'destination' => $this->destination,
                ], $existing, auth()->id())
                : $service->savePhysicalStock([
                    'voucher_type_id' => $this->voucher_type_id,
                    'date' => $this->date,
                    'narration' => $this->narration ?: null,
                    'lines' => $this->source,
                ], $existing, auth()->id());
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError($field, $messages[0]);
            }

            return;
        }

        session(['voucher_date' => $this->date]);
        session()->flash('status', "{$voucher->type->name} {$voucher->number} saved.");

        return $this->voucherId
            ? $this->redirectRoute('reports.day-book', ['from' => $this->date, 'to' => $this->date], navigate: true)
            : $this->redirectRoute('inventory-vouchers.create', $this->voucher_type_id, navigate: true);
    }

    public function cancelVoucher(VoucherService $service)
    {
        $voucher = Voucher::query()->findOrFail($this->voucherId);
        $service->cancel($voucher);
        session()->flash('status', "Voucher {$voucher->number} cancelled.");

        return $this->redirectRoute('reports.day-book', navigate: true);
    }

    private function isStockJournal(): bool
    {
        return VoucherType::query()->find($this->voucher_type_id)?->base_type === VoucherBaseType::StockJournal;
    }

    private function blankLine(): array
    {
        return ['stock_item_id' => null, 'godown_id' => Godown::main()->id, 'quantity' => '', 'rate' => ''];
    }

    public function render(StockService $stock)
    {
        $type = VoucherType::query()->findOrFail($this->voucher_type_id);
        $voucher = $this->voucherId ? Voucher::query()->find($this->voucherId) : null;
        $items = StockItem::query()->with('unit')->where('is_active', true)->orderBy('name')->get();
        $date = rescue(fn () => Carbon::parse($this->date), now(), false);

        // Book quantity per source line, to help with transfers and counts.
        $book = [];
        foreach ($this->source as $i => $line) {
            $item = $items->firstWhere('id', (int) $line['stock_item_id']);
            $book[$i] = $item ? StockService::qty($stock->available($item, $date, (int) $line['godown_id'] ?: null, $this->voucherId)).' '.$item->unit->symbol : '';
        }

        return view('livewire.inventory.inventory-voucher-form', [
            'type' => $type,
            'voucher' => $voucher,
            'isStockJournal' => $type->base_type === VoucherBaseType::StockJournal,
            'items' => $items,
            'godowns' => Godown::query()->orderBy('name')->get(),
            'book' => $book,
            'nextNumber' => $voucher?->number ?? ($type->prefix.$type->next_number),
        ])->title(($voucher ? 'Alter ' : '').$type->name);
    }
}
