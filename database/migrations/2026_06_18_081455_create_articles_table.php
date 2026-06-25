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
            $table->string('url')->unique();
            $table->string('title');
            $table->text('content')->nullable();
            $table->dateTime('published_at')->nullable();
            $table->string('source');
            $table->string('author')->nullable();
            $table->string('image_url')->nullable();
            $table->boolean('is_scraped')->default(false);
            $table->boolean('is_analyzed')->default(false);
            $table->boolean('has_embedding')->default(false);
            $table->timestamp('scraped_at')->nullable();
            $table->timestamp('analyzed_at')->nullable();
            $table->timestamps();

            $table->index('source');
            $table->index('published_at');
            $table->index('is_analyzed');
            $table->index('has_embedding');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
