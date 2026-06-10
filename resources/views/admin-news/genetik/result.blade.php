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

    <!-- ==================== RINGKASAN KROMOSOM (GRID CARD) ==================== -->
    @if(!empty($data_kromosom))
    <div class="summary-header no-print">
        <strong><i class="fa fa-chart-bar"></i> Pilih Kromosom:</strong>
        <div class="kromosom-grid">
            @foreach ($data_kromosom as $krom)
                @php
                    $totalSlots = 468;
                    $achieved = App\Models\Schedule::where('type', $krom['type'])->count();
                    $percentage = $totalSlots > 0 ? round(($achieved / $totalSlots) * 100) : 0;
                    $isActive = ($id == $krom['type']);
                    
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
                    </div>
                </a>
            @endforeach
        </div>
        <div class="mt-2 small text-muted">
            <i class="fa fa-info-circle"></i> Target per kromosom: 468 JP (12 kelas × 39 JP) | Klik card untuk melihat jadwal kromosom tersebut
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
                        @foreach($schedules as $key => $s)
                        <tr>
                            <td>{{ $key + 1 + ($schedules->currentPage() - 1) * $schedules->perPage() }}</td>
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
            <div class="text-center mt-3">
                {!! $schedules->appends(Input::all())->render() !!}
            </div>
        </div>
        <div id="matrix-view" style="display: none;">
            <div class="alert alert-info">Tampilan matriks hanya tersedia untuk 1 kelas. Silakan pilih kelas terlebih dahulu.</div>
        </div>
    @endif
</div>
@stop