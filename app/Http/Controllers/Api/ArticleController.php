<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\ArticleResource;
use App\Models\Article;
use Illuminate\Http\Request;

class ArticleController extends Controller
{
    /**
     * GET /v1/spa/articles
     *
     * Paginated list of published articles.
     * Supports filtering by ?category= and ?tag=
     * Supports pagination via ?page= and ?per_page= (default 10)
     */
    public function index(Request $request)
    {
        $query = Article::published()
            ->orderBy('published_at', 'desc')
            ->orderBy('sort_order', 'asc')
            ->orderBy('created_at', 'desc');

        // Filter by category label
        $query->when($request->category, fn($q, $category) =>
            $q->where('category', $category)
        );

        // Filter by tag (JSON column contains value)
        $query->when($request->tag, fn($q, $tag) =>
            $q->whereJsonContains('tags', $tag)
        );

        $perPage = (int) $request->input('per_page', 10);
        $articles = $query->paginate($perPage);

        $paginated = ArticleResource::collection($articles)->response()->getData(true);

        return response()->json([
            'code'    => 200,
            'status'  => 'success',
            'message' => 'Get Articles Success',
            'data'    => $paginated,
        ]);
    }

    /**
     * GET /v1/spa/articles/{slug}
     *
     * Single published article by slug.
     */
    public function show(string $slug)
    {
        $article = Article::published()
            ->where('slug', $slug)
            ->firstOrFail();

        return response()->json([
            'code'    => 200,
            'status'  => 'success',
            'message' => 'Get Article Success',
            'data'    => new ArticleResource($article),
        ]);
    }
}
