{!! Form::hidden('idtimes', isset($times->id) ? $times->id : '', ['id' => 'idtimes']) !!}

<div class="form-group">
    <label>Waktu Mulai</label>
    {!! Form::time('time_begin', null, ['class' => 'form-control', 'required', 'placeholder' => 'Masukkan Waktu Mulai']) !!}
    <small class="text-muted">Contoh: 07:00</small>
</div>

<div class="form-group">
    <label>Waktu Selesai</label>
    {!! Form::time('time_finish', null, ['class' => 'form-control', 'required', 'placeholder' => 'Masukkan Waktu Selesai']) !!}
    <small class="text-muted">Contoh: 07:40</small>
</div>

<div class="form-group">
    <label>Durasi 1 JP Saat Ini</label>
    <input type="text" class="form-control" value="{{ App\Models\Setting::get('jp_duration', 40) }} menit" readonly disabled>
    <small class="text-muted">Untuk konsistensi, gunakan generate otomatis</small>
</div>

<button class="btn btn-primary"><i class="fa fa-save"></i> Simpan</button>
<a href="{{ route('admin.times') }}" class="btn btn-warning"><i class="fa fa-arrow-left"></i> Kembali</a>