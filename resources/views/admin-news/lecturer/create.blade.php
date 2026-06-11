@extends('admin-news.layouts.master')

@section('title')
{{ $title = 'Tambah Dosen' }}
@stop

@section('content')

<div class="container-fluid">
  <div class="row"> 
    <div class="col-12">
      <div class="card">
        <div class="card-body">
            @include('admin-news._partials.notifications')
            <h4 class="page-title">{{ $title }}</h4>

            {{-- PERBAIKAN: Tambahkan route untuk CREATE --}}
            {!! Form::open(['role' => 'form', 'route' => 'admin.lecturer.store', 'files' => true, 'id' => 'form-register']) !!}
                @include('admin-news.lecturer.form')
            {!! Form::close() !!}
        </div>
    </div>
    </div>  
  </div>  
</div>
@stop