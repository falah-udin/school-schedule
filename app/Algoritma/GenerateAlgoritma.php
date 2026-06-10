<?php namespace app\Algoritma;

use App\Models\Day;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\Teach;
use App\Models\Time;
use App\Models\Timenotavailable;
use App\Models\Course;
use DB;

class GenerateAlgoritma
{
    private $scheduledHours = [];
    private $dailyCount = [];
    private $classDailyCount = [];
    private $filteredClasses = [];
    private $minFulfilled = []; // Track apakah min per hari sudah terpenuhi

    public function setFilteredClasses($classes)
    {
        $this->filteredClasses = $classes;
    }

    /**
     * Cek apakah total JP per kelas melebihi kapasitas
     */
    public function checkIfPossible()
    {
        $rooms = Room::all();
        
        if (!empty($this->filteredClasses)) {
            $rooms = Room::whereIn('id', $this->filteredClasses)->get();
        }
        
        $jpPerDay = \App\Models\Setting::get('jp_per_day', 8);
        $daysPerWeek = 6;
        $maxJpPerWeek = $jpPerDay * $daysPerWeek;
        
        $violations = [];
        $allValid = true;
        
        foreach ($rooms as $room) {
            $totalJp = 0;
            $teachs = Teach::where('class_room', $room->id)->with('course')->get();
            
            foreach ($teachs as $teach) {
                $totalJp += $teach->course->hours_per_week;
            }
            
            if ($totalJp > $maxJpPerWeek) {
                $allValid = false;
                $violations[] = [
                    'class' => $room->name,
                    'required' => $totalJp,
                    'capacity' => $maxJpPerWeek,
                    'shortage' => $totalJp - $maxJpPerWeek
                ];
            }
        }
        
        return [
            'status' => $allValid,
            'message' => $allValid ? '✅ Kapasitas cukup, bisa generate jadwal!' : '❌ Tidak bisa generate jadwal! Total JP melebihi kapasitas.',
            'details' => $violations
        ];
    }

    /**
     * Proses random jadwal dengan validasi KETAT
     */
    public function randomingProcess($type, $maxAttempts = 2000)
    {
        $teach = $this->getUnscheduledTeach($type);
        
        if (!$teach) {
            return null;
        }
        
        $course = $teach->course;
        $attempt = 0;
        
        while ($attempt < $maxAttempts) {
            $day = Day::inRandomOrder()->first();
            
            $currentDayHours = $this->getDailyHours($type, $teach->id, $day->id);
            $remainingNeeded = $course->hours_per_week - $this->getScheduledHours($type, $teach->id);
            
            // Jika sudah mencapai max per hari, skip
            if ($currentDayHours >= $course->max_hours_per_day) {
                $attempt++;
                continue;
            }
            
            // Jika sudah mencapai target mingguan, stop
            if ($this->getScheduledHours($type, $teach->id) >= $course->hours_per_week) {
                return null;
            }
            
            $time = null;
            
            // 🔥 CEK: Apakah sudah ada jadwal di hari ini?
            $existingInDay = Schedule::where('type', $type)
                ->where('teachs_id', $teach->id)
                ->where('days_id', $day->id)
                ->exists();
            
            if ($existingInDay) {
                // Jika sudah ada, cari slot LANGSUNG setelah slot terakhir
                $lastSlot = Schedule::where('type', $type)
                    ->where('teachs_id', $teach->id)
                    ->where('days_id', $day->id)
                    ->orderBy('times_id', 'desc')
                    ->first();
                
                if ($lastSlot) {
                    $nextSlot = Time::where('id', '>', $lastSlot->times_id)
                        ->orderBy('time_begin')
                        ->first();
                    
                    if ($nextSlot) {
                        $time = $nextSlot;
                    }
                }
            }
            
            // Jika tidak ada jadwal sebelumnya atau slot berikutnya tidak tersedia, cari slot dari awal
            if (!$time) {
                // Jika ini JP pertama di hari ini, cari slot dari awal
                if ($currentDayHours == 0 && $remainingNeeded >= $course->min_hours_per_day) {
                    $consecutiveSlots = $this->getConsecutiveAvailableSlotsFromStart($type, $teach, $day, $course->min_hours_per_day);
                    if ($consecutiveSlots) {
                        $time = $consecutiveSlots[0];
                    }
                }
            }
            
            // Jika masih belum dapat slot, coba random
            if (!$time) {
                $time = Time::inRandomOrder()->first();
            }
            
            if (!$time) {
                $attempt++;
                continue;
            }
            
            // 6. Cek bentrok guru
            $check_lecturers_id = Schedule::join('teachs', 'teachs.id', '=', 'schedules.teachs_id')
                ->join('lecturers', 'lecturers.id', '=', 'teachs.lecturers_id')
                ->where('lecturers_id', $teach->lecturers_id)
                ->where('days_id', $day->id)
                ->where('times_id', $time->id)
                ->where('type', $type)
                ->first();
            
            // 7. Cek bentrok kelas
            $check_class_id = Schedule::where('rooms_id', $teach->class_room)
                ->where('days_id', $day->id)
                ->where('times_id', $time->id)
                ->where('type', $type)
                ->first();
            
            // 8. Cek waktu tidak available
            $check_timenotavailable = Timenotavailable::where('lecturers_id', $teach->lecturers_id)
                ->where('days_id', $day->id)
                ->where('times_id', $time->id)
                ->first();
            
            if (!$check_timenotavailable && !$check_lecturers_id && !$check_class_id) {
                $params = [
                    'teachs_id' => $teach->id,
                    'days_id' => $day->id,
                    'times_id' => $time->id,
                    'rooms_id' => $teach->class_room,
                    'type' => $type
                ];
                
                $insert = Schedule::create($params);
                
                $this->incrementScheduledHours($type, $teach->id);
                $this->incrementDailyHours($type, $teach->id, $day->id);
                $this->incrementClassDailyHours($type, $teach->class_room, $day->id);
                
                return $insert;
            }
            
            $attempt++;
        }
        
        return null;
    }
    
