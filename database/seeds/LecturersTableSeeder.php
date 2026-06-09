<?php

use App\Models\Lecturer;
use Illuminate\Database\Seeder;

class LecturersTableSeeder extends Seeder
{
    public function run()
    {
        $lecturers = [
            // Matematika (2 guru)
            ['name' => 'Guru Matematika', 'nidn' => 'MTK001'],
            ['name' => 'Guru Matematika 2', 'nidn' => 'MTK002'],
            
            // Bahasa Indonesia (2 guru)
            ['name' => 'Guru Bahasa Indonesia', 'nidn' => 'BIN001'],
            ['name' => 'Guru Bahasa Indonesia 2', 'nidn' => 'BIN002'],
            
            // Bahasa Inggris (2 guru)
            ['name' => 'Guru Bahasa Inggris', 'nidn' => 'BIG001'],
            ['name' => 'Guru Bahasa Inggris 2', 'nidn' => 'BIG002'],
            
            // IPA (2 guru)
            ['name' => 'Guru IPA', 'nidn' => 'IPA001'],
            ['name' => 'Guru IPA 2', 'nidn' => 'IPA002'],
            
            // IPS (1 guru)
            ['name' => 'Guru IPS', 'nidn' => 'IPS001'],
            
            // PPKn (1 guru)
            ['name' => 'Guru PPKn', 'nidn' => 'PPK001'],
            
            // Agama (1 guru)
            ['name' => 'Guru Agama', 'nidn' => 'AGA001'],
            
            // Penjaskes (1 guru)
            ['name' => 'Guru Penjaskes', 'nidn' => 'PJK001'],
            
            // SBK (1 guru)
            ['name' => 'Guru SBK', 'nidn' => 'SBK001'],
            
            // MULOK (2 guru)
            ['name' => 'Guru MULOK', 'nidn' => 'MLK001'],
            ['name' => 'Guru MULOK 2', 'nidn' => 'MLK002'],
        ];
        
        foreach ($lecturers as $lec) {
            Lecturer::create($lec);
        }
    }
}