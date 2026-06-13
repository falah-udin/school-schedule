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
                
                @php
                    $jpDuration = App\Models\Setting::get('jp_duration', 40);
                    $jpPerDay = App\Models\Setting::get('jp_per_day', 6);
                @endphp
                
                <div class="form-group">
                    <label>Nama Mata Pelajaran</label>
                    <input type="text" name="namecourses" class="form-control" required value="{{ $courses->name }}">
                </div>
                
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Jam per Minggu (JP)</label>
                            <small class="text-muted d-block">1 JP = {{ $jpDuration }} menit</small>
                            <select name="hours_per_week" class="form-control">
                                <option value="0" {{ ($courses->hours_per_week ?? 2) == 0 ? 'selected' : '' }}>0 JP (Tidak dijadwalkan / Offline)</option>
                                <option value="1" {{ ($courses->hours_per_week ?? 2) == 1 ? 'selected' : '' }}>1 JP ({{ $jpDuration }} menit)</option>
                                <option value="2" {{ ($courses->hours_per_week ?? 2) == 2 ? 'selected' : '' }}>2 JP ({{ 2 * $jpDuration }} menit)</option>
                                <option value="3" {{ ($courses->hours_per_week ?? 2) == 3 ? 'selected' : '' }}>3 JP ({{ 3 * $jpDuration }} menit)</option>
                                <option value="4" {{ ($courses->hours_per_week ?? 2) == 4 ? 'selected' : '' }}>4 JP ({{ 4 * $jpDuration }} menit)</option>
                                <option value="5" {{ ($courses->hours_per_week ?? 2) == 5 ? 'selected' : '' }}>5 JP ({{ 5 * $jpDuration }} menit)</option>
                                <option value="6" {{ ($courses->hours_per_week ?? 2) == 6 ? 'selected' : '' }}>6 JP ({{ 6 * $jpDuration }} menit)</option>
                            </select>
                            <small class="text-muted text-danger">Pilih 0 jika mapel tidak perlu dijadwalkan</small>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Minimal Jam per Hari (JP)</label>
                            <select name="min_hours_per_day" class="form-control">
                                <option value="0" {{ ($courses->min_hours_per_day ?? 1) == 0 ? 'selected' : '' }}>0 JP (Tidak ada minimal)</option>
                                <option value="1" {{ ($courses->min_hours_per_day ?? 1) == 1 ? 'selected' : '' }}>1 JP ({{ $jpDuration }} menit)</option>
                                <option value="2" {{ ($courses->min_hours_per_day ?? 1) == 2 ? 'selected' : '' }}>2 JP ({{ 2 * $jpDuration }} menit)</option>
                                <option value="3" {{ ($courses->min_hours_per_day ?? 1) == 3 ? 'selected' : '' }}>3 JP ({{ 3 * $jpDuration }} menit)</option>
                                <option value="4" {{ ($courses->min_hours_per_day ?? 1) == 4 ? 'selected' : '' }}>4 JP ({{ 4 * $jpDuration }} menit)</option>
                            </select>
                            <small class="text-muted">Minimal jam per hari. Pilih 0 jika tidak ada minimal</small>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Maksimal Jam per Hari (JP)</label>
                            <small class="text-muted d-block">Maksimal {{ $jpPerDay }} JP/hari</small>
                            <select name="max_hours_per_day" class="form-control">
                                <option value="0" {{ ($courses->max_hours_per_day ?? 2) == 0 ? 'selected' : '' }}>0 JP (Tidak boleh dijadwalkan)</option>
                                <option value="1" {{ ($courses->max_hours_per_day ?? 2) == 1 ? 'selected' : '' }}>1 JP ({{ $jpDuration }} menit)</option>
                                <option value="2" {{ ($courses->max_hours_per_day ?? 2) == 2 ? 'selected' : '' }}>2 JP ({{ 2 * $jpDuration }} menit)</option>
                                <option value="3" {{ ($courses->max_hours_per_day ?? 2) == 3 ? 'selected' : '' }}>3 JP ({{ 3 * $jpDuration }} menit)</option>
                                <option value="4" {{ ($courses->max_hours_per_day ?? 2) == 4 ? 'selected' : '' }}>4 JP ({{ 4 * $jpDuration }} menit)</option>
                            </select>
                            <small class="text-muted">Maksimal jam per hari. Pilih 0 jika mapel tidak boleh dijadwalkan</small>
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Update</button>
                <a href="{{ route('admin.courses') }}" class="btn btn-warning"><i class="fa fa-arrow-left"></i> Batal</a>
                
            </form>
        </div>
    </div>
</div>
@stop