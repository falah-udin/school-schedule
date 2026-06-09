<?php

use App\Models\Room;
use Illuminate\Database\Seeder;

class RoomsTableSeeder extends Seeder
{
    public function run()
    {
        $kelas = ['7', '8', '9'];
        $rombel = ['A', 'B', 'C', 'D'];
        
        foreach ($kelas as $k) {
            foreach ($rombel as $r) {
                Room::create([
                    'name' => $k . $r,
                    'type' => 'Campur'
                ]);
            }
        }
    }
}