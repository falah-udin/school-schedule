<?php

use App\Models\Course;
use Illuminate\Database\Seeder;

class CoursesTableSeeder extends Seeder
{
    public function run()
    {
        Course::insert([
            ['name' => 'Matematika'],
            ['name' => 'Bahasa Indonesia'],
            ['name' => 'Bahasa Inggris'],
            ['name' => 'IPA'],
            ['name' => 'IPS'],
            ['name' => 'PPKn'],
            ['name' => 'Agama'],
            ['name' => 'Penjaskes'],
            ['name' => 'SBK/Prakarya'],
            ['name' => 'MULOK 1'],
        ]);
    }
}