    /**
     * Hitung sisa hari yang tersedia untuk teach
     */
    private function getRemainingDays($type, $teachId)
    {
        $usedDays = Schedule::where('type', $type)
            ->where('teachs_id', $teachId)
            ->groupBy('days_id')
            ->count();
        
        return 6 - $usedDays;
    }
    
    /**
     * Ambil teach yang masih kurang jamnya (prioritas: yang hampir selesai)
     */
    private function getUnscheduledTeach($type)
    {
        $teachs = Teach::with('course');
        
        if (!empty($this->filteredClasses)) {
            $teachs = $teachs->whereIn('class_room', $this->filteredClasses);
        }
        
        $teachs = $teachs->get();
        
        $bestTeach = null;
        $maxRemaining = -1;
        
        foreach ($teachs as $teach) {
            $scheduled = $this->getScheduledHours($type, $teach->id);
            $remaining = $teach->course->hours_per_week - $scheduled;
            
            if ($remaining > $maxRemaining) {
                $maxRemaining = $remaining;
                $bestTeach = $teach;
            }
        }
        
        return $bestTeach;
    }
    
    /**
     * Validasi MIN per hari dan perbaiki jadwal
     */
    public function validateAndFixMinHours($type)
    {
        $violations = [];
        $teachs = Teach::with('course')->get();
        
        foreach ($teachs as $teach) {
            $course = $teach->course;
            
            // Ambil jadwal per hari
            $dailySchedules = Schedule::where('type', $type)
                ->where('teachs_id', $teach->id)
                ->select('days_id', DB::raw('count(*) as total'))
                ->groupBy('days_id')
                ->get();
            
            foreach ($dailySchedules as $sch) {
                if ($sch->total < $course->min_hours_per_day) {
                    $violations[] = [
                        'teach_id' => $teach->id,
                        'course' => $course->name,
                        'teacher' => $teach->lecturer->name,
                        'class' => $teach->room->name,
                        'day_id' => $sch->days_id,
                        'day' => Day::find($sch->days_id)->name_day,
                        'actual' => $sch->total,
                        'required' => $course->min_hours_per_day
                    ];
                }
            }
        }
        
        return $violations;
    }
    
