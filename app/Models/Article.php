<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Article extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'slug',
        'cover_image',
        'excerpt',
        'content',
        'category',
        'tags',
        'is_published',
        'published_at',
        'sort_order',
    ];

    protected $casts = [
        'tags'         => 'array',
        'is_published' => 'boolean',
        'published_at' => 'datetime',
        'sort_order'   => 'integer',
    ];

    /**
     * Auto-generate slug from title when creating.
     */
    protected static function booted(): void
    {
        static::creating(function (Article $article) {
            if (empty($article->slug)) {
                $article->slug = Str::slug($article->title);
            }
        });
    }

    /**
     * Scope: only published articles.
     * Visibility is controlled solely by the is_published flag.
     * published_at is a display/record field, not a scheduling gate.
     */
    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }
}
