<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
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

        $data = [
            'id' => $this->id,
            'name' => $this->name,
            'image' => $this->images ? asset(config('app.aws_url') . '/' . $this->images[0]) : null,
            'images' => $this->images ? array_map(function ($image) {
                return asset(config('app.aws_url') . '/' . $image);
            }, $this->images) : [],
            'brand_name' => $this->brand->name,
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

        $basePrice = $this->price;
        $webPrice = $this->web_price;
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
            // Login Customer
            $finalPrice = $basePrice;
            if ($customerDiscountPercentage > 0) {
                $finalPrice = $basePrice - ($basePrice * ($customerDiscountPercentage / 100));
            }
            $data['price'] = $finalPrice;
            $data['original_price'] = $basePrice; // show_price
            $data['discount'] = round($customerDiscountPercentage, 2);
        } else {
            // Public User / Login User (Internal User)
            $finalPrice = $basePrice - ($basePrice * ($productDiscount / 100));
            $showPrice = $webPrice ? $webPrice : $basePrice;
            
            $calculatedDiscount = $productDiscount;
            if ($webPrice && $showPrice > 0) {
                $calculatedDiscount = 100 - (($finalPrice / $showPrice) * 100);
            }
            
            $data['price'] = $finalPrice;
            $data['original_price'] = $showPrice;
            $data['discount'] = round($calculatedDiscount, 2);
        }

        $data['stock'] = $this->stock_quantity ?? 10; // Fallback if column missing
        // Check if stock exists in items or directly on product
        if ($this->relationLoaded('items')) {
             $data['stock'] = $this->items->where('status', 'available')->count();
        }

        return $data;
    }
}


//  return [
//             'id' => $this->id,
//             'name' => $this->name,
//             'price' => $this->price,
//             'image' => $this->images ? asset(config('app.aws_url') . '/' . $this->images[0]) : null,
//             'images' => $this->images ? array_map(function ($image) {
//                 return asset(config('app.aws_url') . '/' . $image);
//             }, $this->images) : [],
//         ];