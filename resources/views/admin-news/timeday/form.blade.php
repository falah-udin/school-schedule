{!! Form::hidden('idtimeday', isset($timedays->id) ? $timedays->id : '', ['id' => 'idtimeday']) !!}

<div class="form-group">
    <label>Hari</label>
    {!! Form::select('days', $days, isset($timedays->days_id) ? $timedays->days_id : null, [
        'class' => 'select2 form-control custom-select', 
        'id' => 'days', 
        'required', 
        'placeholder' => 'Pilih Hari'
    ]) !!}
    <small class="text-muted">Pilih hari untuk jadwal ini</small>
</div>

<div class="form-group">
    <label>Waktu</label>
    {!! Form::select('times', $times, isset($timedays->times_id) ? $timedays->times_id : null, [
        'class' => 'select2 form-control custom-select', 
        'id' => 'times', 
        'required',
        'placeholder' => 'Pilih Waktu'
    ]) !!}
    <small class="text-muted">Pilih jam pelajaran</small>
</div>

<div class="form-group">
    <label>Informasi</label>
    <div class="alert alert-info">
        <i class="fa fa-info-circle"></i> 
        Ini adalah kombinasi <strong>Hari + Waktu</strong> yang akan menjadi slot jadwal.
        <br>Contoh: Senin + 07:00-07:40 = 1 slot jadwal.
    </div>
</div>

<button class="btn btn-primary">
    <i class="fa fa-save"></i> Simpan
</button>
<a href="{{ route('admin.timedays') }}" class="btn btn-warning">
    <i class="fa fa-arrow-left"></i> Kembali
</a>