    /**
     * Perbaiki jadwal yang melanggar MIN per hari
     */
    public function repairMinHours($type)
    {
        $violations = $this->validateAndFixMinHours($type);
        $maxRepairs = 100;
        $repairCount = 0;
        
        while (!empty($violations) && $repairCount < $maxRepairs) {
            foreach ($violations as $v) {
                // Hapus jadwal yang melanggar di hari itu
                Schedule::where('type', $type)
                    ->where('teachs_id', $v['teach_id'])
                    ->where('days_id', $v['day_id'])
                    ->delete();
                
                // Reset counter
                $this->scheduledHours = [];
                $this->dailyCount = [];
                $this->classDailyCount = [];
                
                // Generate ulang untuk teach ini
                $this->randomingProcess($type);
            }
            
            $violations = $this->validateAndFixMinHours($type);
            $repairCount++;
        }
        
        return $violations;
    }
    
    // ==================== METHOD LAINNYA (tidak berubah) ====================
    
    private function getScheduledHours($type, $teachId)
    {
        if (!isset($this->scheduledHours[$type][$teachId])) {
            $this->scheduledHours[$type][$teachId] = Schedule::where('type', $type)
                ->where('teachs_id', $teachId)
                ->count();
        }
        return $this->scheduledHours[$type][$teachId];
    }
    
    private function incrementScheduledHours($type, $teachId)
    {
        if (!isset($this->scheduledHours[$type][$teachId])) {
            $this->scheduledHours[$type][$teachId] = 0;
        }
        $this->scheduledHours[$type][$teachId]++;
    }
    
    private function getDailyHours($type, $teachId, $dayId)
    {
        if (!isset($this->dailyCount[$type][$teachId][$dayId])) {
            $this->dailyCount[$type][$teachId][$dayId] = Schedule::where('type', $type)
                ->where('teachs_id', $teachId)
                ->where('days_id', $dayId)
                ->count();
        }
        return $this->dailyCount[$type][$teachId][$dayId];
    }
    
    private function incrementDailyHours($type, $teachId, $dayId)
    {
        if (!isset($this->dailyCount[$type][$teachId][$dayId])) {
            $this->dailyCount[$type][$teachId][$dayId] = 0;
        }
        $this->dailyCount[$type][$teachId][$dayId]++;
    }
    
    private function getClassDailyHours($type, $roomId, $dayId)
    {
        if (!isset($this->classDailyCount[$type][$roomId][$dayId])) {
            $this->classDailyCount[$type][$roomId][$dayId] = Schedule::where('type', $type)
                ->where('rooms_id', $roomId)
                ->where('days_id', $dayId)
                ->count();
        }
        return $this->classDailyCount[$type][$roomId][$dayId];
    }
    
    private function incrementClassDailyHours($type, $roomId, $dayId)
    {
        if (!isset($this->classDailyCount[$type][$roomId][$dayId])) {
            $this->classDailyCount[$type][$roomId][$dayId] = 0;
        }
        $this->classDailyCount[$type][$roomId][$dayId]++;
    }
    
    public function randKromosom($kromosom, $count_teachs)
    {
        $check = $this->checkIfPossible();
        
        if (!$check['status']) {
            $message = "⚠️ GAGAL! Tidak bisa generate jadwal.\n\n";
            $message .= "Total JP per kelas melebihi kapasitas:\n";
            foreach ($check['details'] as $v) {
                $message .= "• Kelas {$v['class']}: butuh {$v['required']} JP, kapasitas {$v['capacity']} JP (kelebihan {$v['shortage']} JP)\n";
            }
            $message .= "\n📌 Solusi: Kurangi JP/minggu pada mata pelajaran.";
            throw new \Exception($message);
        }
        
        for ($i = 0; $i < $kromosom; $i++) {
            Schedule::where('type', $i + 1)->delete();
        }
        
        for ($i = 0; $i < $kromosom; $i++) {
            $this->scheduledHours = [];
            $this->dailyCount = [];
            $this->classDailyCount = [];
            
            $totalRequiredSlots = $this->calculateTotalRequiredSlots();
            
            $successCount = 0;
            for ($j = 0; $j < $totalRequiredSlots; $j++) {
                $result = $this->randomingProcess($i);
                if ($result) {
                    $successCount++;
                } else {
                    break;
                }
            }
            
            // Perbaiki jadwal yang melanggar MIN per hari
            $this->repairMinHours($i);
            
            \Log::info("Kromosom " . ($i + 1) . ": " . $successCount . " dari " . $totalRequiredSlots . " slot terjadwal");
        }
        
        return [];
    }
    
