<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BannerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'type' => $this->type ?? 'image',
            'image_url' => $this->image ? asset('storage/' . $this->image) : null,
            'link' => $this->link,
            'order' => $this->order,
        ];
    }
}
