@extends('admin-news.layouts.master')

@section('title')
{{ $title = 'Hasil Generate Jadwal' }}
@stop

@section('style')
<link href="{{ asset('new_template/assets/libs/tablesaw/dist/tablesaw.css') }}" rel="stylesheet">
<style>
    .matrix-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 12px;
    }
    .matrix-table th, .matrix-table td {
        border: 1px solid #ddd;
        padding: 8px 5px;
        text-align: center;
        vertical-align: top;
    }
    .matrix-table th {
        background: #17a2b8;
        color: white;
        font-weight: bold;
        position: sticky;
        top: 0;
    }
    .time-col {
        background-color: #f8f9fa;
        font-weight: bold;
        width: 100px;
    }
    .subject-name {
        font-weight: bold;
        color: #2c3e50;
        font-size: 12px;
    }
    .teacher-name {
        font-size: 10px;
        color: #7f8c8d;
    }
    .class-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 15px 20px;
        border-radius: 10px;
        margin-bottom: 20px;
    }
    .class-nav {
        display: flex;
        flex-wrap: wrap;
        gap: 5px;
        margin-bottom: 20px;
        padding: 10px;
        background: #f8f9fa;
        border-radius: 8px;
    }
    .class-nav .btn {
        margin: 2px;
    }
    .badge-mapel {
        background: #17a2b8;
        color: white;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 10px;
    }
    
    /* Style untuk ringkasan kromosom - GRID CARD */
    .kromosom-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
        gap: 10px;
        margin-top: 10px;
    }
    .kromosom-card {
        background: white;
        border-radius: 10px;
        padding: 10px;
        text-align: center;
        cursor: pointer;
        transition: all 0.2s;
        border: 2px solid transparent;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    .kromosom-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.15);
    }
    .kromosom-card.active {
        border-color: #17a2b8;
        background: #e6f7ff;
    }
    .kromosom-card .number {
        font-size: 14px;
        font-weight: bold;
        color: #333;
    }
    .kromosom-card .stats {
        font-size: 18px;
        font-weight: bold;
        margin: 5px 0;
    }
    .kromosom-card .percentage {
        font-size: 11px;
    }
    .kromosom-card.success { border-top: 3px solid #28a745; }
    .kromosom-card.warning { border-top: 3px solid #ffc107; }
    .kromosom-card.danger { border-top: 3px solid #dc3545; }
    
    .summary-header {
        background: #f0f7ff;
        border-radius: 10px;
        padding: 12px 20px;
        margin-bottom: 15px;
        border-left: 4px solid #17a2b8;
    }
</style>
@stop

@section('script')
<script>
    function confirmDelete() {
        if (confirm('Hapus jadwal ini? Data tidak bisa dikembalikan!')) {
            window.location.href = '{{ route("admin.generates.delete", $id) }}';
        }
    }
    
    function toggleView() {
        var listView = document.getElementById('list-view');
        var matrixView = document.getElementById('matrix-view');
        var btnList = document.getElementById('btn-list');
        var btnMatrix = document.getElementById('btn-matrix');
        
        if (listView.style.display === 'none') {
            listView.style.display = 'block';
            matrixView.style.display = 'none';
            btnList.classList.add('btn-primary');
            btnList.classList.remove('btn-outline-info');
            btnMatrix.classList.add('btn-outline-info');
            btnMatrix.classList.remove('btn-primary');
        } else {
            listView.style.display = 'none';
            matrixView.style.display = 'block';
            btnMatrix.classList.add('btn-primary');
            btnMatrix.classList.remove('btn-outline-info');
            btnList.classList.add('btn-outline-info');
            btnList.classList.remove('btn-primary');
        }
    }
</script>
@stop

@section('content')
@php
use Illuminate\Support\Facades\DB;
@endphp
<div class="page-breadcrumb no-print">
    <div class="row">
        <div class="col-5 align-self-center">
            <h4 class="page-title">{{ $title }}</h4>
        </div>
        <div class="col-7 align-self-center">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.generates') }}">Generate</a></li>
                    <li class="breadcrumb-item active">Hasil Generate</li>
                </ol>
            </nav>
        </div>
    </div>
</div>

<div class="container-fluid">
    <div class="class-header">
        <div class="row">
            <div class="col-md-6">
                <h4><i class="fa fa-calendar"></i> Jadwal Pelajaran</h4>
                <p>Kromosom/Generasi ke-{{ $id }} | Fitness: {{ number_format($value_schedule->value ?? 0, 4) }}</p>
                <p>Crossover: {{ $crossover->value ?? 0 }} | Mutasi: {{ $mutasi->value ?? 0 }}</p>
            </div>
            <div class="col-md-6 text-right no-print">
                <button onclick="window.print()" class="btn btn-light"><i class="fa fa-print"></i> Print</button>
                <a href="{{ route('admin.generates.excel', $id) }}" class="btn btn-light"><i class="fa fa-download"></i> Excel</a>
                <button class="btn btn-danger" onclick="confirmDelete()"><i class="fa fa-trash"></i> Hapus Jadwal</button>
            </div>
        </div>
    </div>

    <!-- ===================================== RINGKASAN KROMOSOM (GRID CARD) ==================== -->
    @php
        // Hitung target JP per kromosom secara dinamis
        $rooms = App\Models\Room::all();
        $targetJpPerKromosom = 0;
        
        foreach ($rooms as $room) {
            $teachsForRoom = App\Models\Teach::where('class_room', $room->id)->with('course')->get();
            $totalJpForRoom = 0;
            foreach ($teachsForRoom as $teach) {
                $totalJpForRoom += $teach->course->hours_per_week;
            }
            $targetJpPerKromosom += $totalJpForRoom;
        }
        
        $jumlahKelas = $rooms->count();
        $jpPerKelas = $jumlahKelas > 0 ? round($targetJpPerKromosom / $jumlahKelas) : 0;
    @endphp

    @if(!empty($data_kromosom))
    <div class="summary-header no-print">
        <strong><i class="fa fa-chart-bar"></i> Pilih Kromosom:</strong>
        <div class="kromosom-grid">
            @foreach ($data_kromosom as $krom)
                @php
                    // 🔥 HITUNG TARGET HANYA UNTUK KELAS YANG ADA DI KROMOSOM INI
                    $classesInThisKromosom = App\Models\Schedule::where('schedules.type', $krom['type'])
                        ->join('rooms', 'rooms.id', '=', 'schedules.rooms_id')
                        ->select('rooms.*')
                        ->distinct()
                        ->get();
                    
                    $targetJpForThisKromosom = 0;
                    foreach ($classesInThisKromosom as $room) {
                        $teachsForRoom = App\Models\Teach::where('class_room', $room->id)->with('course')->get();
                        foreach ($teachsForRoom as $teach) {
                            $targetJpForThisKromosom += $teach->course->hours_per_week;
                        }
                    }
                    
                    if ($targetJpForThisKromosom == 0) {
                        $targetJpForThisKromosom = App\Models\Schedule::where('type', $krom['type'])->count();
                    }
                    
                    $totalSlots = $targetJpForThisKromosom;
                    $achieved = App\Models\Schedule::where('type', $krom['type'])->count();
                    $percentage = $totalSlots > 0 ? round(($achieved / $totalSlots) * 100) : 0;
                    $isActive = ($id == $krom['type']);
                    
                    // 🔥 HITUNG JUMLAH PELANGGARAN UNTUK KROMOSOM INI
                    $violationCount = 0;

                    // Cek max per hari (tetap pakai Eloquent)
                    $teachsInKromosom = App\Models\Teach::with('course')
                        ->whereIn('class_room', $classesInThisKromosom->pluck('id')->toArray())
                        ->get();

                    foreach ($teachsInKromosom as $teach) {
                        $dailyCounts = App\Models\Schedule::where('type', $krom['type'])
                            ->where('teachs_id', $teach->id)
                            ->select('days_id', \DB::raw('count(*) as total'))
                            ->groupBy('days_id')
                            ->get();
                        foreach ($dailyCounts as $daily) {
                            if ($daily->total > $teach->course->max_hours_per_day) {
                                $violationCount++;
                            }
                            if ($daily->total < $teach->course->min_hours_per_day && $daily->total > 0) {
                                $violationCount++;
                            }
                        }
                    }

                    // Cek bentrok guru - pakai DB::raw
                    $teacherConflicts = DB::select("
                        SELECT COUNT(*) as total FROM (
                            SELECT lecturers.name, schedules.days_id, schedules.times_id, COUNT(*) as cnt
                            FROM schedules
                            JOIN teachs ON teachs.id = schedules.teachs_id
                            JOIN lecturers ON lecturers.id = teachs.lecturers_id
                            WHERE schedules.type = ?
                            GROUP BY lecturers.name, schedules.days_id, schedules.times_id
                            HAVING cnt > 1
                        ) as conflicts
                    ", [$krom['type']])[0]->total ?? 0;
                    $violationCount += $teacherConflicts;

                    // Cek bentrok kelas
                    $classConflicts = DB::select("
                        SELECT COUNT(*) as total FROM (
                            SELECT rooms.name, schedules.days_id, schedules.times_id, COUNT(*) as cnt
                            FROM schedules
                            JOIN rooms ON rooms.id = schedules.rooms_id
                            WHERE schedules.type = ?
                            GROUP BY rooms.name, schedules.days_id, schedules.times_id
                            HAVING cnt > 1
                        ) as conflicts
                    ", [$krom['type']])[0]->total ?? 0;
                    $violationCount += $classConflicts;

                    
                    // Tentukan warna badge berdasarkan jumlah pelanggaran
                    if ($violationCount == 0) {
                        $badgeColor = 'success';
                        $badgeIcon = '✅';
                    } elseif ($violationCount < 5) {
                        $badgeColor = 'warning';
                        $badgeIcon = '⚠️';
                    } else {
                        $badgeColor = 'danger';
                        $badgeIcon = '❌';
                    }
                    
                    $jumlahKelasInKromosom = $classesInThisKromosom->count();
                    $jpPerKelasInKromosom = $jumlahKelasInKromosom > 0 ? round($targetJpForThisKromosom / $jumlahKelasInKromosom) : 0;
                    
                    if ($achieved >= $totalSlots) {
                        $statusClass = 'success';
                        $icon = '✅';
                    } elseif ($achieved >= $totalSlots * 0.7) {
                        $statusClass = 'warning';
                        $icon = '⚠️';
                    } else {
                        $statusClass = 'danger';
                        $icon = '❌';
                    }
                @endphp
                <a href="{{ route('admin.generates.result', $krom['type']) }}" style="text-decoration: none;">
                    <div class="kromosom-card {{ $statusClass }} {{ $isActive ? 'active' : '' }}">
                        <div class="number">Kromosom {{ $krom['type'] }}</div>
                        <div class="stats">{{ $achieved }}/{{ $totalSlots }}</div>
                        <div class="percentage">{{ $icon }} {{ $percentage }}%</div>
                        @if($jumlahKelasInKromosom > 0)
                            <div class="small text-muted mt-1">{{ $jumlahKelasInKromosom }} kelas</div>
                        @endif
                        <!-- 🔥 BADGE PELANGGARAN -->
                        <div class="mt-1">
                            <span class="badge badge-{{ $badgeColor }}" style="font-size: 10px;">
                                {{ $badgeIcon }} {{ $violationCount }} pelanggaran
                            </span>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>

        @php
            // 🔥 Hitung target untuk kromosom yang sedang aktif (yang dipilih)
            $activeClasses = App\Models\Schedule::where('schedules.type', $id)
                ->join('rooms', 'rooms.id', '=', 'schedules.rooms_id')
                ->select('rooms.*')
                ->distinct()
                ->get();
            
            $activeTargetJp = 0;
            foreach ($activeClasses as $room) {
                $teachsForRoom = App\Models\Teach::where('class_room', $room->id)->with('course')->get();
                foreach ($teachsForRoom as $teach) {
                    $activeTargetJp += $teach->course->hours_per_week;
                }
            }
            
            $activeJumlahKelas = $activeClasses->count();
            $activeJpPerKelas = $activeJumlahKelas > 0 ? round($activeTargetJp / $activeJumlahKelas) : 0;
        @endphp

        <div class="mt-2 small text-muted">
            <i class="fa fa-info-circle"></i> 
            Target kromosom {{ $id }}: <strong>{{ number_format($activeTargetJp) }} JP</strong> 
            ({{ $activeJumlahKelas }} kelas × {{ $activeJpPerKelas }} JP/kelas) 
            | Klik card untuk melihat jadwal kromosom tersebut
        </div>
    </div>
    @endif

    <!-- Filter Kelas & Toggle View -->
    <div class="class-nav no-print">
        <div class="row w-100">
            <div class="col-md-6">
                <strong><i class="fa fa-filter"></i> Filter Kelas:</strong>
                <a href="{{ route('admin.generates.result', $id) }}" 
                   class="btn btn-sm @if(empty($filterClass)) btn-primary @else btn-outline-info @endif">
                    🏫 Semua Kelas
                </a>
                @foreach($classCounts as $class)
                    <a href="{{ route('admin.generates.result', ['id' => $id, 'class' => $class->name]) }}" 
                       class="btn btn-sm @if($filterClass == $class->name) btn-primary @else btn-outline-info @endif">
                        📚 {{ $class->name }}
                    </a>
                @endforeach
            </div>
            <div class="col-md-6 text-right">
                <strong><i class="fa fa-eye"></i> Tampilan:</strong>
                <button id="btn-list" class="btn btn-sm btn-primary" onclick="toggleView()">📋 List</button>
                <button id="btn-matrix" class="btn btn-sm btn-outline-info" onclick="toggleView()">📊 Matriks</button>
            </div>
        </div>
    </div>

    <!-- ==================== CARD VALIDASI JADWAL (LENGKAP) ==================== -->
    <div class="row no-print mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-info text-white py-2">
                    <h5 class="mb-0"><i class="fa fa-check-circle"></i> Validasi Jadwal</h5>
                </div>
                <div class="card-body p-3">
                    @php
                        $typeId = $id;
                        
                        // 🔥 AMBIL FILTER KELAS DARI URL
                        $filterClass = request()->input('class');
                        
                        // 🔥 AMBIL KELAS YANG HANYA ADA DI KROMOSOM INI
                        $classesInThisChromosome = App\Models\Schedule::where('schedules.type', $typeId)
                            ->join('rooms', 'rooms.id', '=', 'schedules.rooms_id')
                            ->select('rooms.*')
                            ->distinct()
                            ->get();
                        
                        // 🔥 Tentukan rooms berdasarkan filter dan kromosom
                        if (!empty($filterClass)) {
                            // Jika difilter, cari kelas yang difilter DAN ada di kromosom ini
                            $rooms = $classesInThisChromosome->filter(function($room) use ($filterClass) {
                                return $room->name == $filterClass;
                            });
                            $isFiltered = true;
                        } else {
                            // Jika tidak difilter, ambil semua kelas yang ada di kromosom ini
                            $rooms = $classesInThisChromosome;
                            $isFiltered = false;
                        }
                        
                        // Jika tidak ada kelas di kromosom ini, tampilkan pesan
                        if ($rooms->isEmpty()) {
                            echo '<div class="alert alert-warning">Tidak ada data jadwal untuk kromosom ini</div>';
                        }
                        
                        // HITUNG TARGET PER KELAS (HANYA UNTUK KELAS YANG ADA DI KROMOSOM INI)
                        $targetJpPerKromosom = 0;
                        $classTargets = [];
                        foreach ($rooms as $room) {
                            $teachsForRoom = App\Models\Teach::where('class_room', $room->id)->with('course')->get();
                            $totalJpForRoom = 0;
                            foreach ($teachsForRoom as $teach) {
                                $totalJpForRoom += $teach->course->hours_per_week;
                            }
                            $classTargets[$room->name] = $totalJpForRoom;
                            $targetJpPerKromosom += $totalJpForRoom;
                        }
                        
                        // 1. KAPASITAS KELAS (max 48 JP/minggu)
                        $maxJpPerWeek = (App\Models\Setting::get('jp_per_day', 8)) * 6;
                        $classViolations = [];
                        foreach ($rooms as $room) {
                            $totalJp = App\Models\Schedule::where('type', $typeId)->where('rooms_id', $room->id)->count();
                            if ($totalJp > $maxJpPerWeek) {
                                $classViolations[] = [
                                    'class' => $room->name,
                                    'actual' => $totalJp,
                                    'target' => $classTargets[$room->name],
                                    'max' => $maxJpPerWeek,
                                    'excess' => $totalJp - $maxJpPerWeek
                                ];
                            }
                        }
                        
                        // 2. REALITA PER KELAS (HANYA KELAS YANG ADA DI KROMOSOM INI)
                        $classReality = [];
                        foreach ($rooms as $room) {
                            $actualJp = App\Models\Schedule::where('type', $typeId)->where('rooms_id', $room->id)->count();
                            $targetJp = $classTargets[$room->name];
                            $percentage = $targetJp > 0 ? round(($actualJp / $targetJp) * 100) : 0;
                            $classReality[] = [
                                'class' => $room->name,
                                'actual' => $actualJp,
                                'target' => $targetJp,
                                'percentage' => $percentage,
                                'status' => $actualJp >= $targetJp ? 'success' : ($percentage >= 70 ? 'warning' : 'danger')
                            ];
                        }
                        
                        // ... kode selanjutnya tetap sama, gunakan $rooms (bukan Room::all())
                        // 3. MAX & MIN JP PER HARI PER MAPEL
                        $maxPerDayViolations = [];
                        $minPerDayViolations = [];
                        
                        // 🔥 AMBIL TEACH HANYA UNTUK KELAS YANG ADA DI KROMOSOM INI
                        $roomIds = $rooms->pluck('id')->toArray();
                        $teachs = App\Models\Teach::with('course')->whereIn('class_room', $roomIds)->get();
                        
                        foreach ($teachs as $teach) {
                            $dailyCounts = App\Models\Schedule::where('type', $typeId)->where('teachs_id', $teach->id)
                                ->select('days_id', \DB::raw('count(*) as total'))->groupBy('days_id')->get();
                            foreach ($dailyCounts as $daily) {
                                if ($daily->total > $teach->course->max_hours_per_day) {
                                    $maxPerDayViolations[] = [
                                        'course' => $teach->course->name,
                                        'teacher' => $teach->lecturer->name,
                                        'class' => $teach->room->name,
                                        'day' => App\Models\Day::find($daily->days_id)->name_day,
                                        'actual' => $daily->total,
                                        'max' => $teach->course->max_hours_per_day
                                    ];
                                }
                                if ($daily->total < $teach->course->min_hours_per_day && $daily->total > 0) {
                                    $minPerDayViolations[] = [
                                        'course' => $teach->course->name,
                                        'teacher' => $teach->lecturer->name,
                                        'class' => $teach->room->name,
                                        'day' => App\Models\Day::find($daily->days_id)->name_day,
                                        'actual' => $daily->total,
                                        'min' => $teach->course->min_hours_per_day
                                    ];
                                }
                            }
                        }
                        
                        // 4. BENTROK GURU & KELAS (SUDAH TERBATAS OLEH $typeId)
                        $teacherConflicts = App\Models\Schedule::where('schedules.type', $typeId)
                            ->join('teachs', 'teachs.id', '=', 'schedules.teachs_id')
                            ->join('lecturers', 'lecturers.id', '=', 'teachs.lecturers_id')
                            ->select('lecturers.name', 'schedules.days_id', 'schedules.times_id', \DB::raw('count(*) as total'))
                            ->groupBy('lecturers.name', 'schedules.days_id', 'schedules.times_id')
                            ->having('total', '>', 1)->get();
                        
                        $classConflicts = App\Models\Schedule::where('schedules.type', $typeId)
                            ->join('rooms', 'rooms.id', '=', 'schedules.rooms_id')
                            ->select('rooms.name', 'schedules.days_id', 'schedules.times_id', \DB::raw('count(*) as total'))
                            ->groupBy('rooms.name', 'schedules.days_id', 'schedules.times_id')
                            ->having('total', '>', 1)->get();
                        
                        // 5. GAP & OVERLAP
                        $gapViolations = [];
                        $schedulesByClass = App\Models\Schedule::where('type', $typeId)
                            ->whereIn('rooms_id', $roomIds)  // 🔥 FILTER KELAS YANG ADA DI KROMOSOM
                            ->with(['teach.course', 'day', 'time', 'room'])
                            ->get()
                            ->groupBy('rooms_id');
                            
                        foreach ($schedulesByClass as $roomId => $schedules) {
                            $roomName = $schedules->first()->room->name ?? '?';
                            foreach ($schedules->groupBy('days_id') as $dayId => $daySchedules) {
                                $sorted = $daySchedules->sortBy(fn($s) => $s->time->time_begin ?? '00:00');
                                $prev = null;
                                foreach ($sorted as $curr) {
                                    if ($prev && $curr->time) {
                                        $gap = (strtotime($curr->time->time_begin) - strtotime($prev->time->time_end)) / 60;
                                        if ($gap < 0) {
                                            $gapViolations[] = "🔴 OVERLAP di {$roomName} (Hari " . ($curr->day->name_day ?? '?') . "): {$prev->teach->course->name} & {$curr->teach->course->name}";
                                        } elseif ($gap > 0 && $gap < 15) {
                                            $gapViolations[] = "⏱️ Jeda pendek ({$gap} menit) di {$roomName}: {$prev->teach->course->name} → {$curr->teach->course->name}";
                                        }
                                    }
                                    $prev = $curr;
                                }
                            }
                        }
                        
                        // HITUNG TOTAL
                        $totalViolations = count($classViolations) + count($maxPerDayViolations) + count($minPerDayViolations) + 
                        $teacherConflicts->count() + $classConflicts->count() + count($gapViolations);
                        // 🔥 Hitung total jadwal berdasarkan filter kelas
                        if ($isFiltered && !empty($filterClass)) {
                            $filteredRoom = App\Models\Room::where('name', $filterClass)->first();
                            if ($filteredRoom) {
                                $totalSchedules = App\Models\Schedule::where('type', $typeId)
                                    ->where('rooms_id', $filteredRoom->id)
                                    ->count();
                            } else {
                                $totalSchedules = App\Models\Schedule::where('type', $typeId)->count();
                            }
                        } else {
                            $totalSchedules = App\Models\Schedule::where('type', $typeId)->count();
                        }
                        // 🔥 Hitung target dan completion rate berdasarkan filter
                        if ($isFiltered && !empty($filterClass)) {
                            $targetForRate = $classTargets[$filterClass] ?? 1;
                            // 🔥 Untuk filtered, totalSchedules sudah dihitung hanya untuk kelas itu
                            $completionRate = $targetForRate > 0 ? round(($totalSchedules / $targetForRate) * 100) : 0;
                        } else {
                            $targetForRate = $targetJpPerKromosom;
                            $completionRate = $targetForRate > 0 ? round(($totalSchedules / $targetForRate) * 100) : 0;
                        }                        $statusColor = $totalViolations == 0 ? 'success' : ($totalViolations < 10 ? 'warning' : 'danger');
                        $statusText = $totalViolations == 0 ? 'Sempurna' : ($totalViolations < 10 ? 'Perlu Perbaikan' : 'Banyak Pelanggaran');
                        
                        $missingJp = $targetJpPerKromosom - $totalSchedules;
                        $affectedClasses = count(array_filter($classReality, fn($c) => $c['percentage'] < 100));
                    @endphp
                    
                    <!-- RINGKASAN 4 KARTU -->
                    <div class="row text-center mb-3">
                    <div class="col-3">
                        <div class="p-2 border rounded bg-{{ $statusColor }}-light">
                            <h3 class="mb-0 text-{{ $statusColor }}">{{ $completionRate }}%</h3>
                            <small>Kelengkapan</small>
                            @if($isFiltered)
                                <div class="small text-muted">(Kelas: {{ $filterClass }})</div>
                            @endif
                        </div>
                    </div>
                        @php
                            // 🔥 Hitung target hanya untuk kelas yang difilter (atau yang ada di kromosom)
                            if ($isFiltered && !empty($filterClass)) {
                                $filteredRoom = App\Models\Room::where('name', $filterClass)->first();
                                if ($filteredRoom) {
                                    $targetForDisplay = $classTargets[$filterClass] ?? 0;
                                } else {
                                    $targetForDisplay = $targetJpPerKromosom;
                                }
                            } else {
                                $targetForDisplay = $targetJpPerKromosom;
                            }
                        @endphp

                        <div class="col-3">
                            <div class="p-2 border rounded">
                                <h3 class="mb-0 text-info">{{ $totalSchedules }}/{{ $targetForDisplay }}</h3>
                                <small>Total JP</small>
                                @if($isFiltered)
                                    <div class="small text-muted">(Kelas: {{ $filterClass }})</div>
                                @endif
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="p-2 border rounded">
                                <h3 class="mb-0 text-{{ $totalViolations == 0 ? 'success' : 'danger' }}">{{ $totalViolations }}</h3>
                                <small>Pelanggaran</small>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="p-2 border rounded bg-{{ $statusColor }}-light">
                                <h3 class="mb-0 text-{{ $statusColor }}">{{ $statusText }}</h3>
                                <small>Status</small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- REALITA PER KELAS -->
                    <div class="mb-3">
                        <strong>
                            <i class="fa fa-chart-line"></i> 
                            Realita per Kelas
                            @if($isFiltered)
                                <span class="badge badge-info">Filter: {{ $filterClass }}</span>
                            @endif
                        </strong>
                        <div class="table-responsive mt-2">
                            <table class="table table-sm table-bordered">
                                <thead class="bg-light">
                                    <tr>
                                        <th>Kelas</th>
                                        <th>Target JP</th>
                                        <th>Terisi</th>
                                        <th>Persentase</th>
                                        <th>Kurang</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($classReality as $cr)
                                    <tr>
                                        <td><strong>{{ $cr['class'] }}</strong></td>
                                        <td>{{ $cr['target'] }}</td>
                                        <td>{{ $cr['actual'] }}</td>
                                        <td class="text-{{ $cr['status'] == 'success' ? 'success' : ($cr['status'] == 'warning' ? 'warning' : 'danger') }}">
                                            {{ $cr['percentage'] }}%
                                        </td>
                                        <td class="text-danger">{{ $cr['target'] - $cr['actual'] }}</td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="5" class="text-center">Tidak ada data kelas</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- SARAN PERBAIKAN (BARU) -->
                    @if($missingJp > 0)
                    <div class="alert alert-warning mb-3 py-2">
                        <i class="fa fa-lightbulb-o"></i> 
                        <strong>Rekomendasi Perbaikan:</strong>
                        <ul class="mb-0 mt-1">
                            <li>📊 Masih kurang <strong>{{ $missingJp }} JP</strong> dari target {{ $targetJpPerKromosom }} JP</li>
                            <li>🏫 {{ $affectedClasses }} kelas belum mencapai target ({{ implode(', ', array_map(fn($c) => $c['class'], array_filter($classReality, fn($c) => $c['percentage'] < 100))) }})</li>
                            @if(count($minPerDayViolations) > 0)
                            <li>⚠️ {{ count($minPerDayViolations) }} mapel belum memenuhi minimal JP per hari</li>
                            @endif
                            @if($teacherConflicts->count() > 0)
                            <li>👨‍🏫 Ada bentrok jadwal guru, coba generate ulang dengan parameter berbeda</li>
                            @endif
                            <li>💡 Coba tingkatkan nilai <strong>Crossover (0.7-0.9)</strong> atau <strong>Mutasi (0.3-0.5)</strong></li>
                        </ul>
                    </div>
                    @endif
                    
                    <!-- DETAIL PELANGGARAN -->
                    @if($totalViolations > 0)
                    <div class="small">
                        <button class="btn btn-sm btn-outline-danger mb-2" type="button" data-toggle="collapse" data-target="#detailViolations">
                            <i class="fa fa-exclamation-triangle"></i> Detail Pelanggaran ({{ $totalViolations }})
                        </button>
                        <div id="detailViolations" class="collapse">
                            <div class="row">
                                <div class="col-md-6">
                                    @if(count($classViolations) > 0)
                                    <div class="mb-2">
                                        <strong class="text-danger">📊 Kelas Melebihi Kapasitas:</strong>
                                        <ul class="mb-0 pl-3">
                                            @foreach($classViolations as $v)
                                            <li>{{ $v['class'] }}: {{ $v['actual'] }}/{{ $v['max'] }} JP (kelebihan {{ $v['excess'] }} JP)</li>
                                            @endforeach
                                        </ul>
                                    </div>@endif
                                    
                                    @if(count($maxPerDayViolations) > 0)
                                    <div class="mb-2">
                                        <strong class="text-danger">⚠️ Melebihi Max/Hari ({{ count($maxPerDayViolations) }}):</strong>
                                        <ul class="mb-0 pl-3">
                                            @foreach(array_slice($maxPerDayViolations,0,5) as $v)
                                            <li>{{ $v['course'] }} ({{ $v['class'] }}, {{ $v['day'] }}): {{ $v['actual'] }}/{{ $v['max'] }} JP</li>
                                            @endforeach
                                            @if(count($maxPerDayViolations) > 5) +{{ count($maxPerDayViolations)-5 }} lagi @endif
                                        </ul>
                                    </div>@endif
                                    
                                    @if($teacherConflicts->count() > 0)
                                    <div class="mb-2">
                                        <strong class="text-danger">👨‍🏫 Bentrok Guru ({{ $teacherConflicts->count() }}):</strong>
                                        <ul class="mb-0 pl-3">
                                            @foreach($teacherConflicts as $c)
                                            <li>{{ $c->name }} ({{ App\Models\Day::find($c->days_id)->name_day }})</li>
                                            @endforeach
                                        </ul>
                                    </div>@endif
                                </div>
                                <div class="col-md-6">
                                    @if(count($minPerDayViolations) > 0)
                                    <div class="mb-2">
                                        <strong class="text-warning">⚠️ Kurang dari Min/Hari ({{ count($minPerDayViolations) }}):</strong>
                                        <ul class="mb-0 pl-3">
                                            @foreach(array_slice($minPerDayViolations,0,5) as $v)
                                            <li>{{ $v['course'] }} ({{ $v['class'] }}, {{ $v['day'] }}): {{ $v['actual'] }}/{{ $v['min'] }} JP</li>
                                            @endforeach
                                            @if(count($minPerDayViolations) > 5) +{{ count($minPerDayViolations)-5 }} lagi @endif
                                        </ul>
                                    </div>@endif
                                    
                                    @if($classConflicts->count() > 0)
                                    <div class="mb-2">
                                        <strong class="text-danger">🏫 Bentrok Kelas ({{ $classConflicts->count() }}):</strong>
                                        <ul class="mb-0 pl-3">
                                            @foreach($classConflicts as $c)
                                            <li>{{ $c->name }} ({{ App\Models\Day::find($c->days_id)->name_day }})</li>
                                            @endforeach
                                        </ul>
                                    </div>@endif
                                    
                                    @if(count($gapViolations) > 0)
                                    <div class="mb-2">
                                        <strong class="text-warning">⏱️ Gap/Overlap ({{ count($gapViolations) }}):</strong>
                                        <ul class="mb-0 pl-3">
                                            @foreach(array_slice($gapViolations,0,3) as $v)
                                            <li>{{ $v }}</li>
                                            @endforeach
                                            @if(count($gapViolations) > 3) +{{ count($gapViolations)-3 }} lagi @endif
                                        </ul>
                                    </div>@endif
                                </div>
                            </div>
                        </div>
                    </div>
                    @else
                    <div class="alert alert-success mb-0 py-2 text-center">
                        <i class="fa fa-check-circle"></i> <strong>Validasi Sempurna!</strong> Tidak ada pelanggaran. Jadwal sudah optimal!
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if(!empty($filterClass))
        <!-- TAMPILAN MATRIKS (untuk 1 kelas) -->
        <div id="matrix-view" style="display: block;">
            <div class="table-responsive">
                <table class="matrix-table">
                    <thead>
                        <tr>
                            <th class="time-col">Jam / Hari</th>
                            @foreach($days as $day)
                                <th>{{ $day->name_day }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($times as $time)
                        <tr>
                            <td class="time-col">{{ $time->range }}</td>
                            @foreach($days as $day)
                                @php
                                    $schedule = $scheduleMatrix[$day->name_day][$time->range] ?? null;
                                @endphp
                                <td>
                                    @if($schedule)
                                        <div class="subject-name">{{ $schedule['course'] }}</div>
                                        <div class="teacher-name">{{ $schedule['teacher'] }}</div>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                            @endforeach
                        <tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div id="list-view" style="display: none;">
            <div class="alert alert-info">Tampilan list untuk 1 kelas sama dengan matriks di atas.</div>
        </div>
    @else
        <!-- TAMPILAN LIST (untuk semua kelas) -->
        <div id="list-view" style="display: block;">
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead class="bg-info text-white">
                        <tr>
                            <th>No</th>
                            <th>Hari</th>
                            <th>Jam</th>
                            <th>Kelas</th>
                            <th>Mata Pelajaran</th>
                            <th>Guru</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $counter = 1; @endphp
                        @foreach($schedules as $s)
                        <tr>
                            <td>{{ $counter++ }}</td>
                            <td><span class="badge badge-info">{{ $s->day->name_day ?? '-' }}</span></td>
                            <td>{{ $s->time->range ?? '-' }}</td>
                            <td><strong>{{ $s->room->name ?? '-' }}</strong></td>
                            <td>{{ $s->teach->course->name ?? '-' }}</td>
                            <td>{{ $s->teach->lecturer->name ?? '-' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if(method_exists($schedules, 'links'))
            <div class="text-center mt-3">
                {!! $schedules->links() !!}
            </div>
            @endif
        </div>
        <div id="matrix-view" style="display: none;">
            <div class="alert alert-info">Tampilan matriks hanya tersedia untuk 1 kelas. Silakan pilih kelas terlebih dahulu.</div>
        </div>
    @endif
</div>
@stop