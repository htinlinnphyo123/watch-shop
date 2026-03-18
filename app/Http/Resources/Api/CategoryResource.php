<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'label' => $this->name,
            'id' => $this->id,
            'children' => CategoryResource::collection($this->whenLoaded('children')),
            'image' => $this->when(
                $this->parent_id !== null,
                asset(config('app.aws_url') . '/' . $this->photo)
            ),
            'description'=>$this->description,
        ];
    }
}
