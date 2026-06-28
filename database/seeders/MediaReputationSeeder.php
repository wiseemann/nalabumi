<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MediaReputationSeeder extends Seeder
{
    public function run(): void
    {
        $reputations = [
            ['name' => 'kompas.com', 'score' => 95],
            ['name' => 'tempo.co', 'score' => 95],
            ['name' => 'tempointeraktif.com', 'score' => 95],
            ['name' => 'detik.com', 'score' => 90],
            ['name' => 'detiknet.com', 'score' => 90],
            ['name' => 'antaranews.com', 'score' => 95],
            ['name' => 'republika.co.id', 'score' => 90],
            ['name' => 'surya.co.id', 'score' => 80],
            ['name' => 'okezone.com', 'score' => 85],
            ['name' => 'inilah.com', 'score' => 80],
            ['name' => 'liputan6.com', 'score' => 90],
            ['name' => 'vivanews.com', 'score' => 85],
            ['name' => 'viva.co.id', 'score' => 85],
            ['name' => 'tvone.co.id', 'score' => 85],
            ['name' => 'bbc.co.uk', 'score' => 100],
            ['name' => 'id.berita.yahoo.com', 'score' => 80],
            ['name' => 'jpnn.com', 'score' => 85],
            ['name' => 'tribunnews.com', 'score' => 85],
            ['name' => 'wartakota.tribunnews.com', 'score' => 85],
            ['name' => 'fajar.co.id', 'score' => 80],
            ['name' => 'cnbcindonesia.com', 'score' => 90],
            ['name' => 'ft.com', 'score' => 100],
            ['name' => 'nytimes.com', 'score' => 100],
            ['name' => 'thebalitimes.com', 'score' => 80],
            ['name' => 'suara.com', 'score' => 85],
            ['name' => 'merdeka.com', 'score' => 85],
            ['name' => 'kumparan.com', 'score' => 90],
            ['name' => 'tirto.id', 'score' => 95],
            ['name' => 'jawapos.com', 'score' => 90],
            ['name' => 'mongabay.co.id', 'score' => 95],
            ['name' => 'greenpeace.org', 'score' => 95],
            ['name' => 'walhi.or.id', 'score' => 95],
        ];

        foreach ($reputations as $rep) {
            DB::table('media_reputations')->updateOrInsert(
                ['name' => $rep['name']],
                ['score' => $rep['score'], 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }
}
