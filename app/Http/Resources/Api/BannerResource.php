<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class BannerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $type = $this->type ?? 'image';
        $mediaPath = $this->image;

        $mediaUrl = null;
        if ($mediaPath) {
            if (Str::startsWith($mediaPath, ['http://', 'https://'])) {
                $mediaUrl = $mediaPath;
            } elseif (config('app.aws_url')) {
                $mediaUrl = rtrim(config('app.aws_url'), '/') . '/' . ltrim($mediaPath, '/');
            } else {
                $mediaUrl = asset('storage/' . ltrim($mediaPath, '/'));
            }
        }

        return [
            'id' => $this->id,
            'title' => $this->title,
            'type' => $type,
            'image' => $type === 'image' ? $mediaUrl : $mediaUrl,
            'video' => $type === 'video' ? $mediaUrl : null,
            'link' => $this->link,
        ];
    }
}
