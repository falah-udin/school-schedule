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
    
    /**
     * Cek apakah total JP per kelas melebihi kapasitas
     * @return array ['status' => bool, 'message' => string, 'details' => array]
     */
    public function checkIfPossible()
    {
        $rooms = Room::all();
        
        // Jika ada filter kelas, hanya cek kelas yang difilter
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

    public function randomingProcess($type, $maxAttempts = 1000)
    {
        $teach = $this->getUnscheduledTeach($type);
        
        if (!$teach) {
            return null;
        }
        
        $attempt = 0;
        
        while ($attempt < $maxAttempts) {
            $day = Day::inRandomOrder()->first();
            $time = Time::inRandomOrder()->first();
            $course = $teach->course;
            
            // 1. Cek target JP per minggu
            $currentWeekHours = $this->getScheduledHours($type, $teach->id);
            if ($currentWeekHours >= $course->hours_per_week) {
                return null;
            }
            
            // 2. Cek max JP per hari
            $currentDayHours = $this->getDailyHours($type, $teach->id, $day->id);
            if ($currentDayHours >= $course->max_hours_per_day) {
                $attempt++;
                continue;
            }
            
            // 3. Cek kepadatan kelas per hari
            $classDayHours = $this->getClassDailyHours($type, $teach->class_room, $day->id);
            $maxJpPerDay = \App\Models\Setting::get('jp_per_day', 8);
            if ($classDayHours >= $maxJpPerDay) {
                $attempt++;
                continue;
            }
            
            // 4. Cek bentrok guru
            $check_lecturers_id = Schedule::join('teachs', 'teachs.id', '=', 'schedules.teachs_id')
                ->join('lecturers', 'lecturers.id', '=', 'teachs.lecturers_id')
                ->where('lecturers_id', $teach->lecturers_id)
                ->where('days_id', $day->id)
                ->where('times_id', $time->id)
                ->where('type', $type)
                ->first();
            
            // 5. Cek bentrok kelas
            $check_class_id = Schedule::where('rooms_id', $teach->class_room)
                ->where('days_id', $day->id)
                ->where('times_id', $time->id)
                ->where('type', $type)
                ->first();
            
            // 6. Cek waktu tidak available
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
    
    private $filteredClasses = [];

    public function setFilteredClasses($classes)
    {
        $this->filteredClasses = $classes;
    }

    // Di getUnscheduledTeach, filter berdasarkan kelas
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
        // CEK DULU APAKAH MUNGKIN
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
        
        // Lanjut generate seperti biasa
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
            
            \Log::info("Kromosom " . ($i + 1) . ": " . $successCount . " dari " . $totalRequiredSlots . " slot terjadwal");
        }
        
        return [];
    }
    
    private function calculateTotalRequiredSlots()
    {
        $teachs = Teach::with('course');
        
        // 🔥 TAMBAHKAN FILTER JUGA DI SINI 🔥
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
    
    public function validateMinHoursPerDay($type)
    {
        $violations = [];
        $teachs = Teach::with('course')->get();
        
        foreach ($teachs as $teach) {
            $course = $teach->course;
            $schedules = Schedule::where('type', $type)
                ->where('teachs_id', $teach->id)
                ->select('days_id', DB::raw('count(*) as total'))
                ->groupBy('days_id')
                ->get();
            
            foreach ($schedules as $sch) {
                if ($sch->total < $course->min_hours_per_day) {
                    $violations[] = [
                        'course' => $course->name,
                        'teacher' => $teach->lecturer->name,
                        'class' => $teach->room->name,
                        'day' => Day::find($sch->days_id)->name_day,
                        'actual' => $sch->total,
                        'required' => $course->min_hours_per_day
                    ];
                }
            }
        }
        
        return $violations;
    }

    public function repairSchedule($type)
    {
        $violations = $this->validateMinHoursPerDay($type);
        
        foreach ($violations as $v) {
            // Cari course berdasarkan nama
            $course = Course::where('name', $v['course'])->first();
            if (!$course) continue;
            
            // Cari teach berdasarkan course_id dan class
            $teach = Teach::where('courses_id', $course->id)
                ->whereHas('room', function($q) use ($v) {
                    $q->where('name', $v['class']);
                })
                ->whereHas('lecturer', function($q) use ($v) {
                    $q->where('name', $v['teacher']);
                })
                ->first();
            
            if ($teach) {
                // Cek apakah masih bisa tambah jam
                $currentHours = $this->getScheduledHours($type, $teach->id);
                if ($currentHours < $teach->course->hours_per_week) {
                    $this->randomingProcess($type);
                }
            }
        }
        
        return $this->validateMinHoursPerDay($type);
    }
    
}