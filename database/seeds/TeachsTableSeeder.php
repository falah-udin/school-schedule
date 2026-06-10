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
        
        // Mapping guru ke mapel (per mapel bisa punya multiple guru)
        $guruMapel = [
            'Matematika' => ['Guru Matematika', 'Guru Matematika 2'],
            'Bahasa Indonesia' => ['Guru Bahasa Indonesia', 'Guru Bahasa Indonesia 2'],
            'Bahasa Inggris' => ['Guru Bahasa Inggris', 'Guru Bahasa Inggris 2'],
            'IPA' => ['Guru IPA', 'Guru IPA 2'],
            'IPS' => ['Guru IPS'],
            'PPKn' => ['Guru PPKn'],
            'Agama' => ['Guru Agama'],
            'Penjaskes' => ['Guru Penjaskes'],
            'SBK/Prakarya' => ['Guru SBK'],
            'MULOK 1' => ['Guru MULOK', 'Guru MULOK 2'],
        ];
        
        $totalTeach = 0;
        
        foreach ($rooms as $room) {
            foreach ($courses as $course) {
                $guruNames = $guruMapel[$course->name] ?? ['Guru ' . $course->name];
                
                // Pilih guru secara round-robin berdasarkan ID kelas
                $index = ($room->id - 1) % count($guruNames);
                $guruName = $guruNames[$index];
                $guru = Lecturer::where('name', $guruName)->first();
                
                if (!$guru) {
                    $this->command->error("Guru untuk {$course->name} tidak ditemukan!");
                    continue;
                }
                
                Teach::create([
                    'courses_id' => $course->id,
                    'lecturers_id' => $guru->id,
                    'class_room' => $room->id
                ]);
                $totalTeach++;
            }
        }
        
        $this->command->info('Total Teach: ' . $totalTeach . ' (seharusnya ' . ($courses->count() * $rooms->count()) . ')');
    }
}