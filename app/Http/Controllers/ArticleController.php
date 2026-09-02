<?php

namespace App\Http\Controllers;

use App\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ArticleController extends Controller
{
    public function index()
    {
        return Inertia::render('Articles/Index', [
            'articles' => Article::latest()->paginate(15),
        ]);
    }

    public function create()
    {
        return Inertia::render('Articles/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title'        => 'required|string|max:255',
            'slug'         => 'nullable|string|max:255|unique:articles,slug',
            'cover_image'  => 'nullable|file|mimes:jpg,jpeg,png,webp,gif|max:10240',
            'content'      => 'required|string',
            'is_published' => 'boolean',
            'published_at' => 'nullable|date',
            'sort_order'   => 'integer',
        ]);

        // Auto-generate slug if not provided
        $validated['slug'] = $validated['slug'] ?? Str::slug($validated['title']);

        // Auto-set published_at when publishing without a specific date
        if (!empty($validated['is_published']) && empty($validated['published_at'])) {
            $validated['published_at'] = now();
        }

        if ($request->hasFile('cover_image')) {
            $validated['cover_image'] = $request->file('cover_image')
                ->store('articles', env('FILESYSTEM_DISK', 's3'));
        }

        Article::create($validated);

        return redirect()->route('articles.index')->with('success', 'Article created.');
    }

    public function edit(Article $article)
    {
        return Inertia::render('Articles/Edit', [
            'article' => $article,
        ]);
    }

    public function update(Request $request, Article $article)
    {
        $validated = $request->validate([
            'title'        => 'required|string|max:255',
            'slug'         => 'nullable|string|max:255|unique:articles,slug,' . $article->id,
            'cover_image'  => 'nullable|file|mimes:jpg,jpeg,png,webp,gif|max:10240',
            'content'      => 'required|string',
            'is_published' => 'boolean',
            'published_at' => 'nullable|date',
            'sort_order'   => 'integer',
        ]);

        $dataToUpdate = collect($validated)->except('cover_image')->toArray();

        // Auto-set published_at when publishing without a specific date
        if (!empty($dataToUpdate['is_published']) && empty($dataToUpdate['published_at']) && empty($article->published_at)) {
            $dataToUpdate['published_at'] = now();
        }

        if ($request->hasFile('cover_image')) {
            // Delete old image from storage
            if ($article->cover_image) {
                Storage::disk(env('FILESYSTEM_DISK', 's3'))->delete($article->cover_image);
            }
            $dataToUpdate['cover_image'] = $request->file('cover_image')
                ->store('articles', env('FILESYSTEM_DISK', 's3'));
        }

        $article->update($dataToUpdate);

        return redirect()->back()->with('success', 'Article updated.');
    }

    public function destroy(Article $article)
    {
        // Soft delete — content preserved, recoverable
        $article->delete();
        return redirect()->back()->with('success', 'Article deleted.');
    }

    /**
     * POST /articles/upload-image
     * Called by the Quill editor image handler to store an inline image.
     * Returns JSON { url: "..." } consumed by the editor to embed the <img>.
     */
    public function uploadImage(Request $request)
    {
        $request->validate([
            'image' => 'required|file|mimes:jpg,jpeg,png,webp,gif|max:10240',
        ]);

        $path = $request->file('image')->store('articles/inline', env('FILESYSTEM_DISK', 's3'));

        $url = config('app.aws_url')
            ? rtrim(config('app.aws_url'), '/') . '/' . ltrim($path, '/')
            : asset('storage/' . $path);

        return response()->json(['url' => $url]);
    }
}
