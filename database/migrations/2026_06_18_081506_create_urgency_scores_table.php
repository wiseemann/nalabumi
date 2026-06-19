<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('urgency_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained()->onDelete('cascade');
            $table->integer('score')->default(0)->comment('Urgency score 0-100');
            $table->enum('level', ['rendah', 'sedang', 'tinggi', 'kritis'])->default('rendah');
            $table->integer('case_count')->default(0);
            $table->integer('avg_severity')->default(0);
            $table->date('date');
            $table->timestamps();

            $table->unique(['location_id', 'date']);
            $table->index('score');
            $table->index('level');
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('urgency_scores');
    }
};
