<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->boolean('scrape_failed')->default(false)->after('is_scraped');
            $table->timestamp('scrape_attempted_at')->nullable()->after('scraped_at');
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn(['scrape_failed', 'scrape_attempted_at']);
        });
    }
};
