<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('regency_name')->nullable();
            $table->string('province_name')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('geojson_id')->nullable()->comment('Reference to GeoJSON feature');
            $table->timestamps();

            $table->unique(['name', 'regency_name', 'province_name']);
            $table->index('regency_name');
            $table->index('province_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
