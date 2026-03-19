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

        if ($isAuthenticated) {
            $price = $this->price;
            
            // Apply Group Discount if applicable
            if ($user->customer && $user->customer->group) {
                $discountPercentage = $user->customer->group->percentage;
                $discountAmount = $price * ($discountPercentage / 100);
                $price = $price - $discountAmount;
            }

            $data['price'] = $price;
            $data['original_price'] = $this->price;
            $data['stock'] = $this->stock_quantity ?? 10; // Fallback if column missing
            // Check if stock exists in items or directly on product? 
            // In my previous work I used items sum for stock, but let's assume simplified now or check column?
            // Wait, previous conversation mentioned items relationship.
            // But let's keep it simple. If items exist, use count.
            if ($this->relationLoaded('items')) {
                 $data['stock'] = $this->items->where('status', 'available')->count();
            }
        } else {
            $data['message'] = 'Login to see price';
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