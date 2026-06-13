{!! Form::hidden('idcourse', isset($courses->id) ? $courses->id : '', ['id' => 'idcourse']) !!}

<div class="form-group">
    <label>Nama Mata Pelajaran</label>
    {!! Form::text('namecourses', isset($courses->name) ? $courses->name : '', ['class' => 'form-control', 'required', 'placeholder' => 'Masukkan Nama Mata Pelajaran']) !!}
</div>

@php
    $jpDuration = App\Models\Setting::get('jp_duration', 40);
    $jpPerDay = App\Models\Setting::get('jp_per_day', 6);
@endphp

<div class="row">
    <div class="col-md-4">
        <div class="form-group">
            <label>Jam per Minggu (JP)</label>
            <small class="text-muted d-block">1 JP = {{ $jpDuration }} menit</small>
            {!! Form::select('hours_per_week', [
                0 => "0 JP (Tidak dijadwalkan / Offline)",
                1 => "1 JP ({$jpDuration} menit)",
                2 => "2 JP (" . (2 * $jpDuration) . " menit)",
                3 => "3 JP (" . (3 * $jpDuration) . " menit)",
                4 => "4 JP (" . (4 * $jpDuration) . " menit)",
                5 => "5 JP (" . (5 * $jpDuration) . " menit)",
                6 => "6 JP (" . (6 * $jpDuration) . " menit)",
            ], isset($courses->hours_per_week) ? $courses->hours_per_week : 2, ['class' => 'form-control']) !!}
            <small class="text-muted text-danger">Pilih 0 jika mapel tidak perlu dijadwalkan</small>
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            <label>Minimal Jam per Hari (JP)</label>
            {!! Form::select('min_hours_per_day', [
                0 => "0 JP (Tidak ada minimal)",
                1 => "1 JP ({$jpDuration} menit)",
                2 => "2 JP (" . (2 * $jpDuration) . " menit)",
                3 => "3 JP (" . (3 * $jpDuration) . " menit)",
                4 => "4 JP (" . (4 * $jpDuration) . " menit)",
            ], isset($courses->min_hours_per_day) ? $courses->min_hours_per_day : 1, ['class' => 'form-control']) !!}
            <small class="text-muted">Minimal jam per hari. Pilih 0 jika tidak ada minimal</small>
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            <label>Maksimal Jam per Hari (JP)</label>
            <small class="text-muted d-block">Maksimal {{ $jpPerDay }} JP/hari</small>
            {!! Form::select('max_hours_per_day', [
                0 => "0 JP (Tidak boleh dijadwalkan)",
                1 => "1 JP ({$jpDuration} menit)",
                2 => "2 JP (" . (2 * $jpDuration) . " menit)",
                3 => "3 JP (" . (3 * $jpDuration) . " menit)",
                4 => "4 JP (" . (4 * $jpDuration) . " menit)",
            ], isset($courses->max_hours_per_day) ? min($courses->max_hours_per_day, $jpPerDay) : 2, ['class' => 'form-control']) !!}
            <small class="text-muted">Maksimal jam per hari. Pilih 0 jika mapel tidak boleh dijadwalkan</small>
        </div>
    </div>
</div>

<button class="btn btn-primary"><i class="fa fa-save"></i> Simpan</button>
<a href="{{ route('admin.courses') }}" class="btn btn-warning"><i class="fa fa-arrow-left"></i> Kembali</a>