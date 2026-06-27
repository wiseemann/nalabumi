<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('nalabumi:fetch-news')->everyThirtyMinutes();
// Diubah menjadi hourly (setiap jam) agar data di peta publik lebih cepat sinkron 
// mengikuti ritme AI yang menarik berita setiap 30 menit.
Schedule::command('nalabumi:recalculate-urgency')->hourly();
