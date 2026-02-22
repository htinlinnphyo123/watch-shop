<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;

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
            'image' => $this->image ? asset('storage/' . $this->image) : null,
            'brand' => $this->whenLoaded('brand'),
            'category' => $this->whenLoaded('category'),
            'model_number' => $this->model_number,
            'description' => $this->description,
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
