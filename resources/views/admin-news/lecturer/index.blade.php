@extends('admin-news.layouts.master')

@section('title')
{{ $title = 'Data Guru' }}
@stop

@section('style')
<link href="{{ asset('new_template/assets/libs/tablesaw/dist/tablesaw.css') }}" rel="stylesheet">
<link href="{{ asset('new_template/assets/libs/sweetalert2/dist/sweetalert2.min.css') }}" rel="stylesheet">

<style>
    form.deleteedition{
        display:inline-block;
    }
</style>
@stop

@section('script')

<script src="{{ asset('new_template/assets/libs/tablesaw/dist/tablesaw.jquery.js') }}"></script>
<script src="{{ asset('new_template/assets/libs/tablesaw/dist/tablesaw-init.js') }}"></script>
<script src="{{ asset('new_template/assets/libs/sweetalert2/dist/sweetalert2.all.min.js') }}" aria-hidden="true"></script>
<script src="{{ asset('new_template/assets/libs/sweetalert2/sweet-alert.init.js') }}" aria-hidden="true"></script>

<script>
    // SweetAlert untuk konfirmasi delete
    $(document).ready(function() {
        $('.sa-removeData').click(function(e) {
            e.preventDefault();
            var formId = $(this).data('file');
            var form = $('#' + formId);
            
            Swal.fire({
                title: 'Yakin ingin menghapus?',
                text: "Data guru yang dihapus tidak dapat dikembalikan!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Ya, hapus!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });
    });
</script>
@stop

@section('content')    
<div class="page-breadcrumb">
  <div class="row">
      <div class="col align-self-center">
          <h4 class="page-title">{{ $title }}</h4>
          <div class="d-flex align-items-center">
          </div>
      </div>

      <div class="col-7 align-self-center">
          <div class="d-flex no-block justify-content-end align-items-center">
              <nav aria-label="breadcrumb">
                  <ol class="breadcrumb">
                      <li class="breadcrumb-item">
                          <a href="{{ route('admin.dashboard') }}">Dashboard</a>
                      </li>
                      <li class="breadcrumb-item active" aria-current="page">{{ $title }}</li>
                  </ol>
              </nav>
          </div>
      </div>
  </div>
</div>

<div class="container-fluid">
    <!-- Notifikasi -->
    @include('admin-news._partials.notifications')
    
    <div class="card">
        <div class="card-body">
            <div class="row mb-3">
                <div class="col align-self-center">
                    <div class="dt-buttons">
                        <h6 class="card-subtitle">
                            Manajemen Data Guru / Dosen
                        </h6>
                        <a class="btn waves-effect waves-light btn-info mr-1" href="{{ route('admin.lecturer.create') }}">
                            <i class="fa fa-plus"></i>
                            Tambah Data
                        </a>
                    </div>
                </div>
                <div id="file_export_filter" class="dataTables_filter ml-2">
                    {!! Form::open(['role' => 'form', 'route' => 'admin.lecturers', 'method' => 'get']) !!}
                    <label>
                        {!! Form::text('searchname', Input::get('searchname') ?: null, ['class' => 'form-control mt-3', 'placeholder' => 'Cari Nama Guru...']) !!}
                    </label>
                    <button type="submit" class="btn waves-effect waves-light btn-success ml-3">
                        <i class="fa fa-search"></i> Search
                    </button>
                    @if(Input::get('searchname'))
                        <a href="{{ route('admin.lecturers') }}" class="btn waves-effect waves-light btn-secondary ml-2">
                            <i class="fa fa-refresh"></i> Reset
                        </a>
                    @endif
                    {!! Form::close() !!}
                </div>
            </div>  
            
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead>
                        <tr class="bg-info text-white">
                            <th width="5%" class="text-center">No.</th>
                            <th class="text-center">Nama Guru</th>
                            <th width="20%" class="text-center">Opsi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($lecturers as $key => $lecturer) 
                        <tr>
                            <td class="text-center">{{ ($lecturers->currentpage()-1) * $lecturers->perpage() + $key + 1 }}</td>
                            <td>{{ $lecturer->name }}</td>
                            <td class="text-center">
                                <a class="btn btn-warning btn-sm" href="{{ route('admin.lecturer.edit', $lecturer->id) }}">
                                    <i class="ti-pencil"></i> Ubah
                                </a>
                                
                                {!! Form::model($lecturer, ['route' => ['admin.lecturer.delete', $lecturer->id], 'id' => 'delete-'.$lecturer->id, 'class' => 'deleteedition']) !!}
                                {!! Form::hidden('_method', 'DELETE') !!}
                                {!! Form::button('<i class="ti-trash"></i> Hapus', [
                                    'type' => 'submit', 
                                    'class' => 'btn btn-danger btn-sm sa-removeData',
                                    'data-file' => 'delete-'.$lecturer->id
                                ]) !!}
                                {!! Form::close() !!}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="3" class="text-center text-muted">
                                <i class="fa fa-info-circle"></i> Tidak ada data guru.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <div class="row mt-3">
                <div class="col-12 d-flex justify-content-center">
                    {!! $lecturers->appends(Input::all())->render() !!}
                </div>
            </div>
        </div>
    </div>
</div>
@stop