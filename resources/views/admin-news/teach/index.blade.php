@extends('admin-news.layouts.master')

@section('title')
{{ $title = 'Data Pengampu (Guru Mengajar)' }}
@stop

@section('style')
<link href="{{ asset('new_template/assets/libs/tablesaw/dist/tablesaw.css') }}" rel="stylesheet">
<link href="{{ asset('new_template/assets/libs/sweetalert2/dist/sweetalert2.min.css') }}" rel="stylesheet">
<style>
    form.deleteedition { display: inline-block; }
    .filter-box {
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 20px;
    }
    .info-card {
        background: #e8f4f8;
        border-left: 4px solid #17a2b8;
        padding: 10px 15px;
        margin-bottom: 20px;
        border-radius: 5px;
    }
    .badge-mapel {
        background: #17a2b8;
        color: white;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 11px;
    }
</style>
@stop

@section('script')
<script src="{{ asset('new_template/assets/libs/tablesaw/dist/tablesaw.jquery.js') }}"></script>
<script src="{{ asset('new_template/assets/libs/tablesaw/dist/tablesaw-init.js') }}"></script>
<script src="{{ asset('new_template/assets/libs/sweetalert2/dist/sweetalert2.all.min.js') }}" aria-hidden="true"></script>
<script src="{{ asset('new_template/assets/libs/sweetalert2/sweet-alert.init.js') }}" aria-hidden="true"></script>
<script>
    $(document).ready(function() {
        $('#filter-class').on('change', function() {
            var className = $(this).val();
            if (className) {
                window.location.href = '{{ route("admin.teachs") }}?filter_class=' + className;
            } else {
                window.location.href = '{{ route("admin.teachs") }}';
            }
        });
        
        $('#filter-lecturer').on('change', function() {
            var lecturerName = $(this).val();
            if (lecturerName) {
                window.location.href = '{{ route("admin.teachs") }}?filter_lecturer=' + lecturerName;
            } else {
                window.location.href = '{{ route("admin.teachs") }}';
            }
        });
    });
</script>
@stop

@section('content')
<div class="page-breadcrumb">
    <div class="row">
        <div class="col-5 align-self-center">
            <h4 class="page-title">{{ $title }}</h4>
        </div>
        <div class="col-7 align-self-center">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item active">Data Pengampu</li>
                </ol>
            </nav>
        </div>
    </div>
</div>

<div class="container-fluid">
    @include('admin-news._partials.notifications')
    
    <div class="card">
        <div class="card-body">
            
            {{-- INFO CARD --}}
            <div class="info-card">
                <i class="fa fa-info-circle"></i> 
                <strong>Informasi Pengampu (Teach):</strong>
                <span class="ml-3">Total: <strong>{{ $teachs->total() }}</strong> data</span>
                <span class="ml-3">Guru: <strong>{{ $teachs->groupBy('lecturers_id')->count() }}</strong> orang</span>
                <span class="ml-3">Kelas: <strong>{{ $teachs->groupBy('class_room')->count() }}</strong> kelas</span>
            </div>
            
            {{-- FILTER BOX --}}
            <div class="filter-box">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label><i class="fa fa-filter"></i> Filter Berdasarkan Kelas</label>
                            <select id="filter-class" class="form-control">
                                <option value="">-- Semua Kelas --</option>
                                @php
                                    $classes = App\Models\Room::orderBy('name')->get();
                                @endphp
                                @foreach($classes as $class)
                                    <option value="{{ $class->name }}" {{ request()->get('filter_class') == $class->name ? 'selected' : '' }}>
                                        📚 {{ $class->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label><i class="fa fa-filter"></i> Filter Berdasarkan Guru</label>
                            <select id="filter-lecturer" class="form-control">
                                <option value="">-- Semua Guru --</option>
                                @php
                                    $lecturers = App\Models\Lecturer::orderBy('name')->get();
                                @endphp
                                @foreach($lecturers as $lecturer)
                                    <option value="{{ $lecturer->name }}" {{ request()->get('filter_lecturer') == $lecturer->name ? 'selected' : '' }}>
                                        👨‍🏫 {{ $lecturer->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label><i class="fa fa-search"></i> Reset Filter</label>
                            <a href="{{ route('admin.teachs') }}" class="btn btn-secondary btn-block">
                                <i class="fa fa-refresh"></i> Reset Filter
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            {{-- TOMBOL TAMBAH DATA --}}
            <div class="row mb-3">
                <div class="col align-self-center">
                    <a class="btn btn-info" href="{{ route('admin.teach.create') }}">
                        <i class="fa fa-plus"></i> Tambah Pengampu
                    </a>
                </div>
                <div class="col-auto">
                    <form method="GET" class="form-inline">
                        <div class="input-group">
                            <input type="text" name="search" class="form-control" placeholder="Cari Guru/Mapel/Kelas" value="{{ request()->get('search') }}">
                            <div class="input-group-append">
                                <button type="submit" class="btn btn-success">
                                    <i class="fa fa-search"></i> Cari
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            {{-- TABEL DATA --}}
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="bg-info text-white">
                        <tr>
                            <th width="5%">No</th>
                            <th width="25%">Nama Guru</th>
                            <th width="25%">Mata Pelajaran</th>
                            <th width="15%">Kelas</th>
                            <th width="15%">Jam/Minggu</th>
                            <th width="15%">Opsi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($teachs as $key => $teach)
                        <tr>
                            <td align="center">{{ $teachs->firstItem() + $key }}</td>
                            <td>
                                <i class="fa fa-user-circle"></i> 
                                <strong>{{ $teach->lecturer->name ?? '-' }}</strong>
                            </td>
                            <td>
                                <span class="badge-mapel">{{ $teach->course->name ?? '-' }}</span>
                            </td>
                            <td>
                                <i class="fa fa-building"></i> {{ $teach->room->name ?? '-' }}
                            </td>
                            <td align="center">
                                <span class="badge badge-primary">
                                    {{ $teach->course->hours_per_week ?? 2 }} JP
                                </span>
                            </td>
                            <td align="center" nowrap>
                                <a class="btn btn-warning btn-sm" href="{{ route('admin.teach.edit', $teach->id) }}" title="Edit">
                                    <i class="fa fa-edit"></i> Ubah
                                </a>
                                <a class="btn btn-danger btn-sm" href="{{ route('admin.teach.delete', $teach->id) }}" onclick="return confirm('Yakin hapus pengampu ini?')">
                                    <i class="fa fa-trash"></i> Hapus
                                </a>
                                <form id="delete-form-{{ $teach->id }}" action="{{ route('admin.teach.delete', $teach->id) }}" method="POST" style="display: none;">
                                    @csrf
                                    <input type="hidden" name="_method" value="DELETE">
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center text-danger">
                                <i class="fa fa-warning"></i> Belum ada data pengampu. Silakan tambah data.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            
            {{-- PAGINATION --}}
            <div class="text-center mt-3">
                {{ $teachs->appends(request()->all())->links() }}
            </div>
            
        </div>
    </div>
</div>
@stop