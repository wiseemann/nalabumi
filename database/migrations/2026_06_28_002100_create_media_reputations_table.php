<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_reputations', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique()->comment('Nama media atau domain sumber');
            $table->integer('score')->default(50)->comment('Skor reputasi 0-100');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_reputations');
    }
};
