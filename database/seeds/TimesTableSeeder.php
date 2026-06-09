<?php

use App\Models\Time;
use Illuminate\Database\Seeder;

class TimesTableSeeder extends Seeder
{
    public function run()
    {
        Time::insert([
            // Sesi 1
            [
                'time_begin' => '07:00',
                'time_finish' => '07:40',
                'range' => '07:00 - 07:40',
            ],
            // Sesi 2
            [
                'time_begin' => '07:40',
                'time_finish' => '08:20',
                'range' => '07:40 - 08:20',
            ],
            // 🟢 ISTIRAHAT 08:20-08:35 (tidak masuk)
            // Sesi 3
            [
                'time_begin' => '08:35',
                'time_finish' => '09:15',
                'range' => '08:35 - 09:15',
            ],
            // Sesi 4
            [
                'time_begin' => '09:15',
                'time_finish' => '09:55',
                'range' => '09:15 - 09:55',
            ],
            // 🟢 ISTIRAHAT 09:55-10:10 (tidak masuk)
            // Sesi 5
            [
                'time_begin' => '10:10',
                'time_finish' => '10:50',
                'range' => '10:10 - 10:50',
            ],
            // Sesi 6
            [
                'time_begin' => '10:50',
                'time_finish' => '11:30',
                'range' => '10:50 - 11:30',
            ],
        ]);
    }
}