<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BrandResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'logo' => asset(config('app.aws_url') . '/' . $this->logo),
            'bg_logo' => asset(config('app.aws_url') . '/' . $this->bg_logo),
            'website' => $this->website,
        ];
    }
}
