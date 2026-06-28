<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('nalabumi:fetch-news')->everyThirtyMinutes();

// Penarikan Riwayat Berita (25 Kata Kunci) dari Bing News dijadwalkan sekali sehari tengah malam
// Mekanisme pengamanan delay 2 detik per kueri sudah tertanam di level service.
Schedule::command('nalabumi:fetch-historical')->dailyAt('00:00');

// Diubah menjadi hourly (setiap jam) agar data di peta publik lebih cepat sinkron 
// mengikuti ritme AI yang menarik berita setiap 30 menit.
Schedule::command('nalabumi:recalculate-urgency')->hourly();

// Solusi berkala untuk memastikan artikel yang sudah dianalisis tetapi gagal masuk ke Qdrant (embedding) diproses ulang.
Schedule::call(function () {
    $articles = \App\Models\Article::where('has_embedding', false)->where('is_analyzed', true)->get();
    foreach ($articles as $article) {
        \App\Jobs\GenerateEmbeddingJob::dispatch($article);
    }
})->everyTenMinutes();