    private function calculateTotalRequiredSlots()
    {
        $teachs = Teach::with('course');
        
        if (!empty($this->filteredClasses)) {
            $teachs = $teachs->whereIn('class_room', $this->filteredClasses);
        }
        
        $teachs = $teachs->get();
        $total = 0;
        
        foreach ($teachs as $teach) {
            $total += $teach->course->hours_per_week;
        }
        
        return $total;
    }
    
    // Method checkPinalty, increaseProccess, validateMinHoursPerDay, repairSchedule tetap sama...
    // (tambahkan di bawah)
    
    public function checkPinalty()
    {
        $schedules = Schedule::join('teachs', 'teachs.id', '=', 'schedules.teachs_id')
            ->join('lecturers', 'lecturers.id', '=', 'teachs.lecturers_id')
            ->select(DB::raw('lecturers_id, days_id, times_id, type, count(*) as `jumlah`'))
            ->groupBy('lecturers_id')
            ->groupBy('days_id')
            ->groupBy('times_id')
            ->groupBy('type')
            ->having('jumlah', '>', 1)
            ->get();

        $this->increaseProccess($schedules);

        $schedules = Schedule::select(DB::raw('teachs_id, days_id, times_id, type, count(*) as `jumlah`'))
            ->groupBy('teachs_id')
            ->groupBy('days_id')
            ->groupBy('times_id')
            ->groupBy('type')
            ->having('jumlah', '>', 1)
            ->get();

        $this->increaseProccess($schedules);

        $schedules = Schedule::select(DB::raw('teachs_id, days_id, rooms_id, type, count(*) as `jumlah`'))
            ->groupBy('teachs_id')
            ->groupBy('days_id')
            ->groupBy('rooms_id')
            ->groupBy('type')
            ->having('jumlah', '>', 1)
            ->get();

        $this->increaseProccess($schedules);

        $schedules = Schedule::where('days_id', Schedule::FRIDAY)->whereIn('times_id', [6, 5, 4])->get();

        if (!empty($schedules)) {
            foreach ($schedules as $schedule) {
                $schedule->value = $schedule->value + 1;
                $schedule->value_process = $schedule->value_process . "+ 1 ";
                $schedule->save();
            }
        }

        $time_not_availables = Timenotavailable::get();

        if (!empty($time_not_availables)) {
            foreach ($time_not_availables as $time_not_available) {
                $schedules = Schedule::whereHas('teach', function ($query) use ($time_not_available) {
                    $query->whereHas('lecturer', function ($q) use ($time_not_available) {
                        $q->where('lecturers.id', $time_not_available->lecturers_id);
                    });
                });

                $schedules = $schedules->where('days_id', $time_not_available->days_id)
                    ->where('times_id', $time_not_available->times_id)
                    ->get();

                if (!empty($schedules)) {
                    foreach ($schedules as $schedule) {
                        $schedule->value = $schedule->value + 1;
                        $schedule->value_process = $schedule->value_process . "+ 1 ";
                        $schedule->save();
                    }
                }
            }
        }

        $schedules = Schedule::get();

        foreach ($schedules as $schedule) {
            $schedule->value = 1 / (1 + $schedule->value);
            $schedule->save();
        }

        return $schedules;
    }
    
