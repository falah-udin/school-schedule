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
        $courses = Course::all()->keyBy('name');
        $rooms = Room::all();
        $lecturers = Lecturer::all();
        
        $guruMapel = [
            'Guru Matematika' => 'Matematika',
            'Guru Matematika 2' => 'Matematika',
            'Guru Bahasa Indonesia' => 'Bahasa Indonesia',
            'Guru Bahasa Indonesia 2' => 'Bahasa Indonesia',
            'Guru Bahasa Inggris' => 'Bahasa Inggris',
            'Guru Bahasa Inggris 2' => 'Bahasa Inggris',
            'Guru IPA' => 'IPA',
            'Guru IPA 2' => 'IPA',
            'Guru IPS' => 'IPS',
            'Guru PPKn' => 'PPKn',
            'Guru Agama' => 'Agama',
            'Guru Penjaskes' => 'Penjaskes',
            'Guru SBK' => 'SBK/Prakarya',
            'Guru MULOK' => 'MULOK 1',
            'Guru MULOK 2' => 'MULOK 1',
        ];
        
        foreach ($lecturers as $guru) {
            $mapel = $guruMapel[$guru->name];
            $courseId = $courses[$mapel]->id;
            
            foreach ($rooms as $kelas) {
                Teach::create([
                    'lecturers_id' => $guru->id,
                    'courses_id' => $courseId,
                    'class_room' => $kelas->id
                ]);
            }
        }
        
        $this->command->info('Total Teach: ' . Teach::count());
    }
}