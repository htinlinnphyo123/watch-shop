<?php

namespace App\Http\Controllers;

use App\Models\ProductItem;
use App\Models\Setting;
use App\Services\StockCodeService;
use App\Support\PosPrice;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class StockLabelController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate(['search' => 'nullable|string|max:100', 'page' => 'nullable|integer|min:1']);
        $search = trim($validated['search'] ?? '');
        $items = ProductItem::where('status', 'available')
            ->whereHas('product', fn ($query) => $query->where('kind', 'watch'))
            ->when($search !== '', function ($query) use ($search) {
                $term = '%'.mb_strtolower($search).'%';
                $query->where(function ($query) use ($term) {
                    $query->whereRaw('LOWER(system_unique_id) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(serial_number) LIKE ?', [$term])
                        ->orWhereHas('product', fn ($product) => $product->where(function ($product) use ($term) {
                            $product->whereRaw('LOWER(name) LIKE ?', [$term])->orWhereRaw('LOWER(model_number) LIKE ?', [$term]);
                        }));
                });
            })
            ->with('product:id,name,model_number')
            ->select(['id', 'product_id', 'system_unique_id', 'serial_number'])
            ->orderBy('product_id')->orderBy('id')->paginate(30);

        return response()->json($items);
    }

    public function __invoke(Request $request, StockCodeService $codes)
    {
        $validated = $request->validate([
            'item_ids' => 'sometimes|required|array|list|min:1|max:1000',
            'item_ids.*' => 'required|integer|distinct',
        ]);
        $selectedIds = $validated['item_ids'] ?? null;
        $settings = Setting::pluck('value', 'key')->toArray();
        $labels = DB::transaction(function () use ($codes, $settings, $selectedIds) {
            $items = ProductItem::where('status', 'available')
                ->when($selectedIds !== null, fn ($query) => $query->whereIn('id', $selectedIds))
                ->whereHas('product', fn ($query) => $query->where('kind', 'watch'))
                ->with('product:id,name,model_number,price,currency')
                ->orderBy('product_id')->orderBy('id')->lockForUpdate()->get();

            if ($selectedIds !== null && $items->count() !== count($selectedIds)) {
                throw ValidationException::withMessages(['item_ids' => 'Some selected units are no longer available. Remove them from your selection and try again.']);
            }

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
