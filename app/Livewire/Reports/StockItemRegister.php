<?php

namespace App\Livewire\Reports;

use App\Livewire\Reports\Concerns\HasPeriod;
use App\Models\StockItem;
use App\Services\StockService;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class StockItemRegister extends Component
{
    use HasPeriod;

    public StockItem $item;

    public function render(StockService $stock)
    {
        $opening = $stock->available($this->item, $this->fromDate()->copy()->subDay());

        $lines = DB::table('stock_movements as m')
            ->join('vouchers as v', 'v.id', '=', 'm.voucher_id')
            ->join('voucher_types as t', 't.id', '=', 'v.voucher_type_id')
            ->join('godowns as g', 'g.id', '=', 'm.godown_id')
            ->leftJoin('ledgers as p', 'p.id', '=', 'v.party_ledger_id')
            ->where('m.stock_item_id', $this->item->id)
            ->where('v.is_cancelled', false)
            ->whereBetween('m.date', [$this->fromDate()->toDateString(), $this->toDate()->toDateString()])
            ->orderBy('m.date')->orderBy('m.id')
            ->get(['v.id as voucher_id', 'v.is_invoice', 't.base_type', 'm.date', 'v.number', 't.name as type', 'g.name as godown', 'p.name as party', 'm.quantity', 'm.rate']);

        $balance = $opening;
        $lines = $lines->map(function ($line) use (&$balance) {
            $qty = Money::toBaisa($line->quantity);
            $balance += $qty;
            $line->qty = $qty;
            $line->balance = $balance;

            return $line;
        });

        $this->item->loadMissing('unit');

        return view('livewire.reports.stock-item-register', [
            'opening' => $opening,
            'lines' => $lines,
            'closing' => $balance,
            'unit' => $this->item->unit->symbol,
        ])->title('Stock item: '.$this->item->name);
    }
}
