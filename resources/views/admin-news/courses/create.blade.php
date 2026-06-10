@extends('admin-news.layouts.master')

@section('title')
{{ $title = 'Tambah Mata Pelajaran' }}
@stop

@section('content')
<div class="container-fluid">
    <div class="card">
        <div class="card-body">
            <h4 class="card-title">{{ $title }}</h4>
            @include('admin-news._partials.notifications')
            
            <form method="POST" action="{{ route('admin.courses.store') }}">
                <input type="hidden" name="_token" value="{{ csrf_token() }}">
                
                <div class="form-group">
                    <label>Nama Mata Pelajaran</label>
                    <input type="text" name="namecourses" class="form-control" required placeholder="Masukkan Nama Mata Pelajaran">
                </div>
                
                <div class="form-group">
                    <label>Jam per Minggu (JP)</label>
                    <select name="hours_per_week" class="form-control">
                        <option value="1">1 JP (35 menit)</option>
                        <option value="2" selected>2 JP (70 menit)</option>
                        <option value="3">3 JP (105 menit)</option>
                        <option value="4">4 JP (140 menit)</option>
                        <option value="5">5 JP (175 menit)</option>
                        <option value="6">6 JP (210 menit)</option>
                    </select>
                    <small class="text-muted">Total jam dalam satu minggu</small>
                </div>
                
                <div class="form-group">
                    <label>Minimal Jam per Hari (JP)</label>
                    <select name="min_hours_per_day" class="form-control">
                        <option value="1" selected>1 JP (35 menit)</option>
                        <option value="2">2 JP (70 menit)</option>
                        <option value="3">3 JP (105 menit)</option>
                        <option value="4">4 JP (140 menit)</option>
                    </select>
                    <small class="text-muted">
                        Minimal jam dalam sehari. Jika disamakan dengan Max, maka jadwal hanya dalam 1 hari.
                    </small>
                </div>
                
                <div class="form-group">
                    <label>Maksimal Jam per Hari (JP)</label>
                    <select name="max_hours_per_day" class="form-control">
                        <option value="1">1 JP (35 menit)</option>
                        <option value="2" selected>2 JP (70 menit)</option>
                        <option value="3">3 JP (105 menit)</option>
                        <option value="4">4 JP (140 menit)</option>
                    </select>
                    <small class="text-muted">Maksimal jam dalam sehari</small>
                </div>
                
                <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Simpan</button>
                <a href="{{ route('admin.courses') }}" class="btn btn-warning">Batal</a>
                
            </form>
        </div>
    </div>
</div>
@stop