<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@nalabumi.id'],
            [
                'name' => 'Nalabumi Admin',
                'password' => Hash::make('password'),
                'is_admin' => true,
            ]
        );

        $this->command->info('✅ Admin user created: admin@nalabumi.id / password');
    }
}
