<?php namespace App\Http\Controllers\Admin;

use App\Algoritma\GenerateAlgoritma;
use App\Http\Controllers\Controller;
use App\Models\Lecturer;
use App\Models\Schedule;
use App\Models\Room;
use App\Models\Day;
use App\Models\Time;
use App\Models\Setting;
use App\Models\Teach;
use Excel;
use Illuminate\Http\Request;
// use Symfony\Component\HttpFoundation\Session\Session;

class GenetikController extends Controller
{
    public function index(Request $request)
    {
        return view('admin-news.genetik.index');
    }

    public function submit(Request $request)
    {
        set_time_limit(3600);
        ini_set('memory_limit', '1024M');
        
        $mode = $request->input('mode', 'append');
        $selectedClasses = $request->input('classes', []);
        
        // Simpan ke session untuk notifikasi
        session(['selected_classes' => $selectedClasses]);
        
        // 🔥 3 MODE BERBEDA 🔥
        if ($mode == 'replace_all') {
            // Mode 3: Hapus SEMUA jadwal
            Schedule::truncate();
            \Log::info('Mode: RESET ALL - Semua jadwal dihapus');
            
        } elseif ($mode == 'replace_filter') {
            // Mode 2: Hapus hanya kelas yang dipilih
            if (!empty($selectedClasses)) {
                Schedule::whereIn('rooms_id', $selectedClasses)->delete();
                \Log::info('Mode: REPLACE FILTER - Hapus jadwal untuk kelas: ' . implode(',', $selectedClasses));
            } else {
                // Jika tidak ada kelas dipilih, behave like replace_all
                Schedule::truncate();
                \Log::info('Mode: REPLACE FILTER - Tidak ada kelas dipilih, hapus semua');
            }
            
        } else {
            // Mode 1: Append (Tambah) - tidak hapus apapun
            \Log::info('Mode: APPEND - Menambah ke jadwal yang sudah ada');
        }
        
        $input_kromosom   = $request->input('kromosom');
        $input_generasi   = $request->input('generasi');
        $input_crossover  = $request->input('crossover');
        $input_mutasi     = $request->input('mutasi');
        
        $count_teachs     = Teach::count();
        $kromosom         = $input_kromosom * $input_generasi;
        $crossover        = $input_kromosom * $input_crossover;
        
        $generate = new GenerateAlgoritma;
        
        try {
            // Jika ada kelas yang dipilih, set filter
            if (!empty($selectedClasses)) {
                $generate->setFilteredClasses($selectedClasses);
            }
            $generate->randKromosom($kromosom, $count_teachs);
        } catch (\Exception $e) {
            return redirect()->route('admin.generates')->with('danger', $e->getMessage());
        }
        
        $generate->checkPinalty();
              
        $total_gen = Setting::firstOrNew(['key' => 'total_gen']);
        $total_gen->name = 'Total Gen';
        $total_gen->value = $crossover;
        $total_gen->save();
        
        $mutasi_setting = Setting::firstOrNew(['key' => 'mutasi']);
        $mutasi_setting->name = 'Mutasi';
        $mutasi_setting->value = (3 * $count_teachs) * $input_kromosom * $input_mutasi;
        $mutasi_setting->save();
        
        return redirect()->route('admin.generates.result', 1)->with('success', "✅ Generate selesai! Total jadwal: " . Schedule::count());
    }

    public function delete($id)
    {   
        $deleted = Schedule::where('type', $id)->delete();
        
        if ($deleted > 0) {
            // Redirect ke halaman result dengan ID yang sama
            return redirect()->route('admin.generates.result', $id)->with('success', "✅ {$deleted} jadwal berhasil dihapus!");
        } else {
            return redirect()->route('admin.generates.result', $id)->with('danger', "⚠️ Tidak ada jadwal dengan ID tersebut!");
        }
    }

