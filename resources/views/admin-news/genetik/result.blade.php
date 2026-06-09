@extends('admin-news.layouts.master')

@section('title')
{{ $title = 'Jadwal Per Kelas - ' . ($filterClass ?? 'Semua') }}
@stop

@section('style')
<style>
    .matrix-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 12px;
    }
    .matrix-table th, .matrix-table td {
        border: 1px solid #ddd;
        padding: 10px 5px;
        text-align: center;
        vertical-align: top;
    }
    .matrix-table th {
        background: linear-gradient(135deg, #17a2b8 0%, #0f6b7a 100%);
        color: white;
        font-weight: bold;
        position: sticky;
        top: 0;
    }
    .time-col {
        background-color: #f8f9fa;
        font-weight: bold;
        width: 90px;
    }
    .subject-cell {
        min-height: 65px;
    }
    .subject-name {
        font-weight: bold;
        color: #2c3e50;
        font-size: 12px;
    }
    .teacher-name {
        font-size: 10px;
        color: #7f8c8d;
        margin-top: 3px;
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
    .legend {
        background: #f8f9fa;
        padding: 8px 15px;
        border-radius: 5px;
        margin-bottom: 15px;
        font-size: 11px;
    }
    @media print {
        .no-print, .class-nav, .btn, .alert, .page-breadcrumb {
            display: none !important;
        }
        .matrix-table th {
            background: #ccc !important;
            color: black !important;
        }
    }
</style>
@stop

@section('content')
<div class="page-breadcrumb no-print">
    <div class="row">
        <div class="col-5 align-self-center">
            <h4 class="page-title">Jadwal Pelajaran</h4>
        </div>
        <div class="col-7 align-self-center">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.generates.result', $id) }}">Hasil Generate</a></li>
                    <li class="breadcrumb-item active">Jadwal Per Kelas</li>
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
            </div>
            <div class="col-md-6 text-right no-print">
                <button onclick="window.print()" class="btn btn-light">
                    <i class="fa fa-print"></i> Print
                </button>
                <a href="{{ route('admin.generates.excel', $id) }}" class="btn btn-light">
                    <i class="fa fa-download"></i> Excel
                </a>
            </div>
        </div>
    </div>

    <!-- Navigation Kelas -->
    <div class="class-nav no-print">
        <strong>Pilih Kelas:</strong>
        <a href="{{ route('admin.generates.result.matrix', ['id' => $id]) }}" 
           class="btn btn-sm @if(empty($filterClass)) btn-primary @else btn-outline-info @endif">
            🏫 Semua Kelas
        </a>
        @foreach($classCounts as $class)
            <a href="{{ route('admin.generates.result.matrix', ['id' => $id, 'class' => $class->name]) }}" 
               class="btn btn-sm @if($filterClass == $class->name) btn-primary @else btn-outline-info @endif">
                📚 {{ $class->name }}
            </a>
        @endforeach
    </div>

    @if(!empty($filterClass))
        <!-- TAMPILAN MATRIKS UNTUK 1 KELAS -->
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
                            <td class="subject-cell">
                                @if($schedule)
                                    <div class="subject-name">{{ $schedule['course'] }}</div>
                                    <div class="teacher-name">{{ $schedule['teacher'] }}</div>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <!-- TAMPILAN LIST UNTUK SEMUA KELAS -->
        <div class="table-responsive">
            <table class="matrix-table">
                <thead>
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
                        <td>{{ $key + 1 }}</td>
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
    @endif
</div>
@stop