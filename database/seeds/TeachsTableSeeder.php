<?php

use App\Models\Teach;
use App\Models\Course;
use App\Models\Room;
use App\Models\Lecturer;
use Illuminate\Database\Seeder;

class TeachsTableSeeder extends Seeder
{
    public function run()
    {
        // Hapus data lama
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        Teach::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        
        $courses = Course::all();
        $rooms = Room::all();
        
        // Mapping 1 GURU untuk 1 MAPEL (tidak ada multiple guru lagi)
        $guruMapel = [
            'Matematika' => 'Guru Matematika',
            'Bahasa Indonesia' => 'Guru Bahasa Indonesia',
            'Bahasa Inggris' => 'Guru Bahasa Inggris',
            'IPA' => 'Guru IPA',
            'IPS' => 'Guru IPS',
            'PPKn' => 'Guru PPKn',
            'Agama' => 'Guru Agama',
            'Penjaskes' => 'Guru Penjaskes',
            'SBK/Prakarya' => 'Guru SBK',
            'MULOK 1' => 'Guru MULOK',
            'Informatika' => 'Guru Informatika',      // MAPEL BARU
            'BK (Bimbingan Konseling)' => 'Guru BK',   // MAPEL BARU
        ];
        
        $totalTeach = 0;
        $errors = [];
        
        foreach ($rooms as $room) {
            foreach ($courses as $course) {
                // Ambil nama guru dari mapping
                $guruName = $guruMapel[$course->name] ?? null;
                
                if (!$guruName) {
                    $errors[] = "Guru untuk mapel '{$course->name}' tidak ditemukan dalam mapping!";
                    continue;
                }
                
                // Cari guru berdasarkan nama
                $guru = Lecturer::where('name', $guruName)->first();
                
                if (!$guru) {
                    $errors[] = "Guru dengan nama '{$guruName}' untuk mapel '{$course->name}' tidak ditemukan di database!";
                    continue;
                }
                
                // Cek apakah sudah ada data yang sama (untuk menghindari duplikasi)
                $existing = Teach::where('courses_id', $course->id)
                    ->where('lecturers_id', $guru->id)
                    ->where('class_room', $room->id)
                    ->first();
                
                if (!$existing) {
                    Teach::create([
                        'courses_id' => $course->id,
                        'lecturers_id' => $guru->id,
                        'class_room' => $room->id
                    ]);
                    $totalTeach++;
                }
            }
        }
        
        // Tampilkan hasil
        $expectedTotal = $courses->count() * $rooms->count();
        $this->command->info('=========================================');
        $this->command->info('Total Teach: ' . $totalTeach);
        $this->command->info('Expected: ' . $expectedTotal);
        
        if ($totalTeach !== $expectedTotal) {
            $this->command->warn('Ada ketidaksesuaian jumlah data!');
        }
        
        if (!empty($errors)) {
            $this->command->error('Error ditemukan:');
            foreach ($errors as $error) {
                $this->command->error('  - ' . $error);
            }
        } else {
            $this->command->info('✅ Semua data Teach berhasil dibuat!');
        }
        
        $this->command->info('=========================================');
    }
}