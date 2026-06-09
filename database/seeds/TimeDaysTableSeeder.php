<?php

use Illuminate\Database\Seeder;
use App\Models\Timeday;
use App\Models\Day;
use App\Models\Time;

class TimeDaysTableSeeder extends Seeder
{
    public function run()
    {
        // Hapus data lama
        Timeday::truncate();
        
        $days = Day::all();
        $times = Time::all();
        
        // Buat semua kombinasi hari dan waktu
        foreach ($days as $day) {
            foreach ($times as $time) {
                Timeday::create([
                    'days_id' => $day->id,
                    'times_id' => $time->id
                ]);
            }
        }
        
        $this->command->info('Total TimeDays: ' . Timeday::count());
    }
}