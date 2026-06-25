<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Deforestasi', 'slug' => 'deforestasi', 'description' => 'Kasus penebangan hutan dan penggundulan lahan'],
            ['name' => 'Tambang Ilegal', 'slug' => 'tambang-ilegal', 'description' => 'Aktivitas pertambangan tanpa izin atau ilegal'],
            ['name' => 'Kebakaran Hutan', 'slug' => 'kebakaran-hutan', 'description' => 'Kebakaran hutan dan lahan (karhutla)'],
            ['name' => 'Pencemaran Sungai', 'slug' => 'pencemaran-sungai', 'description' => 'Pencemaran air sungai oleh limbah dan polutan'],
            ['name' => 'Pencemaran Laut', 'slug' => 'pencemaran-laut', 'description' => 'Pencemaran laut dan ekosistem pesisir'],
            ['name' => 'Perburuan Satwa', 'slug' => 'perburuan-satwa', 'description' => 'Perburuan liar dan perdagangan satwa dilindungi'],
            ['name' => 'Kerusakan Pesisir', 'slug' => 'kerusakan-pesisir', 'description' => 'Kerusakan ekosistem pesisir dan abrasi'],
            ['name' => 'Limbah Industri', 'slug' => 'limbah-industri', 'description' => 'Pencemaran oleh limbah industri dan pabrik'],
        ];

        DB::table('categories')->insert($categories);
    }
}
