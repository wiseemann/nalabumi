<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->onDelete('cascade');
            $table->foreignId('location_id')->constrained()->onDelete('cascade');
            $table->foreignId('category_id')->constrained()->onDelete('cascade');
            $table->text('cause')->nullable();
            $table->text('impact')->nullable();
            $table->integer('severity')->default(0)->comment('Severity score 0-100');
            $table->timestamps();

            $table->index('article_id');
            $table->index('location_id');
            $table->index('category_id');
            $table->index('severity');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cases');
    }
};
