<?php

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SeederUser extends Seeder
{
    public function run()
    {
        // Cek apakah sudah ada, jika belum buat
        $user = User::firstOrCreate(
            ['email' => 'admin@gmail.com'],
            [
                'name' => 'admin',
                'password' => Hash::make('admin'),
            ]
        );
        
        $this->command->info('Admin user created: admin@gmail.com / admin');
    }
}