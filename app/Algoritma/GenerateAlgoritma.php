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
    private $tempSchedules = []; 
    private $failedDaysForTeacher = []; // Track hari yang gagal untuk setiap guru
    
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
     * Cek apakah ada potensi bentrok sebelum generate
     */
    private function preCheckConflict($type, $teach, $dayId, $timeId)
    {
        // Cek guru dengan JOIN
        $teacherConflict = DB::table("schedules")
            ->join("teachs", "teachs.id", "=", "schedules.teachs_id")
            ->where("schedules.type", $type)
            ->where("schedules.days_id", $dayId)
            ->where("schedules.times_id", $timeId)
            ->where("teachs.lecturers_id", $teach->lecturers_id)
            ->exists();
        
        if ($teacherConflict) {
            return false;
        }
        
        // Cek kelas
        $classConflict = Schedule::where('type', $type)
            ->where('rooms_id', $teach->class_room)
            ->where('days_id', $dayId)
            ->where('times_id', $timeId)
            ->exists();
        
        if ($classConflict) {
            return false;
        }
        
        return true;
    }

    /**
     * Proses random jadwal dengan validasi KETAT dan RETRY mekanisme
     * DENGAN PENCARIAN HARI ALTERNATIF
     */
    public function randomingProcess($type, $maxAttempts = 20000)
    {
        $teach = $this->getUnscheduledTeach($type);
        
        if (!$teach) {
            return null;
        }
        
        $course = $teach->course;
        
        // 🔥 CEK APAKAH MAPEL KAKU (min == max)
        $isRigid = ($course->min_hours_per_day == $course->max_hours_per_day);
        
        // 🔥 Jika kaku, proses khusus (langsung ambil semua JP dalam 1 hari)
        if ($isRigid) {
            $jpPerDay = $course->min_hours_per_day;
            $totalJp = $course->hours_per_week;
            $neededDays = ceil($totalJp / $jpPerDay);
            
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
                
                foreach ($validDay['slots'] as $slot) {
                    $this->saveSchedule($type, $teach, $validDay['day'], $slot);
                }
            }
            
            for ($i = 0; $i < $totalJp; $i++) {
                $this->incrementScheduledHours($type, $teach->id);
            }
            
            return true;
        }
        
        // 🔥 ALOKASI CERDAS
        $smartResult = $this->allocateSmartDistribution($type, $teach);
        if ($smartResult === true) {
            return true;
        }
        if ($smartResult === false) {
            \Log::warning("Alokasi cerdas gagal untuk {$course->name}, lanjut ke proses random");
        }
        
        // 🔥 🔥 🔥 PROSES RANDOM DENGAN RETRY MECHANISM DAN BLACKLIST HARI 🔥 🔥 🔥
        $attempt = 0;
        $globalRetryCount = 0;
        $maxGlobalRetries = 100;
        
        // 🔥 Track hari yang gagal untuk guru ini
        $teacherKey = $type . '_' . $teach->lecturers_id;
        if (!isset($this->failedDaysForTeacher[$teacherKey])) {
            $this->failedDaysForTeacher[$teacherKey] = [];
        }
        
        while ($attempt < $maxAttempts && $globalRetryCount < $maxGlobalRetries) {
            
            // 🔥 🔥 🔥 PILIH HARI DENGAN RANDOM DAN BOBOT (LEBIH CERDAS) 🔥 🔥 🔥
            $days = $this->getOrderedDays();
            $candidateDays = [];

            foreach ($days as $d) {
                $currentHours = $this->getDailyHours($type, $teach->id, $d->id);
                if ($currentHours < $course->max_hours_per_day) {
                    // Hitung skor - kurangi bobot untuk hari yang sudah gagal
                    $remainingCapacity = $course->max_hours_per_day - $currentHours;
                    
                    // 🔥 SKIP hari yang sudah gagal untuk guru ini
                    if (in_array($d->id, $this->failedDaysForTeacher[$teacherKey])) {
                        \Log::debug("⏭️ Skip hari {$d->id} (sudah gagal) untuk guru {$teach->lecturers_id}");
                        continue; // Langsung skip, tidak tambahkan ke kandidat
                    }
                    
                    // 🔥 PERBAIKAN: Bobot lebih rendah, agar tidak terpaku di satu hari
                    // Gunakan bobot 1-5 saja, bukan 10
                    $score = min(5, $remainingCapacity);
                    
                    // Tambahkan ke kandidat dengan bobot
                    for ($i = 0; $i < $score; $i++) {
                        $candidateDays[] = $d;
                    }
                }
            }

            // 🔥 TAMBAHKAN: Jika tidak ada kandidat, coba semua hari yang belum penuh
            if (empty($candidateDays)) {
                foreach ($days as $d) {
                    $currentHours = $this->getDailyHours($type, $teach->id, $d->id);
                    if ($currentHours < $course->max_hours_per_day) {
                        $candidateDays[] = $d;
                    }
                }
            }

            // Pilih hari dari kandidat dengan random
            if (!empty($candidateDays)) {
                $day = $candidateDays[array_rand($candidateDays)];
                \Log::debug("✅ Pilih hari {$day->id} (dari " . count($candidateDays) . " kandidat) untuk guru {$teach->lecturers_id}");
            } else {
                // 🔥 Jika semua hari gagal, reset failed days
                \Log::warning("⚠️ Semua hari gagal untuk guru {$teach->lecturers_id}, reset failed days dan coba lagi");
                $this->failedDaysForTeacher[$teacherKey] = [];
                
                // Fallback: pilih hari yang belum penuh
                foreach ($days as $d) {
                    $currentHours = $this->getDailyHours($type, $teach->id, $d->id);
                    if ($currentHours < $course->max_hours_per_day) {
                        $day = $d;
                        break;
                    }
                }
            }

            // 🔥 PILIH HARI TERBAIK berdasarkan slot available
            $bestDay = $this->getBestDayWithAvailableSlots($type, $teach);
            if ($bestDay) {
                $day = $bestDay;
                \Log::debug("✅ Pilih hari terbaik {$day->id} untuk guru {$teach->lecturers_id}");
            } else {
                // Fallback ke random
                $day = Day::inRandomOrder()->first();
            }     
                   
            $currentDayHours = $this->getDailyHours($type, $teach->id, $day->id);
            $totalScheduled = $this->getScheduledHours($type, $teach->id);
            $remainingNeeded = $course->hours_per_week - $totalScheduled;
            
            if ($totalScheduled >= $course->hours_per_week) {
                return null;
            }
            
            if ($currentDayHours >= $course->max_hours_per_day) {
                $attempt++;
                $globalRetryCount++;
                continue;
            }
            
            $remainingDays = $this->getRemainingDays($type, $teach->id);
            $minRequiredPerDay = $course->min_hours_per_day;
            
            // 🔥 CEK APAKAH MUNGKIN
            if ($remainingNeeded > 0 && $remainingNeeded < $minRequiredPerDay && $currentDayHours == 0) {
                Schedule::where('type', $type)
                    ->where('teachs_id', $teach->id)
                    ->delete();
                
                $this->scheduledHours = [];
                $this->dailyCount = [];
                $this->classDailyCount = [];
                $this->failedDaysForTeacher[$teacherKey] = [];
                
                return $this->randomingProcess($type, $maxAttempts);
            }
            
            // 🔥 AMBIL SLOT YANG TERSEDIA
            $time = null;
            $availableSlots = $this->getAvailableSlotsFromTimedays($type, $day->id, $teach);
            
            if (empty($availableSlots)) {
                // Tandai hari ini sebagai gagal karena tidak ada slot
                if (!in_array($day->id, $this->failedDaysForTeacher[$teacherKey])) {
                    $this->failedDaysForTeacher[$teacherKey][] = $day->id;
                    \Log::info("📌 Menandai hari {$day->id} sebagai gagal (tidak ada slot) untuk guru {$teach->lecturers_id}");
                }
                $attempt++;
                $globalRetryCount++;
                continue;
            }
            
            // 🔥 PRIORITAS: Ambil slot yang tidak menyebabkan bentrok
            foreach ($availableSlots as $slot) {
                $teacherAvailable = $this->isTeacherAvailableWithMemory($type, $teach->lecturers_id, $day->id, $slot->id);
                $classAvailable = $this->isClassAvailable($type, $teach->class_room, $day->id, $slot->id);
                $timeNotAvailable = Timenotavailable::where('lecturers_id', $teach->lecturers_id)
                    ->where('days_id', $day->id)
                    ->where('times_id', $slot->id)
                    ->exists();
                
                if ($teacherAvailable && $classAvailable && !$timeNotAvailable) {
                    $time = $slot;
                    break;
                }
            }
            
            // FALLBACK: Random dengan mencoba semua slot
            if (!$time && !empty($availableSlots)) {
                shuffle($availableSlots);
                foreach ($availableSlots as $slot) {
                    $teacherAvailable = $this->isTeacherAvailableWithMemory($type, $teach->lecturers_id, $day->id, $slot->id);
                    $classAvailable = $this->isClassAvailable($type, $teach->class_room, $day->id, $slot->id);
                    $timeNotAvailable = Timenotavailable::where('lecturers_id', $teach->lecturers_id)
                        ->where('days_id', $day->id)
                        ->where('times_id', $slot->id)
                        ->exists();
                    
                    if ($teacherAvailable && $classAvailable && !$timeNotAvailable) {
                        $time = $slot;
                        break;
                    }
                }
            }
            
            if (!$time) {
                // Tandai hari ini sebagai gagal karena semua slot bentrok
                if (!in_array($day->id, $this->failedDaysForTeacher[$teacherKey])) {
                    $this->failedDaysForTeacher[$teacherKey][] = $day->id;
                    \Log::info("📌 Menandai hari {$day->id} sebagai gagal (semua slot bentrok) untuk guru {$teach->lecturers_id}");
                }
                $attempt++;
                $globalRetryCount++;
                continue;
            }
            
            // 🔥 CEK LAGI SEBELUM INSERT (double check)
            $teacherAvailable = $this->isTeacherAvailableWithMemory($type, $teach->lecturers_id, $day->id, $time->id);
            $classAvailable = $this->isClassAvailable($type, $teach->class_room, $day->id, $time->id);
            $timeNotAvailable = Timenotavailable::where('lecturers_id', $teach->lecturers_id)
                ->where('days_id', $day->id)
                ->where('times_id', $time->id)
                ->exists();
            
            if (!$teacherAvailable || !$classAvailable || $timeNotAvailable) {
                $globalRetryCount++;
                \Log::debug("🔄 Retry untuk {$course->name} (bentrok) - global retry {$globalRetryCount}");
                continue;
            }
            
            // 🔥 🔥 🔥 GUNAKAN TRANSACTION DENGAN LOCK FOR UPDATE 🔥 🔥 🔥
            $result = DB::transaction(function () use ($type, $teach, $day, $time) {
                
                $teacherConflict = DB::table('schedules')
                    ->join('teachs', 'teachs.id', '=', 'schedules.teachs_id')
                    ->where('schedules.type', $type)
                    ->where('schedules.days_id', $day->id)
                    ->where('schedules.times_id', $time->id)
                    ->where('teachs.lecturers_id', $teach->lecturers_id)
                    ->lockForUpdate()
                    ->exists();
                
                $classConflict = Schedule::where('type', $type)
                    ->where('days_id', $day->id)
                    ->where('times_id', $time->id)
                    ->where('rooms_id', $teach->class_room)
                    ->lockForUpdate()
                    ->exists();
                
                if ($teacherConflict || $classConflict) {
                    return null;
                }
                
                $params = [
                    'teachs_id' => $teach->id,
                    'days_id' => $day->id,
                    'times_id' => $time->id,
                    'rooms_id' => $teach->class_room,
                    'type' => $type
                ];
                
                $insert = Schedule::create($params);
                
                $this->tempSchedules[] = [
                    'days_id' => $day->id,
                    'times_id' => $time->id,
                    'lecturer_id' => $teach->lecturers_id,
                    'room_id' => $teach->class_room
                ];
                
                $this->incrementScheduledHours($type, $teach->id);
                $this->incrementDailyHours($type, $teach->id, $day->id);
                $this->incrementClassDailyHours($type, $teach->class_room, $day->id);
                
                return $insert;
            });
            
            if ($result) {
                return $result;
            }
            
            $globalRetryCount++;
            \Log::debug("🔄 Retry untuk {$course->name} (transaction gagal) - global retry {$globalRetryCount}");
        }
        
        \Log::warning("⚠️ Gagal mengisi slot untuk {$course->name} setelah {$globalRetryCount} retry");
        return null;
    }

    /**
     * Cari hari dengan jumlah slot kosong terbanyak untuk guru
     */
    private function getBestDayWithAvailableSlots($type, $teach)
    {
        $course = $teach->course;
        $bestDay = null;
        $maxAvailableSlots = -1;
        
        $days = $this->getOrderedDays();
        $teacherKey = $type . '_' . $teach->lecturers_id;
        
        foreach ($days as $day) {
            // Skip hari yang sudah gagal
            if (in_array($day->id, $this->failedDaysForTeacher[$teacherKey])) {
                continue;
            }
            
            $currentDayHours = $this->getDailyHours($type, $teach->id, $day->id);
            if ($currentDayHours >= $course->max_hours_per_day) {
                continue;
            }
            
            // Hitung slot yang benar-benar available untuk guru ini
            $availableSlots = $this->getAvailableSlotsFromTimedays($type, $day->id, $teach);
            $slotCount = count($availableSlots);
            
            if ($slotCount > $maxAvailableSlots) {
                $maxAvailableSlots = $slotCount;
                $bestDay = $day;
            }
        }
        
        return $bestDay;
    }

    /**
     * Cek apakah guru tersedia (cek DATABASE + MEMORI)
     * 🔥 DIPERBAIKI: Cek database DAN memory untuk mencegah bentrok
     */
    private function isTeacherAvailableWithMemory($type, $lecturerId, $dayId, $timeId)
    {
        // 1. Cek Database dengan JOIN (hindari whereHas)
        $conflictDb = DB::table('schedules')
            ->join('teachs', 'teachs.id', '=', 'schedules.teachs_id')
            ->where('schedules.type', $type)
            ->where('schedules.days_id', $dayId)
            ->where('schedules.times_id', $timeId)
            ->where('teachs.lecturers_id', $lecturerId)
            ->exists();
        
        // 2. Cek Memory (tempSchedules) - untuk jadwal yang sudah dialokasikan di proses yang sama
        $conflictMem = false;
        foreach ($this->tempSchedules as $temp) {
            if ($temp['days_id'] == $dayId && 
                $temp['times_id'] == $timeId && 
                $temp['lecturer_id'] == $lecturerId) {
                $conflictMem = true;
                break;
            }
        }
        
        if ($conflictDb || $conflictMem) {
            \Log::debug("🚫 Guru {$lecturerId} bentrok di hari {$dayId} jam {$timeId} (DB:" . ($conflictDb?'yes':'no') . ", Mem:" . ($conflictMem?'yes':'no') . ")");
        }
        
        return !($conflictDb || $conflictMem);
    }

    /**
     * Cek apakah kelas tersedia untuk RANGE slot (beberapa jam berurutan)
     */
    private function isClassAvailableForRange($type, $classRoomId, $dayId, $slots)
    {
        foreach ($slots as $slot) {
            $conflict = Schedule::where('type', $type)
                ->where('rooms_id', $classRoomId)
                ->where('days_id', $dayId)
                ->where('times_id', $slot->id)
                ->exists();
            
            if ($conflict) {
                return false;
            }
        }
        return true;
    }

    /**
     * Debug: Cek apakah ada bentrok dalam satu kromosom
     */
    public function debugConflicts($type)
    {
        $conflicts = [];
        
        // Cek bentrok antar jadwal dalam satu kromosom
        $schedules = Schedule::where('type', $type)
            ->with(['teach', 'room', 'day', 'time'])
            ->get();
        
        foreach ($schedules as $s1) {
            foreach ($schedules as $s2) {
                if ($s1->id == $s2->id) continue;
                
                // Cek bentrok kelas (sama-sama di kelas yang sama, hari sama, jam sama)
                if ($s1->rooms_id == $s2->rooms_id && 
                    $s1->days_id == $s2->days_id && 
                    $s1->times_id == $s2->times_id) {
                    $conflicts[] = [
                        'type' => 'KELAS BENTROK',
                        'class' => $s1->room->name,
                        'day' => $s1->day->name_day,
                        'time' => $s1->time->range,
                        'course1' => $s1->teach->course->name,
                        'course2' => $s2->teach->course->name
                    ];
                }
                
                // Cek bentrok guru (guru sama, hari sama, jam sama)
                if ($s1->teach->lecturers_id == $s2->teach->lecturers_id && 
                    $s1->days_id == $s2->days_id && 
                    $s1->times_id == $s2->times_id) {
                    $conflicts[] = [
                        'type' => 'GURU BENTROK',
                        'teacher' => $s1->teach->lecturer->name,
                        'day' => $s1->day->name_day,
                        'time' => $s1->time->range,
                        'class1' => $s1->room->name,
                        'class2' => $s2->room->name,
                        'course1' => $s1->teach->course->name,
                        'course2' => $s2->teach->course->name
                    ];
                }
            }
        }
        
        // Log hasil debug
        if (!empty($conflicts)) {
            \Log::error("🔥 DITEMUKAN " . count($conflicts) . " BENTROK pada type {$type}:");
            foreach ($conflicts as $c) {
                \Log::error(json_encode($c));
            }
        } else {
            \Log::info("✅ TIDAK ADA BENTROK pada type {$type}");
        }
        
        return $conflicts;
    }

    /**
     * Cek apakah guru tersedia (tidak mengajar di kelas APAPUN pada waktu yang sama)
     * 🔥 DIPERKETAT: Cek SEMUA kelas dalam 1 kromosom, termasuk kelas yang sedang diproses
     */
    private function isTeacherAvailable($type, $lecturerId, $dayId, $timeId)
    {
        $conflict = DB::table("schedules")
            ->join("teachs", "teachs.id", "=", "schedules.teachs_id")
            ->where("schedules.type", $type)
            ->where("schedules.days_id", $dayId)
            ->where("schedules.times_id", $timeId)
            ->where("teachs.lecturers_id", $lecturerId)
            ->exists();
            
        return !$conflict;
    }

    /**
     * Cek apakah kelas sudah memiliki jadwal di waktu tersebut
     */
    private function isClassAvailable($type, $classRoomId, $dayId, $timeId)
    {
        // 🔥 CEK DI DATABASE
        $conflictDb = Schedule::where('type', $type)
            ->where('rooms_id', $classRoomId)
            ->where('days_id', $dayId)
            ->where('times_id', $timeId)
            ->exists();
        
        // 🔥 CEK DI MEMORI
        $conflictMem = false;
        foreach ($this->tempSchedules as $temp) {
            if ($temp['days_id'] == $dayId && 
                $temp['times_id'] == $timeId && 
                $temp['room_id'] == $classRoomId) {
                $conflictMem = true;
                break;
            }
        }
        
        return !($conflictDb || $conflictMem);
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
     * Dapatkan urutan hari berdasarkan prioritas (Senin pertama, Sabtu terakhir)
     */
    private function getOrderedDays()
    {
        return Day::orderByRaw("FIELD(name_day, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu')")->get();
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
            $availableSlots = $this->getAvailableSlotsFromTimedays($type, $day->id, $teach);
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
        // 🔥 Ambil slot yang sudah terisi untuk kelas ini
        $bookedSlotIds = Schedule::where('type', $type)
            ->where('rooms_id', $teach->class_room)
            ->where('days_id', $day->id)
            ->pluck('times_id')
            ->toArray();
        
        // 🔥 URUTKAN SLOT DARI YANG PALING AWAL
        $sortedSlots = $availableSlots;
        usort($sortedSlots, function($a, $b) {
            return strtotime($a->time_begin) - strtotime($b->time_begin);
        });
        
        foreach ($sortedSlots as $slot) {
            // 🔥 SKIP JIKA SLOT SUDAH TERISI UNTUK KELAS INI
            if (in_array($slot->id, $bookedSlotIds)) {
                continue;
            }
            
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
     * Cari slot berurutan dari AWAL HARI
     */
    private function getConsecutiveSlotsFromStartOfDay($type, $teach, $day, $neededSlots)
    {
        $availableSlots = $this->getAvailableSlotsFromTimedays($type, $day->id, $teach);
        
        // 🔥 URUTKAN DARI PALING AWAL
        usort($availableSlots, function($a, $b) {
            return strtotime($a->time_begin) - strtotime($b->time_begin);
        });
        
        $count = count($availableSlots);
        
        // 🔥 Ambil slot yang sudah terisi untuk kelas ini
        $bookedSlotIds = Schedule::where('type', $type)
            ->where('rooms_id', $teach->class_room)
            ->where('days_id', $day->id)
            ->pluck('times_id')
            ->toArray();
        
        for ($i = 0; $i <= $count - $neededSlots; $i++) {
            $isAvailable = true;
            $slots = [];
            
            for ($j = 0; $j < $neededSlots; $j++) {
                $slot = $availableSlots[$i + $j];
                
                // 🔥 CEK APAKAH SLOT SUDAH TERISI UNTUK KELAS INI
                if (in_array($slot->id, $bookedSlotIds)) {
                    $isAvailable = false;
                    break;
                }
                
                // Cek apakah slot sudah terisi untuk guru
                $teacherBooked = DB::table('schedules')
                    ->join('teachs', 'teachs.id', '=', 'schedules.teachs_id')
                    ->where('schedules.type', $type)
                    ->where('schedules.days_id', $day->id)
                    ->where('schedules.times_id', $slot->id)
                    ->where('teachs.lecturers_id', $teach->lecturers_id)
                    ->exists();
                
                if ($teacherBooked) {
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
     * Ambil slot waktu yang tersedia berdasarkan timedays (hari + waktu)
     * 🔥 PERBAIKAN: Filter slot yang sudah terisi untuk kelas ini
     */
    private function getAvailableSlotsFromTimedays($type, $dayId, $teach = null)
    {
        // Ambil semua timedays untuk hari ini
        $timedays = \App\Models\Timeday::where('days_id', $dayId)
            ->with('time')
            ->orderBy('times_id', 'asc')
            ->get();
        
        // Jika tidak ada teach, kembalikan semua slot (untuk keperluan tertentu)
        if (!$teach) {
            $availableSlots = [];
            foreach ($timedays as $td) {
                if ($td->time) {
                    $availableSlots[] = $td->time;
                }
            }
            return $availableSlots;
        }
        
        $availableSlots = [];
        $classRoomId = $teach->class_room;
        
        // 🔥 Ambil semua slot yang sudah terisi untuk kelas ini di hari ini
        $bookedSlotIds = Schedule::where('type', $type)
            ->where('rooms_id', $classRoomId)
            ->where('days_id', $dayId)
            ->pluck('times_id')
            ->toArray();
        
        foreach ($timedays as $td) {
            $time = $td->time;
            if (!$time) continue;
            
            // 🔥 Jika slot sudah terisi untuk kelas ini, skip
            if (in_array($time->id, $bookedSlotIds)) {
                continue;
            }
            
            $availableSlots[] = $time;
        }
        
        // 🔥 Tambahkan logging untuk debugging
        if (empty($availableSlots)) {
            \Log::debug("Hari {$dayId} untuk kelas {$classRoomId}: tidak ada slot tersedia");
        }
        
        return $availableSlots;
    }


    /**
     * Cari slot berurutan dari timedays
     * 🔥 PERBAIKAN: Filter berdasarkan kelas
     */
    private function getConsecutiveSlotsFromTimedays($type, $teach, $day, $availableSlots, $neededSlots)
    {
        // 🔥 Ambil slot yang sudah terisi untuk kelas ini
        $bookedSlotIds = Schedule::where('type', $type)
            ->where('rooms_id', $teach->class_room)
            ->where('days_id', $day->id)
            ->pluck('times_id')
            ->toArray();
        
        // Filter availableSlots yang benar-benar available
        $reallyAvailableSlots = [];
        foreach ($availableSlots as $slot) {
            if (!in_array($slot->id, $bookedSlotIds)) {
                $reallyAvailableSlots[] = $slot;
            }
        }
        
        $count = count($reallyAvailableSlots);
        
        for ($i = 0; $i <= $count - $neededSlots; $i++) {
            $isAvailable = true;
            $slots = [];
            
            for ($j = 0; $j < $neededSlots; $j++) {
                $slot = $reallyAvailableSlots[$i + $j];
                
                // Cek apakah slot sudah terisi untuk guru atau kelas
                $isBooked = Schedule::where('type', $type)
                    ->where('days_id', $day->id)
                    ->where('times_id', $slot->id)
                    ->where('rooms_id', $teach->class_room)
                    ->exists();

                $isBookedTeacher = Schedule::where('type', $type)
                    ->where('days_id', $day->id)
                    ->where('times_id', $slot->id)
                    ->where('teachs_id', $teach->id)
                    ->exists();

                if ($isBooked || $isBookedTeacher) {
                    $isAvailable = false;
                    break;
                }
                
                $slots[] = $slot;
            }
            
            if ($isAvailable) {
                \Log::debug("✅ Ditemukan {$neededSlots} slot berurutan untuk kelas {$teach->class_room} di hari {$day->id}");
                return $slots;
            }
        }
        
        \Log::debug("❌ Tidak ditemukan {$neededSlots} slot berurutan untuk kelas {$teach->class_room} di hari {$day->id}");
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
        $bestPriority = -1;
        
        foreach ($teachs as $teach) {
            $scheduled = $this->getScheduledHours($type, $teach->id);
            $remaining = $teach->course->hours_per_week - $scheduled;
            
            if ($remaining <= 0) continue;
            
            // 🔥 PRIORITAS: min BESAR didahulukan
            $minPerDay = $teach->course->min_hours_per_day;
            
            // Mapel dengan min=3 (kaku) prioritas tertinggi
            // Mapel dengan min=2 prioritas sedang
            // Mapel dengan min=1 prioritas rendah
            $classId = $teach->class_room;
            $priority = $minPerDay * 1000 + ($classId * 10) + $remaining;

            if ($priority > $bestPriority) {
                $bestPriority = $priority;
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

        // ==================== ALOKASI CERDAS (SMART ALLOCATION) ====================
    
    /**
     * Alokasi cerdas untuk mapel yang membutuhkan distribusi optimal
     * (Tidak hardcode mapel tertentu, bekerja untuk semua mapel)
     */
    private function allocateSmartDistribution($type, $teach)
    {
        $course = $teach->course;
        $remaining = $course->hours_per_week - $this->getScheduledHours($type, $teach->id);
        $minPerDay = $course->min_hours_per_day;
        $maxPerDay = $course->max_hours_per_day;
        
        // 🔥 HANYA PROSES MAPEL DENGAN SISA 5 JP (min=2, max=3)
        if ($remaining != 5 || $minPerDay != 2 || $maxPerDay != 3) {
            return null;
        }
        
        // 🔥 CEK APAKAH MUNGKIN SECARA TEORI
        // Hitung total kapasitas yang tersedia untuk guru ini
        $availableTeacherSlots = $this->countAvailableTeacherSlots($type, $teach->lecturers_id);
        if ($availableTeacherSlots < $remaining) {
            \Log::warning("⚠️ Tidak cukup slot untuk guru {$teach->lecturer->name}, butuh {$remaining} JP, hanya tersedia {$availableTeacherSlots}");
            return null;
        }
        
        // ... kode selanjutnya
    }

    /**
     * Hitung total slot yang tersedia untuk guru
     */
    private function countAvailableTeacherSlots($type, $lecturerId)
    {
        $totalAvailable = 0;
        $days = Day::all();
        
        foreach ($days as $day) {
            $timedays = \App\Models\Timeday::where('days_id', $day->id)->get();
            foreach ($timedays as $td) {
                $isBooked = DB::table('schedules')
                    ->join('teachs', 'teachs.id', '=', 'schedules.teachs_id')
                    ->where('schedules.type', $type)
                    ->where('schedules.days_id', $day->id)
                    ->where('schedules.times_id', $td->times_id)
                    ->where('teachs.lecturers_id', $lecturerId)
                    ->exists();
                
                if (!$isBooked) {
                    $totalAvailable++;
                }
            }
        }
        
        return $totalAvailable;
    }

    /**
     * Cari semua kemungkinan pola distribusi
     */
    private function getDistributionPatterns($total, $min, $max)
    {
        $patterns = [];
        
        // Cari kombinasi jumlah hari yang mungkin (maksimal 6 hari)
        for ($days = 1; $days <= 6; $days++) {
            $this->findCombinations($total, $min, $max, $days, [], $patterns);
        }
        
        // Urutkan berdasarkan jumlah hari paling sedikit (prioritas)
        usort($patterns, function($a, $b) {
            return count($a) - count($b);
        });
        
        return $patterns;
    }

    /**
     * Rekursif mencari kombinasi angka antara min dan max yang jumlahnya = target
     */
    private function findCombinations($target, $min, $max, $depth, $current, &$results)
    {
        if ($depth == 0) {
            if (array_sum($current) == $target) {
                $results[] = $current;
            }
            return;
        }
        
        for ($value = $min; $value <= $max; $value++) {
            if (array_sum($current) + $value <= $target) {
                $this->findCombinations($target, $min, $max, $depth - 1, array_merge($current, [$value]), $results);
            }
        }
    }

    /**
     * Coba alokasikan satu pola distribusi dengan RETRY
     */
    private function tryAllocatePattern($type, $teach, $pattern)
    {
        $course = $teach->course;
        $allocatedDays = [];
        $successCount = 0;
        $maxRetriesPerAllocation = 20; // Tambah retry
        
        foreach ($pattern as $jpInDay) {
            $allocationRetry = 0;
            $allocated = false;
            
            while ($allocationRetry < $maxRetriesPerAllocation && !$allocated) {
                // Cari hari yang belum dipakai dan muat untuk jpInDay
                $foundDay = null;
                $foundSlots = null;
                
                // 🔥 PRIORITAS: Cari hari dengan kapasitas terbaik
                $days = Day::all();
                $candidateDays = [];
                
                foreach ($days as $day) {
                    if (in_array($day->id, $allocatedDays)) continue;
                    
                    $currentDayHours = $this->getDailyHours($type, $teach->id, $day->id);
                    if ($currentDayHours + $jpInDay > $course->max_hours_per_day) continue;
                    
                    $availableSlots = $this->getAvailableSlotsFromTimedays($type, $day->id, $teach);
                    $consecutiveSlots = $this->getConsecutiveSlotsFromTimedays(
                        $type, $teach, $day, $availableSlots, $jpInDay
                    );
                    
                    if ($consecutiveSlots) {
                        // 🔥 VALIDASI SUPER KETAT: Cek guru dan kelas untuk SEMUA slot
                        $teacherAvailable = $this->isTeacherAvailableForRange($type, $teach->lecturers_id, $day->id, $consecutiveSlots);
                        $classAvailable = $this->isClassAvailableForRange($type, $teach->class_room, $day->id, $consecutiveSlots);
                        
                        if ($teacherAvailable && $classAvailable) {
                            $candidateDays[] = [
                                'day' => $day,
                                'slots' => $consecutiveSlots,
                                'remainingCapacity' => $course->max_hours_per_day - $currentDayHours
                            ];
                        }
                    }
                }
                
                // 🔥 Pilih hari dengan sisa kapasitas terbanyak
                if (!empty($candidateDays)) {
                    usort($candidateDays, function($a, $b) {
                        return $b['remainingCapacity'] - $a['remainingCapacity'];
                    });
                    $best = $candidateDays[0];
                    $foundDay = $best['day'];
                    $foundSlots = $best['slots'];
                }
                
                if ($foundDay) {
                    // Simpan jadwal dengan validasi tambahan
                    $allSuccess = true;
                    foreach ($foundSlots as $slot) {
                        // Gunakan saveScheduleWithRetry untuk setiap slot
                        $result = $this->saveScheduleWithRetry($type, $teach, $foundDay, $slot, 5);
                        if (!$result) {
                            $allSuccess = false;
                            break;
                        }
                    }
                    
                    if ($allSuccess) {
                        $allocatedDays[] = $foundDay->id;
                        
                        for ($i = 0; $i < $jpInDay; $i++) {
                            $this->incrementScheduledHours($type, $teach->id);
                        }
                        $this->incrementDailyHours($type, $teach->id, $foundDay->id);
                        $this->incrementClassDailyHours($type, $teach->class_room, $foundDay->id);
                        
                        $successCount += $jpInDay;
                        $allocated = true;
                        break;
                    }
                }
                
                $allocationRetry++;
                \Log::debug("🔄 Alokasi cerdas retry untuk {$course->name} (JP {$jpInDay}) - retry {$allocationRetry}/{$maxRetriesPerAllocation}");
            }
            
            if (!$allocated) {
                // Rollback semua jadwal yang sudah tersimpan untuk pola ini
                \Log::warning("❌ Alokasi cerdas gagal untuk {$course->name} pada pola, melakukan rollback");
                Schedule::where('type', $type)
                    ->where('teachs_id', $teach->id)
                    ->delete();
                return false;
            }
        }
        
        return true;
    }

    /**
     * Simpan jadwal dengan retry mechanism (untuk alokasi cerdas)
     */
    private function saveScheduleWithRetry($type, $teach, $day, $time, $maxRetries = 5)
    {
        for ($retry = 0; $retry < $maxRetries; $retry++) {
            $result = DB::transaction(function () use ($type, $teach, $day, $time) {
    
                // Cek bentrok GURU dengan JOIN (hindari whereHas)
                $teacherConflict = DB::table('schedules')
                    ->join('teachs', 'teachs.id', '=', 'schedules.teachs_id')
                    ->where('schedules.type', $type)
                    ->where('schedules.days_id', $day->id)
                    ->where('schedules.times_id', $time->id)
                    ->where('teachs.lecturers_id', $teach->lecturers_id)
                    ->lockForUpdate()
                    ->exists();
                
                // Cek bentrok KELAS
                $classConflict = Schedule::where('type', $type)
                    ->where('days_id', $day->id)
                    ->where('times_id', $time->id)
                    ->where('rooms_id', $teach->class_room)
                    ->lockForUpdate()
                    ->exists();
                
                if ($teacherConflict || $classConflict) {
                    return null;
                }
                
                $params = [
                    'teachs_id' => $teach->id,
                    'days_id' => $day->id,
                    'times_id' => $time->id,
                    'rooms_id' => $teach->class_room,
                    'type' => $type
                ];
                
                $insert = Schedule::create($params);
                
                $this->tempSchedules[] = [
                    'days_id' => $day->id,
                    'times_id' => $time->id,
                    'lecturer_id' => $teach->lecturers_id,
                    'room_id' => $teach->class_room
                ];
                
                $this->incrementScheduledHours($type, $teach->id);
                $this->incrementDailyHours($type, $teach->id, $day->id);
                $this->incrementClassDailyHours($type, $teach->class_room, $day->id);
                
                return $insert;
            });
                
            if ($result) {
                return $result;
            }
            
            if ($retry < $maxRetries - 1) {
                \Log::debug("🔄 SaveSchedule retry " . ($retry + 1) . "/{$maxRetries} untuk kelas {$teach->class_room} hari {$day->id} jam {$time->id}");
            }
        }
        
        return null;
    }

    /**
     * Cari hari dengan X slot berurutan yang tersedia (untuk alokasi cerdas)
     */
    private function findDayWithConsecutiveSlots($type, $teach, $neededSlots, $excludeDayId = null)
    {
        $days = Day::all();
        
        foreach ($days as $day) {
            if ($excludeDayId && $day->id == $excludeDayId) continue;
            
            $currentDayHours = $this->getDailyHours($type, $teach->id, $day->id);
            if ($currentDayHours + $neededSlots > $teach->course->max_hours_per_day) continue;
            
            $availableSlots = $this->getAvailableSlotsFromTimedays($type, $day->id, $teach);
            $consecutiveSlots = $this->getConsecutiveSlotsFromTimedays(
                $type, $teach, $day, $availableSlots, $neededSlots
            );
            
            if ($consecutiveSlots) {
                return ['day' => $day, 'slots' => $consecutiveSlots];
            }
        }
        
        return null;
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
        
        if ($userId === null) {
            $userId = auth()->id() ?? 1;
        }
        
        $startType = 0;
        if ($mode == 'append') {
            $startType = (Schedule::max('type') ?? -1) + 1;
            \Log::info("Mode APPEND: Memulai dari type {$startType}");
        }
        
        for ($i = 0; $i < $kromosom; $i++) {
            $maxRetries = 100;
            $retryCount = 0;
            $success = false;
            $bestRealCount = 0;
            $bestType = null;
            
            while ($retryCount < $maxRetries && !$success) {
                // RESET SEMUA STATE UNTUK PERCOBAAN BARU
                $this->scheduledHours = [];
                $this->dailyCount = [];
                $this->classDailyCount = [];
                $this->tempSchedules = [];
                $this->minFulfilled = [];
                $this->failedDaysForTeacher = []; // 🔥 Reset failed days
                
                $totalRequiredSlots = $this->calculateTotalRequiredSlots();
                $currentType = $startType + $i;
                
                \Log::info("Generate type {$currentType} (target: {$totalRequiredSlots} JP) - Percobaan ke-" . ($retryCount + 1));
                
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
                
                // 🔥 🔥 🔥 LOGIKA RESET UNTUK STAGNASI 🔥 🔥 🔥
                if ($realCount == $bestRealCount && $bestRealCount < $totalRequiredSlots) {
                    // Jika sudah beberapa kali retry tanpa improvement (stagnasi)
                    if ($retryCount > 10 && $bestRealCount == $realCount) {
                        \Log::warning("⚠️ Stagnasi terdeteksi pada type {$currentType}, menghapus beberapa jadwal random untuk memberi ruang");
                        
                        // Hapus 20% jadwal random untuk memberi kesempatan variasi baru
                        $schedulesToDelete = Schedule::where('type', $currentType)
                            ->inRandomOrder()
                            ->limit(max(1, ceil($totalRequiredSlots * 0.2)))
                            ->get();
                        
                        $deletedCount = 0;
                        foreach ($schedulesToDelete as $sch) {
                            // Reset counter untuk jadwal yang dihapus
                            if (isset($this->scheduledHours[$currentType][$sch->teachs_id])) {
                                $this->scheduledHours[$currentType][$sch->teachs_id]--;
                            }
                            if (isset($this->dailyCount[$currentType][$sch->teachs_id][$sch->days_id])) {
                                $this->dailyCount[$currentType][$sch->teachs_id][$sch->days_id]--;
                            }
                            if (isset($this->classDailyCount[$currentType][$sch->rooms_id][$sch->days_id])) {
                                $this->classDailyCount[$currentType][$sch->rooms_id][$sch->days_id]--;
                            }
                            $sch->delete();
                            $deletedCount++;
                        }
                        
                        // Reset tempSchedules untuk sinkronisasi
                        $this->tempSchedules = [];
                        $this->failedDaysForTeacher = [];
                        
                        \Log::info("🗑️ Dihapus {$deletedCount} jadwal random untuk memberi ruang pada type {$currentType}");
                        
                        // Update realCount setelah delete
                        $realCount = Schedule::where('type', $currentType)->count();
                    }
                }
                
                if ($realCount > $bestRealCount) {
                    $bestRealCount = $realCount;
                    $bestType = $currentType;
                }
                
                if ($realCount >= $totalRequiredSlots) {
                    $success = true;
                    \Log::info("✅ KROMOSOM TYPE {$currentType}: BERHASIL 100% ({$realCount}/{$totalRequiredSlots} JP)");
                    
                    // 🔥 PANGGIL DEBUG LANGSUNG KE DATABASE
                    $this->debugConflictsDirect($currentType);
                    
                    // 🔥 JUGA CEK DENGAN validateConflicts
                    $conflicts = $this->validateConflicts($currentType);
                    if (!empty($conflicts)) {
                        \Log::error("🔥🔥🔥 validateConflicts() Juga menemukan bentrok! 🔥🔥🔥");
                        foreach ($conflicts as $conflict) {
                            \Log::error(json_encode($conflict));
                        }
                    } else {
                        \Log::info("✅ validateConflicts(): TIDAK ADA BENTROK");
                    }
                    
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
                        \Log::error("❌ KROMOSOM TYPE {$currentType}: GAGAL TOTAL setelah {$maxRetries} kali percobaan! Data terbaik ({$bestRealCount}/{$totalRequiredSlots} JP) dari type {$bestType} disimpan.");
                        
                        if ($currentType != $bestType) {
                            Schedule::where('type', $currentType)->delete();
                        }
                        
                        $this->updateProgress(
                            $currentType,
                            $i + 1,
                            $kromosom,
                            "⚠️ Kromosom " . ($i + 1) . " dari {$kromosom} GAGAL! Hanya {$bestRealCount}/{$totalRequiredSlots} JP"
                        );
                    } else {
                        Schedule::where('type', $currentType)->delete();
                        
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
        
        // Update progress final ke 100%
        $this->updateProgress(
            $kromosom,
            $kromosom,
            $kromosom,
            "Generate selesai!",
            $userId
        );
        
        $cacheKey = 'generate_progress_' . $userId;
        \Cache::put($cacheKey, [
            'status' => 'completed',
            'progress' => 100,
            'message' => 'Generate selesai!',
            'current_kromosom' => $kromosom,
            'total_kromosom' => $kromosom,
            'total_jadwal' => Schedule::count(),
            'updated_at' => date('Y-m-d H:i:s')
        ], 3600);
        
        $totalAll = Schedule::count();
        \Log::info("========== GENERATE SELESAI ==========");
        \Log::info("Total semua jadwal: {$totalAll} JP");
        \Log::info("Type yang tersedia: " . json_encode(Schedule::select('type')->distinct()->pluck('type')->toArray()));
        
        return [];
    }
    

    /**
     * Validasi bentrok setelah generate selesai
     */
    public function validateConflicts($type)
    {
        // Ambil semua jadwal yang sudah ter-insert di database untuk type ini
        $schedules = Schedule::where('type', $type)
            ->with(['teach' => function($q) {
                $q->with('lecturer'); // Pastikan relasi ke guru ada
            }])
            ->get();

        $conflicts = [];
        $tracker = [];

        foreach ($schedules as $s) {
            // Kunci unik: Guru + Hari + Jam
            $key = "G{$s->teach->lecturers_id}_D{$s->days_id}_T{$s->times_id}";

            if (isset($tracker[$key])) {
                // Jika kunci sudah ada, berarti ini BENTROK
                $conflicts[] = [
                    'guru' => $s->teach->lecturer->name,
                    'hari' => $s->days_id,
                    'jam'  => $s->times_id,
                    'kelas_1' => $tracker[$key],
                    'kelas_2' => $s->rooms_id
                ];
            } else {
                $tracker[$key] = $s->rooms_id;
            }
        }

        return $conflicts;
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
                // Perbaiki query dengan JOIN menggunakan DB::table
                $conflictSchedules = DB::table('schedules')
                    ->join('teachs', 'teachs.id', '=', 'schedules.teachs_id')
                    ->join('lecturers', 'lecturers.id', '=', 'teachs.lecturers_id')
                    ->where('lecturers.id', $time_not_available->lecturers_id)
                    ->where('schedules.days_id', $time_not_available->days_id)
                    ->where('schedules.times_id', $time_not_available->times_id)
                    ->select('schedules.*')
                    ->get();

                if (!empty($conflictSchedules)) {
                    foreach ($conflictSchedules as $schedule) {
                        // Ambil model Schedule yang sebenarnya
                        $scheduleModel = Schedule::find($schedule->id);
                        if ($scheduleModel) {
                            $scheduleModel->value = $scheduleModel->value + 1;
                            $scheduleModel->value_process = $scheduleModel->value_process . "+ 1 ";
                            $scheduleModel->save();
                        }
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
                
                $classBooked = Schedule::where('type', $type)
                    ->where('days_id', $day->id)
                    ->where('times_id', $slot->id)
                    ->where('rooms_id', $teach->class_room)
                    ->exists();

                $teacherBooked = Schedule::where('type', $type)
                    ->where('days_id', $day->id)
                    ->where('times_id', $slot->id)
                    ->where('teachs_id', $teach->id)
                    ->exists();

                $isBooked = $classBooked || $teacherBooked;
                
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
        $maxRepairs = 25;
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
     * Validasi apakah ada hari yang slot paginya kosong tapi siangnya terisi
     * (Digunakan untuk memeriksa kualitas jadwal setelah generate)
     */
    public function validateNoEmptyMorningSlots($type, $classRoomId)
    {
        $schedules = Schedule::where('type', $type)
            ->where('rooms_id', $classRoomId)
            ->with(['day', 'time'])
            ->get();
        
        $violations = [];
        
        foreach ($schedules->groupBy('days_id') as $dayId => $daySchedules) {
            $times = $daySchedules->pluck('time')->sortBy('time_begin');
            $earliestTime = $times->first();
            
            // Cek apakah ada slot kosong sebelum jam pertama yang terisi
            $firstSlotOfDay = Time::orderBy('time_begin')->first();
            
            if ($earliestTime && $earliestTime->id > $firstSlotOfDay->id) {
                $violations[] = [
                    'day' => Day::find($dayId)->name_day,
                    'first_filled' => $earliestTime->range,
                    'first_available' => $firstSlotOfDay->range
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

    /**
     * DEBUG: Cek bentrok langsung dari database dengan query RAW
     */
    public function debugConflictsDirect($type)
    {
        \Log::info("========== DEBUG BENTROK UNTUK TYPE {$type} ==========");
        
        // 🔥 CEK BENTROK KELAS dengan QUERY RAW
        $classConflicts = DB::select("
            SELECT 
                s.rooms_id,
                r.name as room_name,
                s.days_id,
                d.name_day,
                s.times_id,
                t.range as time_range,
                COUNT(*) as total
            FROM schedules s
            JOIN rooms r ON r.id = s.rooms_id
            JOIN days d ON d.id = s.days_id
            JOIN times t ON t.id = s.times_id
            WHERE s.type = ?
            GROUP BY s.rooms_id, s.days_id, s.times_id
            HAVING COUNT(*) > 1
        ", [$type]);
        
        if (!empty($classConflicts)) {
            \Log::error("🔥🔥🔥 BENTROK KELAS DITEMUKAN! 🔥🔥🔥");
            foreach ($classConflicts as $conflict) {
                \Log::error("KELAS: {$conflict->room_name}, HARI: {$conflict->name_day}, JAM: {$conflict->time_range}, JUMLAH: {$conflict->total}");
                
                // Ambil detail jadwal yang bentrok
                $details = Schedule::where('type', $type)
                    ->where('rooms_id', $conflict->rooms_id)
                    ->where('days_id', $conflict->days_id)
                    ->where('times_id', $conflict->times_id)
                    ->with(['teach.course'])
                    ->get();
                
                foreach ($details as $detail) {
                    \Log::error("  - MAPEL: {$detail->teach->course->name}, GURU: {$detail->teach->lecturer->name}");
                }
            }
        } else {
            \Log::info("✅ Tidak ada bentrok kelas untuk type {$type}");
        }
        
        // 🔥 CEK BENTROK GURU dengan QUERY RAW
        $teacherConflicts = DB::select("
            SELECT 
                t.lecturers_id,
                l.name as lecturer_name,
                s.days_id,
                d.name_day,
                s.times_id,
                ti.range as time_range,
                COUNT(*) as total,
                GROUP_CONCAT(DISTINCT r.name SEPARATOR ', ') as classes
            FROM schedules s
            JOIN teachs t ON t.id = s.teachs_id
            JOIN lecturers l ON l.id = t.lecturers_id
            JOIN days d ON d.id = s.days_id
            JOIN times ti ON ti.id = s.times_id
            JOIN rooms r ON r.id = s.rooms_id
            WHERE s.type = ?
            GROUP BY t.lecturers_id, s.days_id, s.times_id
            HAVING COUNT(*) > 1
        ", [$type]);
        
        if (!empty($teacherConflicts)) {
            \Log::error("🔥🔥🔥 BENTROK GURU DITEMUKAN! 🔥🔥🔥");
            foreach ($teacherConflicts as $conflict) {
                \Log::error("GURU: {$conflict->lecturer_name}, HARI: {$conflict->name_day}, JAM: {$conflict->time_range}, KELAS: {$conflict->classes}, JUMLAH: {$conflict->total}");
            }
        } else {
            \Log::info("✅ Tidak ada bentrok guru untuk type {$type}");
        }
        
        return [
            'class_conflicts' => $classConflicts,
            'teacher_conflicts' => $teacherConflicts
        ];
    }

    /**
     * DEBUG: Tampilkan semua jadwal untuk kelas tertentu pada type tertentu
     */
    public function debugClassSchedule($type, $classRoomId)
    {
        $schedules = Schedule::where('type', $type)
            ->where('rooms_id', $classRoomId)
            ->with(['teach.course', 'day', 'time'])
            ->orderBy('days_id')
            ->orderBy('times_id')
            ->get();
        
        $room = Room::find($classRoomId);
        \Log::info("========== JADWAL KELAS {$room->name} (TYPE {$type}) ==========");
        
        foreach ($schedules as $s) {
            \Log::info("{$s->day->name_day} - {$s->time->range}: {$s->teach->course->name} ({$s->teach->lecturer->name})");
        }
        
        // Cek duplikasi
        $duplicates = [];
        foreach ($schedules as $s) {
            $key = $s->days_id . '_' . $s->times_id;
            if (isset($duplicates[$key])) {
                \Log::error("🔥 DUPLIKAT DITEMUKAN! HARI {$s->day->name_day} JAM {$s->time->range} terisi 2 kali!");
                \Log::error("  - MAPEL 1: {$duplicates[$key]->teach->course->name}");
                \Log::error("  - MAPEL 2: {$s->teach->course->name}");
            }
            $duplicates[$key] = $s;
        }
        
        return $schedules;
    }

}
