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
    public function randomingProcess($type, $maxAttempts = 10000)
    {
        $teach = $this->getUnscheduledTeach($type);
        
        if (!$teach) {
            return null;
        }
        
        $course = $teach->course;
        // 🔥 TAMBAHKAN INI: DETEKSI MAPEL KAKU (min == max)
        $isRigid = ($course->min_hours_per_day == $course->max_hours_per_day);
        
        // 🔥 Jika kaku, proses khusus (langsung ambil semua JP dalam 1 hari)
        if ($isRigid) {
            $jpPerDay = $course->min_hours_per_day;
            $totalJp = $course->hours_per_week;
            $neededDays = ceil($totalJp / $jpPerDay);
            
            // Cek apakah total JP kelipatan dari jpPerDay
            if ($totalJp % $jpPerDay != 0) {
                \Log::error("❌ Mapel kaku {$course->name}: {$totalJp} JP tidak kelipatan {$jpPerDay}");
                return null;
            }
            
            for ($d = 0; $d < $neededDays; $d++) {
                $validDay = $this->getValidDayForMin($type, $teach, $jpPerDay);
                
                if (!$validDay) {
                    \Log::warning("Tidak ada hari yang cukup untuk mapel kaku {$course->name}");
                    return null;
                }
                
                // Simpan semua slot sekaligus
                foreach ($validDay['slots'] as $slot) {
                    $this->saveSchedule($type, $teach, $validDay['day'], $slot);
                }
            }
            
            // Update scheduled hours (total JP)
            for ($i = 0; $i < $totalJp; $i++) {
                $this->incrementScheduledHours($type, $teach->id);
            }
            
            return true;
        }
        
        $attempt = 0;
        
        while ($attempt < $maxAttempts) {
            // 🔥 DEFINISIKAN $day DI AWAL LOOP
            $day = Day::inRandomOrder()->first();
            
            $currentDayHours = $this->getDailyHours($type, $teach->id, $day->id);
            $totalScheduled = $this->getScheduledHours($type, $teach->id);
            $remainingNeeded = $course->hours_per_week - $totalScheduled;
            
            // Jika sudah mencapai target, stop
            if ($totalScheduled >= $course->hours_per_week) {
                return null;
            }
            
            // MAX PER HARI
            if ($currentDayHours >= $course->max_hours_per_day) {
                $attempt++;
                continue;
            }
            
            // CEK KEMUNGKINAN
            $remainingDays = $this->getRemainingDays($type, $teach->id);
            $minRequiredPerDay = $course->min_hours_per_day;
            
            // 🔥 PERBAIKAN: pakai $course->max_hours_per_day
            $daysLeftIfSkip = $remainingDays - 1;
            $maxPossibleWithSkip = $daysLeftIfSkip * $course->max_hours_per_day;
            
            // Jika skip hari ini mengakibatkan tidak mungkin mencapai target
            if ($remainingNeeded > $maxPossibleWithSkip && $currentDayHours == 0) {
                $attempt--; // tidak dihitung sebagai attempt
            }
            
            // Jika sisa kebutuhan < min_per_hari
            if ($remainingNeeded > 0 && $remainingNeeded < $minRequiredPerDay && $currentDayHours == 0) {
                
                Schedule::where('type', $type)
                    ->where('teachs_id', $teach->id)
                    ->delete();
                
                $this->scheduledHours = [];
                $this->dailyCount = [];
                $this->classDailyCount = [];
                
                return $this->randomingProcess($type, $maxAttempts);
            }
            
            $time = null;
            
            // AMBIL SLOT DARI TIMEDAYS
            $availableSlots = $this->getAvailableSlotsFromTimedays($type, $day->id);
            
            if (empty($availableSlots)) {
                $attempt++;
                continue;
            }
            
            // CEK SUDAH ADA JADWAL DI HARI INI
            $existingInDay = Schedule::where('type', $type)
                ->where('teachs_id', $teach->id)
                ->where('days_id', $day->id)
                ->exists();
            
            if ($existingInDay) {
                $lastSlot = Schedule::where('type', $type)
                    ->where('teachs_id', $teach->id)
                    ->where('days_id', $day->id)
                    ->orderBy('times_id', 'desc')
                    ->first();
                
                if ($lastSlot) {
                    foreach ($availableSlots as $slot) {
                        if ($slot->id > $lastSlot->times_id) {
                            $time = $slot;
                            break;
                        }
                    }
                }
            }
            
            // JIKA BELUM ADA JADWAL DI HARI INI
            if (!$time && $currentDayHours == 0) {
                if ($remainingNeeded >= $minRequiredPerDay) {
                    $consecutiveSlots = $this->getConsecutiveSlotsFromTimedays($type, $teach, $day, $availableSlots, $minRequiredPerDay);
                    if ($consecutiveSlots) {
                        $time = $consecutiveSlots[0];
                    }
                } else {
                    if ($remainingNeeded > 0) {
                        $consecutiveSlots = $this->getConsecutiveSlotsFromTimedays($type, $teach, $day, $availableSlots, $remainingNeeded);
                        if ($consecutiveSlots) {
                            $time = $consecutiveSlots[0];
                        }
                    }
                }
            }
            
            // PRIORITAS: Ambil slot paling awal
            if (!$time && !empty($availableSlots)) {
                $time = $this->getEarliestAvailableSlot($type, $teach, $day, $availableSlots);
            }
            
            // FALLBACK: Random
            if (!$time && !empty($availableSlots)) {
                $time = $availableSlots[array_rand($availableSlots)];
            }
            
            if (!$time) {
                $attempt++;
                continue;
            }
            
            // VALIDASI BENTROK
            $check_lecturers_id = Schedule::join('teachs', 'teachs.id', '=', 'schedules.teachs_id')
                ->join('lecturers', 'lecturers.id', '=', 'teachs.lecturers_id')
                ->where('lecturers_id', $teach->lecturers_id)
                ->where('days_id', $day->id)
                ->where('times_id', $time->id)
                ->where('type', $type)
                ->first();
            
            $check_class_id = Schedule::where('rooms_id', $teach->class_room)
                ->where('days_id', $day->id)
                ->where('times_id', $time->id)
                ->where('type', $type)
                ->first();
            
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
     * Hitung jumlah hari yang TERSISA (belum ada jadwal sama sekali)
     */
    private function getRemainingDays($type, $teachId)
    {
        $usedDays = Schedule::where('type', $type)
            ->where('teachs_id', $teachId)
            ->groupBy('days_id')
            ->pluck('days_id')
            ->toArray();
        
        $allDays = DB::table('days')->pluck('id')->toArray();
        $remainingDays = array_diff($allDays, $usedDays);
        
        return count($remainingDays);
    }

    /**
     * Cari hari yang bisa menampung minRequiredPerDay sekaligus
     */
    private function getValidDayForMin($type, $teach, $minRequiredPerDay)
    {
        $course = $teach->course;
        $maxAttempts = 50;
        
        for ($i = 0; $i < $maxAttempts; $i++) {
            $day = Day::inRandomOrder()->first();
            $currentDayHours = $this->getDailyHours($type, $teach->id, $day->id);
            
            // Cek apakah hari ini masih bisa menampung minRequiredPerDay
            if ($currentDayHours + $minRequiredPerDay > $course->max_hours_per_day) {
                continue;
            }
            
            // Cek apakah ada slot berurutan sepanjang minRequiredPerDay
            $availableSlots = $this->getAvailableSlotsFromTimedays($type, $day->id);
            $consecutiveSlots = $this->getConsecutiveSlotsFromTimedays($type, $teach, $day, $availableSlots, $minRequiredPerDay);
            
            if ($consecutiveSlots) {
                return ['day' => $day, 'slots' => $consecutiveSlots];
            }
        }
        
        return null;
    }

    /**
     * Ambil slot paling awal yang tersedia untuk hari ini
     */
    private function getEarliestAvailableSlot($type, $teach, $day, $availableSlots)
    {
        foreach ($availableSlots as $slot) {
            // Cek bentrok guru
            $teacherConflict = Schedule::join('teachs', 'teachs.id', '=', 'schedules.teachs_id')
                ->join('lecturers', 'lecturers.id', '=', 'teachs.lecturers_id')
                ->where('lecturers_id', $teach->lecturers_id)
                ->where('days_id', $day->id)
                ->where('times_id', $slot->id)
                ->where('type', $type)
                ->exists();
            
            // Cek bentrok kelas
            $classConflict = Schedule::where('rooms_id', $teach->class_room)
                ->where('days_id', $day->id)
                ->where('times_id', $slot->id)
                ->where('type', $type)
                ->exists();
            
            // Cek waktu tidak available
            $timeNotAvailable = Timenotavailable::where('lecturers_id', $teach->lecturers_id)
                ->where('days_id', $day->id)
                ->where('times_id', $slot->id)
                ->exists();
            
            if (!$teacherConflict && !$classConflict && !$timeNotAvailable) {
                return $slot;
            }
        }
        
        return null;
    }

    /**
     * Ambil slot waktu yang tersedia berdasarkan timedays (hari + waktu)
     */
    private function getAvailableSlotsFromTimedays($type, $dayId)
    {
        // Ambil semua timedays untuk hari ini
        $timedays = \App\Models\Timeday::where('days_id', $dayId)
            ->with('time')
            ->orderBy('times_id', 'asc')
            ->get();
        
        $availableSlots = [];
        
        foreach ($timedays as $td) {
            $time = $td->time;
            if (!$time) continue;
            
            // Cek apakah slot ini sudah terisi penuh untuk kelas ini?
            // (tidak perlu cek di sini, nanti divalidasi di bentrok)
            $availableSlots[] = $time;
        }
        
        return $availableSlots;
    }

    /**
     * Cari slot berurutan dari timedays
     */
    private function getConsecutiveSlotsFromTimedays($type, $teach, $day, $availableSlots, $neededSlots)
    {
        $count = count($availableSlots);
        
        for ($i = 0; $i <= $count - $neededSlots; $i++) {
            $isAvailable = true;
            $slots = [];
            
            for ($j = 0; $j < $neededSlots; $j++) {
                $slot = $availableSlots[$i + $j];
                
                // Cek apakah slot sudah terisi untuk guru atau kelas
                $isBooked = Schedule::where('type', $type)
                    ->where('days_id', $day->id)
                    ->where('times_id', $slot->id)
                    ->where(function($q) use ($teach) {
                        $q->where('rooms_id', $teach->class_room)
                        ->orWhere('teachs_id', $teach->id);
                    })
                    ->exists();
                
                if ($isBooked) {
                    $isAvailable = false;
                    break;
                }
                
                $slots[] = $slot;
            }
            
            if ($isAvailable) {
                return $slots;
            }
        }
        
        return null;
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
    
    public function randKromosom($kromosom, $count_teachs, $mode = 'replace_all', $userId = null)
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
        
        // 🔥 JIKA userId TIDAK DIKIRIM, AMBIL DARI AUTH
        if ($userId === null) {
            $userId = auth()->id() ?? 1;
        }
        
        // 🔥 TENTUKAN TYPE AWAL BERDASARKAN MODE
        $startType = 0;
        if ($mode == 'append') {
            $startType = (Schedule::max('type') ?? -1) + 1;
            \Log::info("Mode APPEND: Memulai dari type {$startType}");
        }
        
        for ($i = 0; $i < $kromosom; $i++) {
            $maxRetries = 5;
            $retryCount = 0;
            $success = false;
            $bestRealCount = 0;
            
            while ($retryCount < $maxRetries && !$success) {
                $this->scheduledHours = [];
                $this->dailyCount = [];
                $this->classDailyCount = [];
                
                $totalRequiredSlots = $this->calculateTotalRequiredSlots();
                $currentType = $startType + $i;
                
                \Log::info("Generate type {$currentType} (target: {$totalRequiredSlots} JP) - Percobaan ke-" . ($retryCount + 1));
                
                // 🔥 UPDATE PROGRESS: Memulai generate kromosom
                $this->updateProgress(
                    $currentType,
                    $i + 1,
                    $kromosom,
                "Memulai generate kromosom " . ($i + 1) . " dari {$kromosom}", 
                $userId
                );
                
                // Hapus data lama untuk percobaan ini
                Schedule::where('type', $currentType)->delete();
                
                $successCount = 0;
                for ($j = 0; $j < $totalRequiredSlots; $j++) {
                    $result = $this->randomingProcess($currentType);
                    if ($result) {
                        $successCount++;
                        
                        // 🔥 UPDATE PROGRESS setiap 5 slot (agar tidak terlalu sering)
                        if ($j % 5 == 0 || $j == $totalRequiredSlots - 1) {
                            $slotProgress = round(($j + 1) / $totalRequiredSlots * 100);
                            $this->updateProgress(
                                $currentType,
                                $i + 1,
                                $kromosom,
                                "Kromosom " . ($i + 1) . " dari {$kromosom}: mengisi slot " . ($j + 1) . "/{$totalRequiredSlots} ({$slotProgress}%)"
                            );
                        }
                    } else {
                        \Log::warning("Gagal mengisi slot ke-" . ($j+1) . " untuk type {$currentType}");
                        break;
                    }
                }
                
                $this->repairMinHours($currentType);
                
                $realCount = Schedule::where('type', $currentType)->count();
                $percentage = $totalRequiredSlots > 0 ? round(($realCount / $totalRequiredSlots) * 100) : 0;
                
                // 🔥 SIMPAN DATA TERBAIK
                if ($realCount > $bestRealCount) {
                    $bestRealCount = $realCount;
                }
                
                if ($realCount >= $totalRequiredSlots) {
                    $success = true;
                    \Log::info("✅ KROMOSOM TYPE {$currentType}: BERHASIL 100% ({$realCount}/{$totalRequiredSlots} JP)");
                    
                    // 🔥 UPDATE PROGRESS: Kromosom selesai 100%
                    $this->updateProgress(
                        $currentType,
                        $i + 1,
                        $kromosom,
                        "✅ Kromosom " . ($i + 1) . " dari {$kromosom} SELESAI 100%"
                    );
                } else {
                    $retryCount++;
                    \Log::warning("⚠️ KROMOSOM TYPE {$currentType}: GAGAL! Hanya {$realCount}/{$totalRequiredSlots} JP ({$percentage}%). Retry {$retryCount}/{$maxRetries}");
                    
                    if ($retryCount >= $maxRetries) {
                        \Log::error("❌ KROMOSOM TYPE {$currentType}: GAGAL TOTAL setelah {$maxRetries} kali percobaan! Data terakhir ({$bestRealCount}/{$totalRequiredSlots} JP) tetap disimpan.");
                        
                        // 🔥 UPDATE PROGRESS: Kromosom gagal total
                        $this->updateProgress(
                            $currentType,
                            $i + 1,
                            $kromosom,
                            "⚠️ Kromosom " . ($i + 1) . " dari {$kromosom} GAGAL! Hanya {$bestRealCount}/{$totalRequiredSlots} JP"
                        );
                    } else {
                        // Hapus data yang gagal hanya jika masih ada percobaan tersisa
                        Schedule::where('type', $currentType)->delete();
                        
                        // 🔥 UPDATE PROGRESS: Akan retry
                        $this->updateProgress(
                            $currentType,
                            $i + 1,
                            $kromosom,
                            "⚠️ Kromosom " . ($i + 1) . " gagal (retry {$retryCount}/{$maxRetries})"
                        );
                    }
                }
            }
        }
        
        // 🔥 UPDATE PROGRESS: Ringkasan akhir
        $this->updateProgress(
            0,
            $kromosom,
            $kromosom,
            "Finalisasi jadwal..."
        );
        
        // Ringkasan akhir
        $totalAll = Schedule::count();
        \Log::info("========== GENERATE SELESAI ==========");
        \Log::info("Total semua jadwal: {$totalAll} JP");
        \Log::info("Type yang tersedia: " . json_encode(Schedule::select('type')->distinct()->pluck('type')->toArray()));
        
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
        // 🔥 AMBIL DARI TIMEDAYS, BUKAN DARI TIME
        $timedays = \App\Models\Timeday::where('days_id', $day->id)
            ->with('time')
            ->orderBy('time_begin')
            ->get();
        
        $times = $timedays->pluck('time');
        
        for ($i = 0; $i <= $times->count() - $neededSlots; $i++) {
            $isAvailable = true;
            $slots = [];
            
            for ($j = 0; $j < $neededSlots; $j++) {
                $checkTime = $times[$i + $j];
                
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

    /**
     * Validasi akhir apakah semua target tercapai
     */
    public function validateAllTargets($type)
    {
        $teachs = Teach::with('course');
        
        if (!empty($this->filteredClasses)) {
            $teachs = $teachs->whereIn('class_room', $this->filteredClasses);
        }
        
        $teachs = $teachs->get();
        $violations = [];
        
        foreach ($teachs as $teach) {
            $scheduled = Schedule::where('type', $type)
                ->where('teachs_id', $teach->id)
                ->count();
            
            $target = $teach->course->hours_per_week;
            
            if ($scheduled != $target) {
                $violations[] = [
                    'course' => $teach->course->name,
                    'teacher' => $teach->lecturer->name,
                    'class' => $teach->room->name,
                    'target' => $target,
                    'actual' => $scheduled,
                    'difference' => $target - $scheduled
                ];
            }
        }
        
        return $violations;
    }
    
    /**
     * Simpan jadwal ke database
     */
    private function saveSchedule($type, $teach, $day, $time)
    {
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

    // Di GenerateAlgoritma.php, tambahkan method untuk update progress
    // Ubah method updateProgress
    private function updateProgress($type, $current, $total, $message, $userId = null)
    {
        if ($userId === null) {
            $userId = auth()->id() ?? 1;
        }
        
        $progressPercent = round(($current / $total) * 100);
        $progressPercent = min($progressPercent, 99);
        
        $data = [
            'status' => 'processing',
            'progress' => $progressPercent,
            'message' => $message,
            'current_kromosom' => $current,
            'total_kromosom' => $total,
            'total_jadwal' => Schedule::count(),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        $cacheKey = 'generate_progress_' . $userId;
        \Cache::put($cacheKey, $data, 3600);
    }

}
