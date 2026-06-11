{{-- PERBAIKAN: $lecturers diganti menjadi $lecturer (SINGULAR) --}}
{!! Form::hidden('idlecturer', isset($lecturer->id) ? $lecturer->id : '', ['class' => 'form-control', 'id' => 'idlecturer']) !!}

<div class="form-group">
    <label>
        Nama
    </label>
    {!! Form::text('name', null, ['class' => 'form-control', 'required', 'maxlength' => '100', 'placeholder' => 'Masukkan Nama','id' => 'name','style' => 'width: 100%; height:36px;']) !!}
    <label id="type-error" class="error" for="type" style="display: none;">This field is required.</label>
</div>
<button class="btn btn-primary">
    Simpan
</button>