    public function result($id, Request $request)
    {
        // Cek apakah ada schedule sama sekali
        $totalSchedules = Schedule::count();
        
        if ($totalSchedules == 0) {
            return redirect()->route('admin.generates')->with('danger', '⚠️ Belum ada jadwal. Silakan generate terlebih dahulu!');
        }
        
        // Ambil SEMUA type yang ada di database
        $availableTypes = Schedule::select('type')->groupBy('type')->orderBy('type', 'asc')->pluck('type')->toArray();
        $kromosom = count($availableTypes);
        
        $crossover = Setting::where('key', Setting::CROSSOVER)->first();
        $mutasi = Setting::where('key', Setting::MUTASI)->first();
        $value_schedule = Schedule::where('type', $id)->first();
        
        // Jika type yang diminta tidak ada, redirect ke type pertama yang tersedia
        if (empty($value_schedule) && !empty($availableTypes)) {
            return redirect()->route('admin.generates.result', $availableTypes[0]);
        }
        
        // Ambil parameter filter kelas dari URL
        $filterClass = $request->input('class');
        
        // Query jadwal untuk list view
        $query = Schedule::join('teachs', 'teachs.id', '=', 'schedules.teachs_id')
            ->join('lecturers', 'lecturers.id', '=', 'teachs.lecturers_id')
            ->join('days', 'days.id', '=', 'schedules.days_id')
            ->join('times', 'times.id', '=', 'schedules.times_id')
            ->join('rooms', 'rooms.id', '=', 'schedules.rooms_id')
            ->orderByRaw("FIELD(days.name_day, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu')")
            ->orderBy('times.time_begin', 'asc')
            ->where('schedules.type', $id)
            ->select('schedules.*');
        
        // Jika ada filter kelas
        if (!empty($filterClass)) {
            $query->where('rooms.name', $filterClass);
        }
        
        $schedules = $query->paginate(100);
        
        // Data untuk filter kelas
        $classCounts = Schedule::where('schedules.type', $id)
            ->join('rooms', 'rooms.id', '=', 'schedules.rooms_id')
            ->select('rooms.name', \DB::raw('count(*) as total'))
            ->groupBy('rooms.name')
            ->orderBy('rooms.name')
            ->get();

        // Data untuk matriks view (hanya jika filter kelas aktif)
        $days = Day::orderByRaw("FIELD(name_day, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu')")->get();
        $times = Time::orderBy('time_begin')->get();
        
        $scheduleMatrix = [];
        if (!empty($filterClass)) {
            $room = Room::where('name', $filterClass)->first();
            if ($room) {
                $schedulesRaw = Schedule::where('schedules.type', $id)
                    ->where('rooms_id', $room->id)
                    ->with(['teach.course', 'teach.lecturer', 'day', 'time'])
                    ->get();
                
                foreach ($schedulesRaw as $s) {
                    $scheduleMatrix[$s->day->name_day][$s->time->range] = [
                        'course' => $s->teach->course->name,
                        'teacher' => $s->teach->lecturer->name
                    ];
                }
            }
        }

        // Buat data_kromosom berdasarkan type yang ADA (bukan dari 1)
        $data_kromosom = [];
        foreach ($availableTypes as $type) {
            $value_schedules = Schedule::where('type', $type)->first();
            $data_kromosom[] = [
                'type' => $type,
                'value_schedules' => $value_schedules->value ?? 0,
            ];
        }

        return view('admin-news.genetik.result', compact(
            'schedules', 'data_kromosom', 'id', 'value_schedule', 
            'crossover', 'mutasi', 'filterClass', 'classCounts',
            'days', 'times', 'scheduleMatrix'
        ));
    }

    // HAPUS METHOD resultMatrix() - TIDAK DIPAKAI LAGI

    public function excel($id)
    {
        $schedules = Schedule::orderBy('days_id', 'desc')
            ->orderBy('times_id', 'desc')
            ->where('type', $id)
            ->get();

        return Excel::create('Algoritma Genetika', function ($excel) use ($schedules)
        {
            $excel->sheet('Genetika', function ($sheet) use ($schedules)
            {
                $sheet->loadView('admin-news.genetik.export')->with('schedules', $schedules);
            });
        })->export('xlsx');

        return redirect()->back();
    }

