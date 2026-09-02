<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('cover_image')->nullable();          // DigitalOcean Spaces path
            $table->text('excerpt')->nullable();                // Short summary / meta description
            $table->longText('content');                       // Full rich-text body (HTML/Markdown)
            $table->string('category')->nullable();            // Simple label: "News", "Tips", etc.
            $table->json('tags')->nullable();                  // ["watchcare", "luxury"]
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->integer('sort_order')->default(0);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
