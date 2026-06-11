<?php

use App\Models\Course;
use Illuminate\Database\Seeder;

class CoursesTableSeeder extends Seeder
{
    public function run()
    {
        Course::insert([
            ['name' => 'Matematika', 'hours_per_week' => 5, 'min_hours_per_day' => 2, 'max_hours_per_day' => 3],
            ['name' => 'Bahasa Indonesia', 'hours_per_week' => 6, 'min_hours_per_day' => 2, 'max_hours_per_day' => 2],
            ['name' => 'Bahasa Inggris', 'hours_per_week' => 4, 'min_hours_per_day' => 2, 'max_hours_per_day' => 2],
            ['name' => 'IPA', 'hours_per_week' => 5, 'min_hours_per_day' => 2, 'max_hours_per_day' => 3],
            ['name' => 'IPS', 'hours_per_week' => 4, 'min_hours_per_day' => 2, 'max_hours_per_day' => 2],
            ['name' => 'PPKn', 'hours_per_week' => 3, 'min_hours_per_day' => 3, 'max_hours_per_day' => 3],
            ['name' => 'Agama', 'hours_per_week' => 3, 'min_hours_per_day' => 3, 'max_hours_per_day' => 3],
            ['name' => 'Penjaskes', 'hours_per_week' => 3, 'min_hours_per_day' => 3, 'max_hours_per_day' => 3],
            ['name' => 'SBK/Prakarya', 'hours_per_week' => 3, 'min_hours_per_day' => 3, 'max_hours_per_day' => 3],
            ['name' => 'MULOK 1', 'hours_per_week' => 2, 'min_hours_per_day' => 2, 'max_hours_per_day' => 2],
            // MAPEL BARU:
            ['name' => 'Informatika', 'hours_per_week' => 3, 'min_hours_per_day' => 3, 'max_hours_per_day' => 3],
            ['name' => 'BK (Bimbingan Konseling)', 'hours_per_week' => 1, 'min_hours_per_day' => 1, 'max_hours_per_day' => 1],
        ]);
    }
}