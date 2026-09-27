<?php

namespace App\Http\Controllers;

use App\Models\ProductItem;
use App\Models\Setting;
use App\Services\StockCodeService;
use App\Support\PosPrice;
use Illuminate\Support\Facades\DB;

class StockLabelController extends Controller
{
    public function __invoke(StockCodeService $codes)
    {
        $settings = Setting::pluck('value', 'key')->toArray();
        $labels = DB::transaction(function () use ($codes, $settings) {
            $items = ProductItem::where('status', 'available')
                ->whereHas('product', fn ($query) => $query->where('kind', 'watch'))
                ->with('product:id,name,model_number,price,currency')
                ->orderBy('product_id')->orderBy('id')->lockForUpdate()->get();

            return $items->map(function ($item) use ($codes, $settings) {
                if (! $item->system_unique_id) {
                    $item->update(['system_unique_id' => $codes->generate()]);
                }

                return [
                    'id' => $item->id,
                    'system_unique_id' => $item->system_unique_id,
                    'product' => $item->product->only(['name', 'model_number']),
                    'price_mmk' => PosPrice::inMmk((float) $item->product->price, $item->product->currency, $settings),
                ];
            });
        });

        return response()->json(['items' => $labels]);
    }
}
