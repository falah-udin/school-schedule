@extends('admin-news.layouts.master')

@section('title')
{{ $title = 'Mata Pelajaran' }}
@stop

@section('content')
<div class="container-fluid">
    <div class="card">
        <div class="card-body">
            <h4>{{ $title }}</h4>
            
            {{-- ALERT INFO SEBELUM MENGHAPUS --}}
            <div class="alert alert-info">
                <i class="fa fa-info-circle"></i> 
                <strong>Informasi:</strong> Mata pelajaran yang sudah digunakan di data <strong>Pengampu (Teach)</strong> 
                tidak bisa dihapus langsung. Hapus data Pengampu terlebih dahulu.
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <a class="btn btn-info" href="{{ route('admin.courses.create') }}">
                        <i class="fa fa-plus"></i> Tambah Mata Pelajaran
                    </a>
                </div>
                <div class="col-md-6">
                    <form method="GET" class="form-inline float-right">
                        <input type="text" name="searchname" class="form-control" placeholder="Cari Pelajaran" value="{{ request()->get('searchname') }}">
                        <button type="submit" class="btn btn-success">
                            <i class="fa fa-search"></i> Search
                        </button>
                    </form>
                </div>
            </div>
            
            <table class="table table-bordered">
                <thead class="bg-info text-white">
                    <tr>
                        <th>No</th>
                        <th>Mata Pelajaran</th>
                        <th>Jam/Minggu</th>
                        <th>Min/Hari</th>
                        <th>Max/Hari</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($courses as $key => $course)
                    @php
                        $teachCount = App\Models\Teach::where('courses_id', $course->id)->count();
                        $canDelete = $teachCount == 0;
                    @endphp
                    <tr>
                        <td align="center">{{ $courses->firstItem() + $key }}</td>
                        <td><strong>{{ $course->name }}</strong></td>
                        <td align="center">{{ $course->hours_per_week ?? 2 }} JP</td>
                        <td align="center">{{ $course->min_hours_per_day ?? 1 }} JP</td>
                        <td align="center">{{ $course->max_hours_per_day ?? 2 }} JP</td>
                        <td align="center">
                            @if($teachCount > 0)
                                <span class="badge badge-warning">
                                    <i class="fa fa-chain"></i> Digunakan di {{ $teachCount }} Pengampu
                                </span>
                            @else
                                <span class="badge badge-success">
                                    <i class="fa fa-check"></i> Aman dihapus
                                </span>
                            @endif
                        </td>
                        <td align="center">
                            <a class="btn btn-warning btn-sm" href="{{ route('admin.courses.edit', $course->id) }}">
                                <i class="fa fa-edit"></i> Edit
                            </a>
                            
                            @if($teachCount > 0)
                                <button class="btn btn-danger btn-sm" disabled style="opacity:0.6;" title="Tidak bisa dihapus karena sudah digunakan di Pengampu">
                                    <i class="fa fa-trash"></i> Hapus
                                </button>
                                <a href="{{ route('admin.teachs') }}?search={{ $course->name }}" class="btn btn-info btn-sm" target="_blank">
                                    <i class="fa fa-eye"></i> Lihat Pengampu
                                </a>
                            @else
                                <a class="btn btn-danger btn-sm" href="{{ route('admin.courses.delete', $course->id) }}" onclick="return confirm('Yakin hapus mata pelajaran {{ $course->name }}?')">
                                    <i class="fa fa-trash"></i> Hapus
                                </a>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            
            <div class="text-center">
                {{ $courses->appends(request()->all())->links() }}
            </div>
        </div>
    </div>
</div>
@stop