    public function showClasses($id)
    {
        // $years          = Teach::select('year')->groupBy('year')->pluck('year', 'year');
        $classes        = Teach::select('class_room')->groupBy('class_room')->havingRaw('COUNT(*) > 1')->get();
        $kromosom       = Schedule::select('type')->groupBy('type')->get()->count();
        $crossover      = Setting::where('key', Setting::CROSSOVER)->first();
        $mutasi         = Setting::where('key', Setting::MUTASI)->first();
        $value_schedule = Schedule::where('type', $id)->first();

        $lecturer       = Lecturer::select('id', 'name')->get();
        $rooms          = Room::select('id', 'name')->get();

        $schedule      = Schedule::orderBy('days_id', 'desc')
            ->orderBy('times_id', 'desc')
            ->where('type', $id)
            ->select(
                'schedules.id',
                'schedules.type',
                'schedules.teachs_id',
                'schedules.days_id',
                'schedules.times_id',
                'schedules.rooms_id',
                'schedules.value',
                'schedules.value_process'
                )
            ->get();

        if (empty($value_schedule))
        {
            abort(404);
        }

        for ($i = 1; $i <= $kromosom; $i++)
        {
            $value_schedules = Schedule::where('type', $i)->first();
            $data_kromosom[] = [
                'value_schedules' => $value_schedules->value,
            ];
        }

        $days       = Day::select('name_day')->get();
        $times      = Time::select('range')->get();

        $schedules  = [];

        foreach ($schedule as $s) {
            // $schedules[] = $s->time->range;
            foreach ($times as $t) {
                // $schedules[] = $s->time->range;
                if($s->time->range == $t->range){
                    // $schedules[] = $t->range;

                    $schedules["{$t->range}"][$s->day->name_day][] = $s->teach->course->name  .' - '. $s->teach->lecturer->name .' - '.$s->teach->room->name; // pelajaran guru kelas
                    
                }
            }
        }

        // dd($schedules); //cek crossover & mutasi
        return view('admin-news.genetik.classes', compact('schedules', 'data_kromosom', 'id', 'value_schedule', 'crossover', 'mutasi', 'classes','lecturer', 'rooms', 'times', 'days'));
        // return view('admin.genetik.classes', compact('schedules', 'years', 'data_kromosom', 'id', 'value_schedule', 'crossover', 'mutasi', 'classes','lecturer'));
    }

    public function showTeacherSearch(int $id, Request $request)
    {
        $teachName      = $request->teachName;
        $crossover      = Setting::where('key', Setting::CROSSOVER)->first();
        $mutasi         = Setting::where('key', Setting::MUTASI)->first();
        $lecturer       = Lecturer::select('id', 'name')->get();
        $rooms          = Room::select('id', 'name')->get();        
        $days           = Day::select('name_day')->get();
        $times          = Time::select('range')->get();

        $schedule      = Schedule::orderBy('days_id', 'desc')
            ->orderBy('times_id', 'desc')
            ->where('type', $id)
            ->select(
                'schedules.id',
                'schedules.type',
                'schedules.teachs_id',
                'schedules.days_id',
                'schedules.times_id',
                'schedules.rooms_id',
                'schedules.value',
                'schedules.value_process'
                )
            ->get();

        $schedules  = [];

        foreach ($schedule as $s) {
            // $schedules[] = $s->time->range;
            foreach ($times as $t) {
                // $schedules[] = $s->time->range;
                if($s->time->range == $t->range && $s->teach->lecturer->name == $teachName){
                    // $schedules[] = $t->range;

                    $schedules["{$t->range}"][$s->day->name_day][] = $s->teach->course->name  .' - '. $s->teach->lecturer->name .' - '.$s->teach->room->name; // pelajaran guru kelas
                    
                }
            }
        }

        return view('admin-news.genetik.filter', compact('lecturer','schedules','rooms','times','days','teachs')); 
    }