    public function increaseProccess($schedules = '')
    {
        if (!empty($schedules)) {
            foreach ($schedules as $schedule) {
                if ($schedule->jumlah > 1) {
                    $schedule_wheres = Schedule::where('type', $schedule->type)->get();
                    foreach ($schedule_wheres as $schedule_where) {
                        $schedule_where->value = $schedule_where->value + ($schedule->jumlah - 1);
                        $schedule_where->value_process = $schedule_where->value_process . " + " . ($schedule->jumlah - 1);
                        $schedule_where->save();
                    }
                }
            }
        }
        return $schedules;
    }

    /**
     * Cari slot waktu berurutan yang tersedia dalam satu hari (mulai dari jam pertama)
     */
    private function getConsecutiveAvailableSlotsFromStart($type, $teach, $day, $neededSlots)
    {
        $times = Time::orderBy('time_begin')->get();
        
        for ($i = 0; $i <= $times->count() - $neededSlots; $i++) {
            $isAvailable = true;
            $slots = [];
            
            for ($j = 0; $j < $neededSlots; $j++) {
                $checkTime = $times[$i + $j];
                
                // Cek apakah slot ini sudah terisi untuk guru atau kelas
                $isBooked = Schedule::where('type', $type)
                    ->where('days_id', $day->id)
                    ->where('times_id', $checkTime->id)
                    ->where(function($q) use ($teach) {
                        $q->where('rooms_id', $teach->class_room)
                        ->orWhere('teachs_id', $teach->id);
                    })
                    ->exists();
                
                if ($isBooked) {
                    $isAvailable = false;
                    break;
                }
                
                $slots[] = $checkTime;
            }
            
            if ($isAvailable) {
                return $slots;
            }
        }
        
        return null;
    }

    /**
     * Cek apakah dalam satu hari, jadwal untuk satu mapel berurutan (tidak terselingi)
     */
    private function isMapelConsecutiveInDay($type, $teachId, $dayId)
    {
        $schedules = Schedule::where('type', $type)
            ->where('teachs_id', $teachId)
            ->where('days_id', $dayId)
            ->orderBy('times_id')
            ->get();
        
        if ($schedules->count() <= 1) {
            return true; // 1 atau 0 jadwal, sudah otomatis consecutive
        }
        
        $times = $schedules->pluck('times_id')->toArray();
        
        // Cek apakah times_id berurutan (contoh: [1,2,3] atau [2,3,4])
        for ($i = 0; $i < count($times) - 1; $i++) {
            if ($times[$i + 1] != $times[$i] + 1) {
                return false; // Tidak berurutan, ada gap
            }
        }
        
        return true;
    }

    /**
     * Perbaiki jadwal yang tidak kontinu dalam satu hari untuk satu mapel
     */
    public function repairConsecutiveInDay($type)
    {
        $teachs = Teach::with('course')->get();
        $maxRepairs = 100;
        $repairCount = 0;
        $fixed = false;
        
        do {
            $fixed = false;
            
            foreach ($teachs as $teach) {
                // Ambil semua hari yang memiliki jadwal untuk teach ini
                $days = Schedule::where('type', $type)
                    ->where('teachs_id', $teach->id)
                    ->groupBy('days_id')
                    ->pluck('days_id');
                
                foreach ($days as $dayId) {
                    if (!$this->isMapelConsecutiveInDay($type, $teach->id, $dayId)) {
                        // Hapus semua jadwal teach ini di hari itu
                        Schedule::where('type', $type)
                            ->where('teachs_id', $teach->id)
                            ->where('days_id', $dayId)
                            ->delete();
                        
                        // Reset counter
                        $this->scheduledHours = [];
                        $this->dailyCount = [];
                        $this->classDailyCount = [];
                        
                        // Generate ulang untuk teach ini (prioritaskan hari itu)
                        $course = $teach->course;
                        $neededSlots = $course->hours_per_week - $this->getScheduledHours($type, $teach->id);
                        
                        for ($i = 0; $i < $neededSlots; $i++) {
                            $this->randomingProcess($type);
                        }
                        
                        $fixed = true;
                        $repairCount++;
                        break 2; // Keluar dari kedua loop, mulai ulang
                    }
                }
            }
            
            if ($repairCount >= $maxRepairs) {
                break;
            }
            
        } while ($fixed);
        
        return $repairCount;
    }

}