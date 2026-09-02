<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class ArticleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $coverImage = $this->cover_image;

        $coverImageUrl = null;
        if ($coverImage) {
            if (Str::startsWith($coverImage, ['http://', 'https://'])) {
                $coverImageUrl = $coverImage;
            } elseif (config('app.aws_url')) {
                $coverImageUrl = rtrim(config('app.aws_url'), '/') . '/' . ltrim($coverImage, '/');
            } else {
                $coverImageUrl = asset('storage/' . ltrim($coverImage, '/'));
            }
        }

        return [
            'id'           => $this->id,
            'title'        => $this->title,
            'slug'         => $this->slug,
            'cover_image'  => $coverImageUrl,
            'excerpt'      => $this->excerpt,
            'content'      => $this->content,       // Full HTML/Markdown body
            'category'     => $this->category,
            'tags'         => $this->tags ?? [],
            'published_at' => $this->published_at?->toISOString(),
            'created_at'   => $this->created_at?->toISOString(),
        ];
    }
}
