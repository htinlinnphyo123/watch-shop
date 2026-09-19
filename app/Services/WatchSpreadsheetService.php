<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Collection as WatchCollection;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class WatchSpreadsheetService
{
    private const PRODUCT_FIELDS = [
        'name', 'model_number', 'barcode', 'price', 'cost_price', 'web_price', 'discount',
        'warranty_period', 'warranty_type', 'description', 'youtube_link', 'case_material',
        'priority_level', 'currency', 'crystal', 'water_resistant', 'case_shape', 'dial_size',
        'dial_color', 'strap_material', 'strap_size', 'strap_color', 'movement', 'gender',
        'clasp_type', 'strap_style', 'quick_release', 'origin', 'caliber_code', 'caseback_design',
        'is_featured', 'is_banner', 'is_limited_collection', 'is_latest', 'is_active', 'is_public',
    ];

    private const BOOLEAN_FIELDS = [
        'is_featured', 'is_banner', 'is_limited_collection', 'is_latest', 'is_active', 'is_public',
    ];

    public function __construct(
        private readonly StockCodeService $stockCodes,
        private readonly LowStockNotificationService $lowStockNotifications,
    ) {}

    public function exportRow(Product $watch): array
    {
        return [
            'id' => $watch->id,
            'name' => $watch->name,
            'model_number' => $watch->model_number,
            'barcode' => $watch->barcode,
            'price' => $watch->price,
            'cost_price' => $watch->cost_price,
            'web_price' => $watch->web_price,
            'discount' => $watch->discount,
            'currency' => $watch->currency,
            'brand' => $watch->brand?->name,
            'collection' => $watch->collection?->name,
            'categories' => $watch->categories->pluck('name')->implode(', '),
            'warranty_period' => $watch->warranty_period,
            'warranty_type' => $watch->warranty_type,
            'description' => $watch->description,
            'youtube_link' => $watch->youtube_link,
            'case_material' => $watch->case_material,
            'priority_level' => $watch->priority_level,
            'crystal' => $watch->crystal,
            'water_resistant' => $watch->water_resistant,
            'case_shape' => $watch->case_shape,
            'dial_size' => $watch->dial_size,
            'dial_color' => $watch->dial_color,
            'strap_material' => $watch->strap_material,
            'strap_size' => $watch->strap_size,
            'strap_color' => $watch->strap_color,
            'movement' => $watch->movement,
            'gender' => $watch->gender,
            'clasp_type' => $watch->clasp_type,
            'strap_style' => $watch->strap_style,
            'quick_release' => $watch->quick_release,
            'origin' => $watch->origin,
            'caliber_code' => $watch->caliber_code,
            'caseback_design' => $watch->caseback_design,
            'stock_total_read_only' => $watch->total_items,
            'stock_available_read_only' => $watch->available_items,
            'stock_sold_read_only' => $watch->sold_items,
            'stock_reserved_read_only' => $watch->reserved_items,
            'stock_to_add' => 0,
            'is_featured' => (int) $watch->is_featured,
            'is_banner' => (int) $watch->is_banner,
            'is_limited_collection' => (int) $watch->is_limited_collection,
            'is_latest' => (int) $watch->is_latest,
            'is_active' => (int) $watch->is_active,
            'is_public' => (int) $watch->is_public,
        ];
    }

    public function import(Collection $rows): array
    {
        $errors = [];
        $summary = ['created' => 0, 'updated' => 0, 'stock_added' => 0];
        $brands = $this->nameMap(Brand::pluck('id', 'name'));
        $collections = $this->nameMap(WatchCollection::pluck('id', 'name'));
        $categories = $this->nameMap(Category::pluck('id', 'name'));

        return DB::transaction(function () use ($rows, &$errors, &$summary, $brands, $collections, $categories) {
            foreach ($rows as $index => $rawRow) {
                $rowNumber = $index + 2;
                $row = collect($rawRow)->mapWithKeys(fn ($value, $key) => [trim((string) $key) => $this->cell($value)])->all();
                if (! collect($row)->contains(fn ($value) => $value !== null && $value !== '')) {
                    continue;
                }

                $rowErrors = [];
                $watch = null;
                if ($row['id'] ?? null) {
                    if (filter_var($row['id'], FILTER_VALIDATE_INT) === false) {
                        $rowErrors[] = "Column 'id' must be a whole number.";
                    } else {
                        $product = Product::find((int) $row['id']);
                        if (! $product) {
                            $rowErrors[] = "Column 'id': Product ID {$row['id']} was not found.";
                        } elseif ($product->kind !== 'watch') {
                            $rowErrors[] = "Column 'id': Product ID {$row['id']} is an accessory and cannot be changed from the watch import.";
                        } else {
                            $watch = $product;
                        }
                    }
                }

                $data = collect($row)->only(self::PRODUCT_FIELDS)->all();
                foreach (self::BOOLEAN_FIELDS as $field) {
                    if (array_key_exists($field, $data)) {
                        $boolean = $this->boolean($data[$field]);
                        if ($boolean === null && $data[$field] !== null && $data[$field] !== '') {
                            $rowErrors[] = "Column '{$field}' must be 1/0, true/false, or yes/no.";
                        } else {
                            $data[$field] = $boolean ?? false;
                        }
                    }
                }

                $data['brand_id'] = $this->relationId($row['brand'] ?? null, $brands, 'brand', true, $rowErrors);
                $data['collection_id'] = $this->relationId($row['collection'] ?? null, $collections, 'collection', false, $rowErrors);
                $categoryIds = $this->categoryIds($row['categories'] ?? null, $categories, $rowErrors);
                $stockToAdd = $this->stockToAdd($row, $watch, $rowErrors);

                $validator = validator($data, [
                    'name' => 'required|string|max:255',
                    'brand_id' => 'required|integer',
                    'price' => 'required|numeric|min:0|max:9999999999.99',
                    'cost_price' => 'nullable|numeric|min:0|max:9999999999.99',
                    'web_price' => 'nullable|numeric|min:0|max:9999999999.99',
                    'discount' => 'nullable|numeric|min:0|max:100',
                    'warranty_period' => 'nullable|integer|min:0|max:1200',
                    'warranty_type' => 'nullable|in:international_warranty,shop_warranty',
                    'youtube_link' => 'nullable|url:http,https|max:2048',
                    'case_material' => 'nullable|string|max:255',
                    'priority_level' => 'nullable|integer|in:0,1,2,3',
                    'currency' => 'required|in:MMK,USD,THB,SGD,CNY',
                    'barcode' => ['nullable', 'string', 'max:255', Rule::unique('products', 'barcode')->ignore($watch?->id)],
                    'model_number' => 'nullable|string|max:255',
                    'description' => 'nullable|string|max:10000',
                ]);
                foreach ($validator->errors()->messages() as $column => $messages) {
                    foreach ($messages as $message) {
                        $rowErrors[] = "Column '{$column}': {$message}";
                    }
                }

                if ($rowErrors) {
                    foreach ($rowErrors as $message) {
                        $errors[] = "Row {$rowNumber}: {$message}";
                    }

                    continue;
                }

                $data = $validator->validated() + collect($data)->except(array_keys($validator->getRules()))->all();
                $data['kind'] = 'watch';
                $data['priority_level'] = $data['priority_level'] ?? 0;
                $data['web_price'] = $data['web_price'] ?? 0;
                $data['discount'] = $data['discount'] ?? 0;
                if (! $watch && empty($data['barcode'])) {
                    $data['barcode'] = 'W-'.Str::ulid();
                }

                if ($watch) {
                    $watch->update($data);
                    $summary['updated']++;
                } else {
                    $watch = Product::create($data);
                    $summary['created']++;
                }
                $watch->categories()->sync($categoryIds);
                for ($number = 0; $number < $stockToAdd; $number++) {
                    $watch->items()->create(['status' => 'available', 'system_unique_id' => $this->stockCodes->generate()]);
                }
                $this->lowStockNotifications->sync($watch);
                $summary['stock_added'] += $stockToAdd;
            }

            if ($errors) {
                throw ValidationException::withMessages(['file' => $errors]);
            }

            return $summary;
        });
    }

    private function cell(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return is_string($value) ? (trim($value) === '' ? null : trim($value)) : $value;
    }

    private function nameMap(Collection $values): array
    {
        $map = [];
        foreach ($values as $name => $id) {
            $map[mb_strtolower(trim($name))] = $id;
        }

        return $map;
    }

    private function relationId(mixed $name, array $map, string $column, bool $required, array &$errors): ?int
    {
        if ($name === null || $name === '') {
            if ($required) {
                $errors[] = "Column '{$column}' is required.";
            }

            return null;
        }
        $id = $map[mb_strtolower(trim((string) $name))] ?? null;
        if (! $id) {
            $errors[] = "Column '{$column}': '{$name}' does not exist.";
        }

        return $id;
    }

    private function categoryIds(mixed $value, array $map, array &$errors): array
    {
        if ($value === null || $value === '') {
            return [];
        }
        $ids = [];
        foreach (array_unique(array_filter(array_map('trim', explode(',', (string) $value)))) as $name) {
            $id = $map[mb_strtolower($name)] ?? null;
            if (! $id) {
                $errors[] = "Column 'categories': '{$name}' does not exist.";
            } else {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    private function stockToAdd(array $row, ?Product $watch, array &$errors): int
    {
        $value = $row['stock_to_add'] ?? null;
        if (($value === null || $value === '') && array_key_exists('items_count', $row)) {
            $target = filter_var($row['items_count'], FILTER_VALIDATE_INT);
            $current = $watch?->items()->count() ?? 0;
            if ($target === false || $target < $current) {
                $errors[] = "Column 'items_count' must be a whole number greater than or equal to the existing total ({$current}).";

                return 0;
            }
            $value = $target - $current;
        }
        $value ??= 0;
        if (filter_var($value, FILTER_VALIDATE_INT) === false || (int) $value < 0 || (int) $value > 5000) {
            $errors[] = "Column 'stock_to_add' must be a whole number from 0 to 5000.";

            return 0;
        }

        return (int) $value;
    }

    private function boolean(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if ($value === 1 || $value === 0) {
            return (bool) $value;
        }

        return match (mb_strtolower(trim((string) $value))) {
            '1', 'true', 'yes', 'active' => true,
            '0', 'false', 'no', 'inactive' => false,
            default => null,
        };
    }
}
