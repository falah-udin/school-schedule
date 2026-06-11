<?php

use App\Models\Lecturer;
use Illuminate\Database\Seeder;

class LecturersTableSeeder extends Seeder
{
    public function run()
    {
        $lecturers = [
            // Matematika
            ['name' => 'Guru Matematika', 'nidn' => 'MTK001'],
            
            // Bahasa Indonesia
            ['name' => 'Guru Bahasa Indonesia', 'nidn' => 'BIN001'],
            
            // Bahasa Inggris
            ['name' => 'Guru Bahasa Inggris', 'nidn' => 'BIG001'],
            
            // IPA
            ['name' => 'Guru IPA', 'nidn' => 'IPA001'],
            
            // IPS
            ['name' => 'Guru IPS', 'nidn' => 'IPS001'],
            
            // PPKn
            ['name' => 'Guru PPKn', 'nidn' => 'PPK001'],
            
            // Agama
            ['name' => 'Guru Agama', 'nidn' => 'AGA001'],
            
            // Penjaskes
            ['name' => 'Guru Penjaskes', 'nidn' => 'PJK001'],
            
            // SBK/Prakarya
            ['name' => 'Guru SBK', 'nidn' => 'SBK001'],
            
            // MULOK
            ['name' => 'Guru MULOK', 'nidn' => 'MLK001'],
            
            // INFORMATIKA (baru)
            ['name' => 'Guru Informatika', 'nidn' => 'INF001'],
            
            // BK (baru)
            ['name' => 'Guru BK', 'nidn' => 'BK001'],
        ];
        
        foreach ($lecturers as $lec) {
            // Cek apakah sudah ada, jika belum buat
            Lecturer::firstOrCreate(
                ['name' => $lec['name']],
                ['nidn' => $lec['nidn']]
            );
        }
        
        $this->command->info('Total Lecturers: ' . Lecturer::count());
    }
}