    public function showClassesSearch(int $id, Request $request)
    {
        $className      = $request->className;
        $crossover      = Setting::where('key', Setting::CROSSOVER)->first();
        $mutasi         = Setting::where('key', Setting::MUTASI)->first();
        
        $lecturer       = Lecturer::select('id', 'name')->get();
        $rooms          = Room::select('id', 'name')->get();
        $days           = Day::select('name_day')->get();
        $times          = Time::select('range')->get();
        $teachs         = Teach::select('id')->get();
        
        $schedule      = Schedule::orderBy('days_id', 'desc')
            ->orderBy('times_id', 'desc')
            ->where('type', $id)
            ->select(
                'schedules.id',
                'schedules.type',
                'schedules.teachs_id',
                'schedules.days_id',
                'schedules.times_id',
                'schedules.rooms_id',
                'schedules.value',
                'schedules.value_process'
                )
            ->get();

        $schedules  = [];

        foreach ($schedule as $s) {
            // $schedules[] = $s->time->range;
            foreach ($times as $t) {
                // $schedules[] = $s->time->range;
                if($s->time->range == $t->range && $s->teach->room->name == $className){
                    // $schedules[] = $t->range;

                    $schedules["{$t->range}"][$s->day->name_day][] = $s->teach->course->name  .' - '. $s->teach->lecturer->name .' - '.$s->teach->room->name; // pelajaran guru kelas
                    
                }
            }
        }

        return view('admin.genetik.filterClass', compact('lecturer','schedules','rooms','times','days','teachs')); 
    }

    // Tambahkan property untuk menyimpan progress
    private $generateProgress = [];

