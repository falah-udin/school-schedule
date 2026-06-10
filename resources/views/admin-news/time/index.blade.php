@extends('admin-news.layouts.master')

@section('title')
{{ $title = 'Data Waktu' }}
@stop

@section('style')
<link href="{{ asset('new_template/assets/libs/tablesaw/dist/tablesaw.css') }}" rel="stylesheet">
<link href="{{ asset('new_template/assets/libs/sweetalert2/dist/sweetalert2.min.css') }}" rel="stylesheet">
<style>
    form.deleteedition { display: inline-block; }
    .generate-box {
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 20px;
    }
    .generate-box h6 { margin-bottom: 15px; color: #17a2b8; }
</style>
@stop

@section('script')
<script src="{{ asset('new_template/assets/libs/tablesaw/dist/tablesaw.jquery.js') }}"></script>
<script src="{{ asset('new_template/assets/libs/tablesaw/dist/tablesaw-init.js') }}"></script>
<script src="{{ asset('new_template/assets/libs/sweetalert2/dist/sweetalert2.all.min.js') }}" aria-hidden="true"></script>
<script src="{{ asset('new_template/assets/libs/sweetalert2/sweet-alert.init.js') }}" aria-hidden="true"></script>
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
            
            {{-- ========== ALERT PERINGATAN ========== --}}
            <div class="alert alert-info">
                <i class="fa fa-info-circle"></i> 
                <strong>Informasi Penting:</strong>
                <ul class="mb-0 mt-2">
                    <li>Waktu yang sudah digunakan di <strong>Timedays</strong> (kombinasi hari & waktu) <strong>TIDAK BISA</strong> dihapus langsung.</li>
                    <li>Gunakan tombol <strong>"Generate Sekarang"</strong> untuk mereset semua data waktu (akan menghapus semua timedays terlebih dahulu).</li>
                    <li>Atau hapus manual data Timedays yang terkait melalui menu <strong>Manajemen Waktu (Hari + Jam)</strong>.</li>
                </ul>
            </div>
            
            {{-- ========== BOX GENERATE OTOMATIS ========== --}}
            <div class="generate-box">
                <h6><i class="fa fa-magic"></i> Generate Jadwal Waktu Otomatis</h6>
                
                <form method="GET" action="{{ route('admin.times.generate') }}">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Durasi 1 JP (menit)</label>
                                <input type="number" name="duration" class="form-control" 
                                    value="{{ App\Models\Setting::get('jp_duration', 40) }}" min="30" max="60" step="5">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Jam Mulai Sekolah</label>
                                <input type="time" name="start_time" class="form-control" 
                                    value="{{ App\Models\Setting::get('start_time', '07:00') }}">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>JP per Hari</label>
                                <input type="number" name="jp_per_day" class="form-control" 
                                    value="{{ App\Models\Setting::get('jp_per_day', 6) }}" min="4" max="10">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Durasi Istirahat (menit)</label>
                                <input type="number" name="break_duration" class="form-control" 
                                    value="{{ App\Models\Setting::get('break_duration', 15) }}" min="5" max="30">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Istirahat Setelah JP ke-</label>
                                <input type="number" name="break_after" class="form-control" 
                                    value="{{ App\Models\Setting::get('break_after', 3) }}" min="1" max="5">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <button type="submit" class="btn btn-info" onclick="return confirm('Data waktu lama akan dihapus. Lanjutkan?')">
                                <i class="fa fa-magic"></i> Generate Sekarang
                            </button>
                            <small class="text-muted ml-3">
                                <i class="fa fa-info-circle"></i>
                                Akan menghasilkan {{ App\Models\Setting::get('jp_per_day', 6) }} JP
                            </small>
                        </div>
                    </div>
                </form>
            </div>
            
            {{-- ========== TOMBOL TAMBAH DATA ========== --}}
            <div class="row mb-3">
                <div class="col align-self-center">
                    <h6 class="card-subtitle">Daftar Waktu Pelajaran</h6>
                    <a class="btn btn-info" href="{{ route('admin.time.create') }}">
                        <i class="fa fa-plus"></i> Tambah Data
                    </a>
                </div>
            </div>

            {{-- ========== TABEL TIME ========== --}}
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead class="bg-info text-white">
                        <tr>
                            <th>No</th>
                            <th>Waktu Mulai</th>
                            <th>Waktu Selesai</th>
                            <th>Range</th>
                            <th>Option</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($times as $key => $time)
                        <tr>
                            <td>{{ ($times->currentpage()-1) * $times->perpage() + $key + 1 }}</td>
                            <td>{{ $time->time_begin }}</td>
                            <td>{{ $time->time_finish }}</td>
                            <td>{{ $time->range }}</td>
                            <td>
                                <a class="btn btn-warning btn-sm" href="{{ route('admin.time.edit', $time->id) }}">
                                    <i class="fa fa-edit"></i> Ubah
                                </a>
                                {!! Form::open(['route' => ['admin.time.delete', $time->id], 'method' => 'DELETE', 'style' => 'display:inline-block', 'onsubmit' => 'return confirm("Yakin hapus?")']) !!}
                                <button type="submit" class="btn btn-danger btn-sm">
                                    <i class="fa fa-trash"></i> Hapus
                                </button>
                                {!! Form::close() !!}
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            
            {!! $times->appends(Input::all())->render() !!}
            
        </div>
    </div>
</div>
@stop