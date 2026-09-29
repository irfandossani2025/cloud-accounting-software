<?php

namespace App\Services;

use App\Models\StockItem;
use App\Models\StockOpening;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Inventory quantities and valuation. Quantities are handled in thousandths (qty × 1000) and values
 * in baisa, both as integers.
 *
 * Valuation is weighted average cost (Tally's default): all cost-bearing inwards up to the date
 * (opening stock, purchases net of purchase returns, stock journal production) divided by their quantity.
 */
class StockService
{
    /**
     * Per-item position as of the end of $asOf (inclusive), optionally per godown.
     *
     * @return Collection<int, object{item: StockItem, qty: int, costQty: int, costValue: int, rate: int, value: int}>
     */
    public function positions(Carbon $asOf, ?int $godownId = null): Collection
    {
        $openings = StockOpening::query()
            ->when($godownId, fn ($q) => $q->where('godown_id', $godownId))
            ->get()
            ->groupBy('stock_item_id');

        $movements = DB::table('stock_movements as m')
            ->join('vouchers as v', 'v.id', '=', 'm.voucher_id')
            ->where('v.is_cancelled', false)
            ->where('m.date', '<=', $asOf->toDateString())
            ->when($godownId, fn ($q) => $q->where('m.godown_id', $godownId))
            ->groupBy('m.stock_item_id')
            ->selectRaw('m.stock_item_id, SUM(m.quantity) AS qty')
            ->selectRaw('SUM(CASE WHEN m.affects_cost = 1 THEN m.quantity ELSE 0 END) AS cost_qty')
            ->selectRaw('SUM(CASE WHEN m.affects_cost = 1 THEN m.value ELSE 0 END) AS cost_value')
            ->get()
            ->keyBy('stock_item_id');

        // The average rate is always company-wide, even when quantities are filtered by godown.
        $rates = $godownId ? $this->positions($asOf)->map(fn ($p) => $p->rate) : null;

        return StockItem::query()->with(['unit', 'group'])->orderBy('name')->get()
            ->map(function (StockItem $item) use ($openings, $movements, $rates) {
                $opening = $openings->get($item->id, collect());
                $m = $movements->get($item->id);

                $openingQty = $opening->sum(fn ($o) => Money::toBaisa($o->quantity));
                $openingValue = $opening->sum(fn ($o) => Money::toBaisa($o->value));

                $qty = $openingQty + Money::toBaisa($m->qty ?? 0);
                $costQty = $openingQty + Money::toBaisa($m->cost_qty ?? 0);
                $costValue = $openingValue + Money::toBaisa($m->cost_value ?? 0);

                // Rate per unit in baisa: value / qty (qty is in thousandths).
                $rate = $rates?->get($item->id) ?? ($costQty > 0 ? intdiv($costValue * 1000 + intdiv($costQty, 2), $costQty) : 0);

                return (object) [
                    'item' => $item,
                    'qty' => $qty,
                    'costQty' => $costQty,
                    'costValue' => $costValue,
                    'rate' => $rate,
                    'value' => Money::multiply($rate, Money::toDecimal($qty)),
                ];
            })
            ->keyBy(fn ($p) => $p->item->id);
    }

    /** Total closing stock value at the end of $asOf. */
    public function closingValue(Carbon $asOf): int
    {
        return $this->positions($asOf)->sum('value');
    }

    /** Value of stock entered as opening on the item masters (books beginning). */
    public function openingMasterValue(): int
    {
        return StockOpening::query()->pluck('value')->sum(fn ($v) => Money::toBaisa($v));
    }

    /** Quantity (thousandths) of an item available in a godown, or overall, at a date. */
    public function available(StockItem $item, Carbon $asOf, ?int $godownId = null, ?int $excludeVoucherId = null): int
    {
        $opening = StockOpening::query()->where('stock_item_id', $item->id)
            ->when($godownId, fn ($q) => $q->where('godown_id', $godownId))
            ->pluck('quantity')->sum(fn ($q) => Money::toBaisa($q));

        $moved = DB::table('stock_movements as m')
            ->join('vouchers as v', 'v.id', '=', 'm.voucher_id')
            ->where('v.is_cancelled', false)
            ->where('m.stock_item_id', $item->id)
            ->where('m.date', '<=', $asOf->toDateString())
            ->when($godownId, fn ($q) => $q->where('m.godown_id', $godownId))
            ->when($excludeVoucherId, fn ($q) => $q->where('m.voucher_id', '!=', $excludeVoucherId))
            ->sum('m.quantity');

        return $opening + Money::toBaisa($moved);
    }

    /**
     * Stock summary for a period: opening, inwards, outwards and closing per item.
     *
     * @return Collection<int, object>
     */
    public function summary(Carbon $from, Carbon $to, ?int $godownId = null): Collection
    {
        $before = $this->positions($from->copy()->subDay(), $godownId);
        $after = $this->positions($to, $godownId);

        $flows = DB::table('stock_movements as m')
            ->join('vouchers as v', 'v.id', '=', 'm.voucher_id')
            ->where('v.is_cancelled', false)
            ->whereBetween('m.date', [$from->toDateString(), $to->toDateString()])
            ->when($godownId,
                fn ($q) => $q->where('m.godown_id', $godownId),
                fn ($q) => $q->where('m.is_transfer', false))
            ->groupBy('m.stock_item_id')
            ->selectRaw('m.stock_item_id')
            ->selectRaw('SUM(CASE WHEN m.quantity > 0 THEN m.quantity ELSE 0 END) AS in_qty')
            ->selectRaw('SUM(CASE WHEN m.quantity > 0 AND m.affects_cost = 1 THEN m.value ELSE 0 END) AS in_value')
            ->selectRaw('SUM(CASE WHEN m.quantity < 0 THEN -m.quantity ELSE 0 END) AS out_qty')
            ->get()
            ->keyBy('stock_item_id');

        return $after->map(function ($closing) use ($before, $flows) {
            $opening = $before->get($closing->item->id);
            $f = $flows->get($closing->item->id);
            $outQty = Money::toBaisa($f->out_qty ?? 0);

            return (object) [
                'item' => $closing->item,
                'openingQty' => $opening->qty,
                'openingValue' => $opening->value,
                'inQty' => Money::toBaisa($f->in_qty ?? 0),
                'inValue' => Money::toBaisa($f->in_value ?? 0),
                'outQty' => $outQty,
                // Outwards are valued at the closing average cost.
                'outValue' => Money::multiply($closing->rate, Money::toDecimal($outQty)),
                'closingQty' => $closing->qty,
                'closingRate' => $closing->rate,
                'closingValue' => $closing->value,
            ];
        })->filter(fn ($row) => $row->openingQty || $row->inQty || $row->outQty || $row->closingQty)->values();
    }

    /** Format a quantity held in thousandths, trimming trailing zeros. */
    public static function qty(int $milli): string
    {
        $text = Money::toDecimal($milli);

        return rtrim(rtrim($text, '0'), '.');
    }
}