    public function submitAjaxProgress(Request $request)
    {
        set_time_limit(3600);
        ini_set('memory_limit', '1024M');
        
        $mode = $request->input('mode', 'append');
        $selectedClasses = $request->input('classes', []);
        $input_kromosom = (int)$request->input('kromosom', 1);
        $input_generasi = (int)$request->input('generasi', 1);
        $input_crossover = (float)$request->input('crossover', 0.5);
        $input_mutasi = (float)$request->input('mutasi', 0.2);
        
        // Hapus jadwal sesuai mode
        if ($mode == 'replace_all') {
            Schedule::truncate();
            \Log::info('Mode: RESET ALL');
        } elseif ($mode == 'replace_filter' && !empty($selectedClasses)) {
            Schedule::whereIn('rooms_id', $selectedClasses)->delete();
            \Log::info('Mode: REPLACE FILTER - Kelas: ' . implode(',', $selectedClasses));
        }
        
        $totalKromosomTarget = $input_kromosom * $input_generasi;
        $count_teachs = Teach::count();
        
        // Inisialisasi progress
        $this->saveProgress([
            'status' => 'processing',
            'progress' => 0,
            'message' => 'Memulai generate...',
            'current_kromosom' => 0,
            'total_kromosom' => $totalKromosomTarget,
            'total_jadwal' => 0,
            'kromosom_stats' => []
        ]);
        
        try {
            // Loop per generasi untuk tracking progress
            $generate = new GenerateAlgoritma;
            
            if (!empty($selectedClasses)) {
                $generate->setFilteredClasses($selectedClasses);
            }
            
            // Proses bertahap dengan tracking
            for ($gen = 1; $gen <= $input_generasi; $gen++) {
                for ($kro = 1; $kro <= $input_kromosom; $kro++) {
                    $currentKromosom = (($gen - 1) * $input_kromosom) + $kro;
                    
                    $this->saveProgress([
                        'status' => 'processing',
                        'progress' => round(($currentKromosom / $totalKromosomTarget) * 100),
                        'message' => "Memproses kromosom {$currentKromosom} dari {$totalKromosomTarget}",
                        'current_kromosom' => $currentKromosom,
                        'total_kromosom' => $totalKromosomTarget,
                        'total_jadwal' => Schedule::count(),
                        'kromosom_stats' => $this->getKromosomStats($selectedClasses)
                    ]);
                    
                    // Generate satu kromosom
                    $generate->randKromosom(1, $count_teachs);
                    $generate->checkPinalty();
                    
                    \Log::info("✅ Kromosom {$currentKromosom}/{$totalKromosomTarget} selesai");
                    sleep(1); // Beri jeda agar frontend bisa catch up
                }
            }
            
            // Simpan setting akhir
            $total_gen = Setting::firstOrNew(['key' => 'total_gen']);
            $total_gen->value = $input_kromosom * $input_crossover;
            $total_gen->save();
            
            $mutasi_setting = Setting::firstOrNew(['key' => 'mutasi']);
            $mutasi_setting->value = (3 * $count_teachs) * $input_kromosom * $input_mutasi;
            $mutasi_setting->save();
            
            // Progress selesai
            $this->saveProgress([
                'status' => 'completed',
                'progress' => 100,
                'message' => 'Generate selesai!',
                'current_kromosom' => $totalKromosomTarget,
                'total_kromosom' => $totalKromosomTarget,
                'total_jadwal' => Schedule::count(),
                'kromosom_stats' => $this->getKromosomStats($selectedClasses)
            ]);
            
            return response()->json([
                'success' => true,
                'redirect' => route('admin.generates.result', 1)
            ]);
            
        } catch (\Exception $e) {
            $this->saveProgress([
                'status' => 'error',
                'progress' => 0,
                'message' => $e->getMessage(),
                'current_kromosom' => 0,
                'total_kromosom' => $totalKromosomTarget,
                'total_jadwal' => Schedule::count(),
                'kromosom_stats' => []
            ]);
            
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // Method untuk menyimpan progress (gunakan cache atau file)
    private function saveProgress($data)
    {
        // Simpan ke cache (lebih cepat dari session untuk polling)
        \Cache::put('generate_progress_' . auth()->id(), $data, 3600);
        
        // Juga simpan ke session sebagai backup
        session(['generate_progress' => $data]);
    }

    // Method untuk cek progress (panggil dari frontend)
    public function checkProgress()
    {
        $progress = \Cache::get('generate_progress_' . auth()->id());
        
        if (!$progress) {
            // Jika tidak ada progress, ambil dari database
            $selectedClasses = session('selected_classes', []);
            $stats = $this->getKromosomStats($selectedClasses);
            $totalKromosom = count($stats);
            $completedKromosom = count(array_filter($stats, function($s) {
                return $s['count'] >= $s['target'];
            }));
            
            $progress = [
                'status' => $totalKromosom > 0 && $completedKromosom >= $totalKromosom ? 'completed' : 'idle',
                'progress' => $totalKromosom > 0 ? round(($completedKromosom / $totalKromosom) * 100) : 0,
                'message' => $totalKromosom > 0 ? "Selesai {$completedKromosom}/{$totalKromosom} kromosom" : 'Belum ada generate',
                'current_kromosom' => $completedKromosom,
                'total_kromosom' => $totalKromosom,
                'total_jadwal' => Schedule::count(),
                'kromosom_stats' => $stats
            ];
        }
        
        return response()->json($progress);
    }

    // Method untuk mengambil statistik kromosom
    private function getKromosomStats($selectedClasses = [])
    {
        // Hitung target per kromosom berdasarkan kelas yang dipilih
        $teachsQuery = Teach::with('course');
        if (!empty($selectedClasses)) {
            $teachsQuery->whereIn('class_room', $selectedClasses);
        }
        
        $targetPerKromosom = 0;
        foreach ($teachsQuery->get() as $teach) {
            $targetPerKromosom += $teach->course->hours_per_week;
        }
        
        if ($targetPerKromosom == 0) {
            $targetPerKromosom = 468; // default
        }
        
        // Ambil semua type kromosom
        $kromosomTypes = Schedule::select('type')
            ->groupBy('type')
            ->orderBy('type', 'asc')
            ->get();
        
        $stats = [];
        foreach ($kromosomTypes as $type) {
            $count = Schedule::where('type', $type->type)->count();
            $stats[] = [
                'type' => $type->type,
                'count' => $count,
                'target' => $targetPerKromosom,
                'percentage' => $targetPerKromosom > 0 ? round(($count / $targetPerKromosom) * 100) : 0
            ];
        }
        
        return $stats;
    }

    // Method untuk mengambil data hasil akhir
    public function getResultData()
    {
        $selectedClasses = session('selected_classes', []);
        
        $teachsQuery = Teach::with('course');
        if (!empty($selectedClasses)) {
            $teachsQuery->whereIn('class_room', $selectedClasses);
        }
        
        $targetPerKromosom = 0;
        foreach ($teachsQuery->get() as $teach) {
            $targetPerKromosom += $teach->course->hours_per_week;
        }
        
        if ($targetPerKromosom == 0) {
            $targetPerKromosom = 468;
        }
        
        $kromosomTypes = Schedule::select('type')
            ->groupBy('type')
            ->orderBy('type', 'asc')
            ->get();
        
        $kromosomStats = [];
        $totalJadwal = 0;
        
        foreach ($kromosomTypes as $type) {
            $count = Schedule::where('type', $type->type)->count();
            $totalJadwal += $count;
            $kromosomStats[] = [
                'type' => $type->type,
                'count' => $count,
                'target' => $targetPerKromosom,
                'percentage' => $targetPerKromosom > 0 ? round(($count / $targetPerKromosom) * 100) : 0
            ];
        }
        
        return response()->json([
            'total' => $totalJadwal,
            'target_per_kromosom' => $targetPerKromosom,
            'kromosom_stats' => $kromosomStats
        ]);
    }

    public function getStatus()
    {
        $total = Schedule::count();
        $latestLog = $this->getLatestLog();
        
        // Estimasi progress berdasarkan total jadwal
        $teachs = Teach::with('course')->get();
        $target = 0;
        foreach ($teachs as $teach) {
            $target += $teach->course->hours_per_week;
        }
        
        $progress = $target > 0 ? min(99, round(($total / $target) * 100)) : 0;
        
        return response()->json([
            'status' => $total > 0 ? 'processing' : 'waiting',
            'progress' => $progress,
            'message' => 'Mengenerate jadwal... (' . $total . ' jadwal tersimpan)',
            'total' => $total,
            'target' => $target,
            'last_log' => $latestLog
        ]);
    }

    private function getLatestLog()
    {
        $logFile = storage_path('logs/laravel.log');
        if (!file_exists($logFile)) return 'Menunggu proses dimulai...';
        
        $lines = file($logFile);
        $lastLines = array_slice($lines, -5);
        
        foreach ($lastLines as $line) {
            if (strpos($line, 'Kromosom') !== false) {
                return trim($line);
            }
        }
        return 'Proses sedang berjalan...';
    }

    // Di GenetikController.php
    public function checkSchedule()
    {
        $selectedClasses = session('selected_classes', []);
        
        $teachs = Teach::with('course');
        
        if (!empty($selectedClasses)) {
            $teachs = $teachs->whereIn('class_room', $selectedClasses);
        }
        
        $targetPerKromosom = 0;
        foreach ($teachs->get() as $teach) {
            $targetPerKromosom += $teach->course->hours_per_week;
        }
        
        // Ambil SEMUA type yang ada (termasuk 0,1,2,3...)
        $kromosomTypes = Schedule::select('type')
            ->groupBy('type')
            ->orderBy('type', 'asc')
            ->get();
        
        $kromosomStats = [];
        $totalJadwal = 0;
        
        foreach ($kromosomTypes as $type) {
            $count = Schedule::where('type', $type->type)->count();
            $totalJadwal += $count;
            $kromosomStats[] = [
                'type' => $type->type,
                'count' => $count,
                'target' => $targetPerKromosom,
                'percentage' => $targetPerKromosom > 0 ? round(($count / $targetPerKromosom) * 100) : 0
            ];
        }
        
        return response()->json([
            'total' => $totalJadwal,
            'target_per_kromosom' => $targetPerKromosom,
            'kromosom_stats' => $kromosomStats,
            'total_kromosom' => count($kromosomTypes)
        ]);
    }

}
