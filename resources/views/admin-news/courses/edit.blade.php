@extends('admin-news.layouts.master')

@section('title')
{{ $title = 'Edit Mata Pelajaran' }}
@stop

@section('content')
<div class="container-fluid">
    <div class="card">
        <div class="card-body">
            <h4 class="card-title">{{ $title }}</h4>
            @include('admin-news._partials.notifications')
            
            <form method="GET" action="{{ route('admin.courses.update', $courses->id) }}">
                
                <div class="form-group">
                    <label>Nama Mata Pelajaran</label>
                    <input type="text" name="namecourses" class="form-control" required value="{{ $courses->name }}">
                </div>
                
                <div class="form-group">
                    <label>Jam per Minggu (JP)</label>
                    <select name="hours_per_week" class="form-control">
                        @for($i = 1; $i <= 6; $i++)
                            <option value="{{ $i }}" {{ ($courses->hours_per_week ?? 2) == $i ? 'selected' : '' }}>
                                {{ $i }} JP ({{ $i * 35 }} menit)
                            </option>
                        @endfor
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Minimal Jam per Hari (JP)</label>
                    <select name="min_hours_per_day" class="form-control">
                        @for($i = 1; $i <= 4; $i++)
                            <option value="{{ $i }}" {{ ($courses->min_hours_per_day ?? 1) == $i ? 'selected' : '' }}>
                                {{ $i }} JP
                            </option>
                        @endfor
                    </select>
                    <small class="text-muted">Jika sama dengan Max, jadwal hanya dalam 1 hari</small>
                </div>
                
                <div class="form-group">
                    <label>Maksimal Jam per Hari (JP)</label>
                    <select name="max_hours_per_day" class="form-control">
                        @for($i = 1; $i <= 4; $i++)
                            <option value="{{ $i }}" {{ ($courses->max_hours_per_day ?? 2) == $i ? 'selected' : '' }}>
                                {{ $i }} JP
                            </option>
                        @endfor
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary">Update</button>
                <a href="{{ route('admin.courses') }}" class="btn btn-warning">Batal</a>
                
            </form>
        </div>
    </div>
</div>
@stop