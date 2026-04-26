<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NewProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user('sanctum');
        $isAuthenticated = $user !== null;

        // Resolve MMK exchange rate for this product's currency
        $settings = \App\Models\Setting::pluck('value', 'key')->toArray();
        $rate = 1;
        if ($this->currency && $this->currency !== 'MMK') {
            $rateKey = strtolower($this->currency) . '_rate';
            $rate = floatval($settings[$rateKey] ?? 1);
        }
        $data = [
            'id' => $this->id,
            'name' => $this->name,
            'image' => $this->images ? asset(config('app.aws_url') . '/' . $this->images[0]) : null,
            'images' => $this->images ? array_map(function ($image) {
                return asset(config('app.aws_url') . '/' . $image);
            }, $this->images) : [],
            "preview_photo"=> $this->preview_photo ? asset(config('app.aws_url') . '/' . $this->preview_photo) : null,
            "preview_bg_photo"=> $this->preview_bg_photo ? asset(config('app.aws_url') . '/' . $this->preview_bg_photo) : null,
            'brand_name' => $this->brand->name,
            'currency' => 'MMK',
            'category_name' => $this->categories->pluck('name')->toArray(),
            'model_number' => $this->model_number,
            'description' => $this->description,
            'warranty_period'=>$this->warranty_period,
            'crystal'=>$this->crystal,
            'water_resistant'=>$this->water_resistant,
            'case_shape'=>$this->case_shape,
            'dial_size'=>$this->dial_size,
            'dial_color'=>$this->dial_color,
            'strap_material'=>$this->strap_material,
            'strap_size'=>$this->strap_size,
            'strap_color'=>$this->strap_color,
            'movement'=>$this->movement,
            'gender'=>$this->gender,
            'clasp_type'=>$this->clasp_type,
            'origin'=>$this->origin,
            'quick_release'=>$this->quick_release,
            'strap_style'=>$this->strap_style,
        ];

        $basePrice = floatval($this->price) * $rate;   // always in MMK
        $webPrice  = $this->web_price ? floatval($this->web_price) * $rate : null;
        $productDiscount = $this->discount ?? 0;
        
        $isCustomer = false;
        $customerDiscountPercentage = 0;
        
        if ($isAuthenticated) {
            if ($user instanceof \App\Models\Customer && $user->group) {
                $isCustomer = true;
                $customerDiscountPercentage = $user->group->percentage;
            } elseif ($user instanceof \App\Models\User && $user->customer && $user->customer->group) {
                $isCustomer = true;
                $customerDiscountPercentage = $user->customer->group->percentage;
            }
        }

        if ($isCustomer) {
            // Login Customer — apply group discount on top of MMK price
            $finalPrice = $basePrice;
            if ($customerDiscountPercentage > 0) {
                $finalPrice = $basePrice - ($basePrice * ($customerDiscountPercentage / 100));
            }
            $data['price']          = (float) round($finalPrice, 2);
            $data['original_price'] = (float) round($basePrice, 2);
            $data['discount']       = (float) round($customerDiscountPercentage, 2);
        } else {
            // Public User / Internal User
            $finalPrice = $basePrice - ($basePrice * ($productDiscount / 100));
            $showPrice  = $webPrice ? $webPrice : $basePrice;

            $calculatedDiscount = $productDiscount;
            if ($webPrice && $showPrice > 0) {
                $calculatedDiscount = 100 - (($finalPrice / $showPrice) * 100);
            }

            $data['price']          = (float) round($finalPrice, 2);
            $data['original_price'] = (float) round($showPrice, 2);
            $data['discount']       = (float) round($calculatedDiscount, 2);
        }
        $data['stock'] = $this->available_items > 0 ? $this->available_items : ($this->reserved_items > 0 ? $this->reserved_items : 0);
        $data['item_status'] = $this->available_items > 0 ? 'Available' : ($this->reserved_items > 0 ? 'Reserved' : 'Out Of Stock');
        // Will open Add_To_Cart button if stock > 0 and item_status='Available'
        /**
         * Available -> Reserved -> Out Of Stock
         */
        $data['final_stock_status'] = $data['stock'] > 0 ? $data['stock'] . ' ' . $data['item_status'] : 'Out Of Stock';
        return $data;
    }
}