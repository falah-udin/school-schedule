@extends('admin-news.layouts.master')

@section('title')
{{ $title = 'Manajemen Waktu (Hari + Jam)' }}
@stop

@section('style')
<link href="{{ asset('new_template/assets/libs/tablesaw/dist/tablesaw.css') }}" rel="stylesheet">
<style>
    .generate-box {
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 20px;
    }
    .generate-box h6 { margin-bottom: 15px; color: #17a2b8; }
    .btn-edit-time { background-color: #ffc107; color: #333; }
    .btn-edit-timeday { background-color: #17a2b8; color: white; }
</style>
@stop

@section('content')
<div class="page-breadcrumb">
    <div class="row">
        <div class="col-5 align-self-center">
            <h4 class="page-title">{{ $title }}</h4>
        </div>
        <div class="col-7 align-self-center">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item active">{{ $title }}</li>
                </ol>
            </nav>
        </div>
    </div>
</div>

<div class="container-fluid">
    @include('admin-news._partials.notifications')
    
    <div class="card">
        <div class="card-body">
            
            {{-- INFO DURASI JP --}}
            @php
                $jpDuration = App\Models\Setting::get('jp_duration', 40);
                $jpPerDay = App\Models\Setting::get('jp_per_day', 6);
            @endphp
            
            <div class="alert alert-info">
                <i class="fa fa-info-circle"></i> 
                <strong>Konfigurasi Saat Ini:</strong> 
                1 JP = {{ $jpDuration }} menit | 
                {{ $jpPerDay }} JP/hari | 
                Total slot = {{ $jpPerDay * 6 }} slot
            </div>
            
            {{-- TOMBOL REGENERATE TIMEDAYS --}}
            <div class="generate-box">
                <h6><i class="fa fa-refresh"></i> Refresh Kombinasi Hari & Waktu</h6>
                <p class="text-muted">Mengambil data dari tabel Times dan Days untuk membuat kombinasi hari + waktu</p>
                <a href="{{ route('admin.timedays.regenerate') }}" class="btn btn-info" onclick="return confirm('Data timedays akan di-refresh. Lanjutkan?')">
                    <i class="fa fa-refresh"></i> Refresh Timedays
                </a>
                <small class="text-muted ml-3">
                    Akan menghasilkan {{ $jpPerDay * 6 }} slot waktu ({{ $jpPerDay }} JP × 6 hari)
                </small>
            </div>

            {{-- TABEL TIMEDAYS (HARI + WAKTU) --}}
            <div class="table-responsive">
                <table class="no-wrap table-bordered table-hover table" data-tablesaw>
                    <thead class="bg-info text-white">
                        <tr>
                            <th>No</th>
                            <th>Hari</th>
                            <th>Jam Mulai</th>
                            <th>Jam Selesai</th>
                            <th>Range</th>
                            <th colspan="2">Option</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($timedays as $key => $td)
                        <tr>
                            <td>{{ ($timedays->currentpage()-1) * $timedays->perpage() + $key + 1 }}</td>
                            <td>{{ $td->day->name_day ?? '-' }}</td>
                            <td>{{ $td->time->time_begin ?? '-' }}</td>
                            <td>{{ $td->time->time_finish ?? '-' }}</td>
                            <td>{{ $td->time->range ?? '-' }}</td>
                            <td>
                                {{-- EDIT DATA TIME (WAKTU DASAR) --}}
                                <a class="btn btn-warning btn-sm" href="{{ route('admin.time.edit', $td->time->id) }}" title="Ubah waktu dasar">
                                    <i class="ti-pencil"></i> Edit Waktu Dasar
                                </a>
                            </td>
                            <td>
                                {{-- EDIT DATA TIMEDAY (KOMBINASI HARI + WAKTU) --}}
                                <a class="btn btn-info btn-sm" href="{{ route('admin.timeday.edit', $td->id) }}" title="Ubah kombinasi hari dan waktu">
                                    <i class="ti-settings"></i> Edit Kombinasi
                                </a>
                                
                                {!! Form::open(['route' => ['admin.timedays.delete', $td->id], 'method' => 'DELETE', 'style' => 'display:inline-block', 'onsubmit' => 'return confirm("Yakin hapus kombinasi ini?")']) !!}
                                <button type="submit" class="btn btn-danger btn-sm" title="Hapus kombinasi">
                                    <i class="ti-trash"></i> Hapus
                                </button>
                                {!! Form::close() !!}
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            
            {!! $timedays->appends(Input::all())->render() !!}
            
            {{-- KETERANGAN --}}
            <div class="alert alert-secondary mt-3">
                <i class="fa fa-info-circle"></i> 
                <strong>Keterangan:</strong><br>
                • <strong>Edit Waktu Dasar</strong> = Mengubah waktu di tabel Times (berdampak ke semua hari)<br>
                • <strong>Edit Kombinasi</strong> = Mengubah relasi hari dan waktu (tabel Timedays)<br>
                • <strong>Hapus</strong> = Menghapus kombinasi hari & waktu ini
            </div>
            
        </div>
    </div>
</div>
@stop