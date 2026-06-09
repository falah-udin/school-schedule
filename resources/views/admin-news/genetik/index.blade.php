@extends('admin-news.layouts.master')

@section('title')
{{ $title = 'Generate Jadwal Otomatis' }}
@stop

@section('style')
<link href="{{ asset('new_template/assets/libs/tablesaw/dist/tablesaw.css') }}" rel="stylesheet">
<link href="{{ asset('new_template/assets/libs/sweetalert2/dist/sweetalert2.min.css') }}" rel="stylesheet">
<style>
    .info-card {
        background: #f8f9fa;
        border-left: 4px solid #17a2b8;
        padding: 15px;
        margin-bottom: 20px;
        border-radius: 5px;
    }
    .param-card {
        background: #fff;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 15px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    .param-label {
        font-weight: bold;
        color: #17a2b8;
        font-size: 16px;
    }
    .param-desc {
        font-size: 13px;
        color: #6c757d;
        margin-top: 5px;
    }
    .example-box {
        background: #e9ecef;
        padding: 10px;
        border-radius: 5px;
        font-family: monospace;
        font-size: 12px;
        margin-top: 10px;
    }
    .btn-generate {
        background: #28a745;
        color: white;
        font-size: 18px;
        font-weight: bold;
        padding: 12px;
        border-radius: 8px;
    }
    .btn-generate:hover {
        background: #218838;
        color: white;
    }
</style>
@stop

@section('script')
<script src="{{ asset('new_template/assets/libs/tablesaw/dist/tablesaw.jquery.js') }}"></script>
<script src="{{ asset('new_template/assets/libs/tablesaw/dist/tablesaw-init.js') }}"></script>
<script src="{{ asset('new_template/assets/libs/sweetalert2/dist/sweetalert2.all.min.js') }}" aria-hidden="true"></script>
<script src="{{ asset('new_template/assets/libs/sweetalert2/sweet-alert.init.js') }}" aria-hidden="true"></script>
<script>
    // Fungsi untuk menampilkan contoh nilai
    function setExample(type) {
        if (type === 'basic') {
            document.getElementById('kromosom').value = '2';
            document.getElementById('generasi').value = '2';
            document.getElementById('crossover').value = '0.5';
            document.getElementById('mutasi').value = '0.1';
        } else if (type === 'standard') {
            document.getElementById('kromosom').value = '3';
            document.getElementById('generasi').value = '3';
            document.getElementById('crossover').value = '0.8';
            document.getElementById('mutasi').value = '0.2';
        } else if (type === 'max') {
            document.getElementById('kromosom').value = '5';
            document.getElementById('generasi').value = '5';
            document.getElementById('crossover').value = '1';
            document.getElementById('mutasi').value = '0.3';
        }
    }
</script>
@stop

@section('content')
<!-- Breadcrumb -->
<div class="page-breadcrumb">
    <div class="row">
        <div class="col-5 align-self-center">
            <h4 class="page-title">{{ $title }}</h4>
        </div>
        <div class="col-7 align-self-center">
            <div class="d-flex no-block justify-content-end align-items-center">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Generate Jadwal</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>
</div>

<!-- Container fluid -->
<div class="container-fluid">
    @include('admin-news._partials.notifications')
    
    <!-- INFORMASI AWAL -->
    <div class="info-card">
        <h5><i class="fa fa-info-circle"></i> Apa yang dilakukan halaman ini?</h5>
        <p>Sistem akan membuat <strong>jadwal pelajaran otomatis</strong> berdasarkan data guru, mata pelajaran, dan kelas yang sudah Anda masukkan. 
        Algoritma akan mencoba berbagai kemungkinan dan memilih jadwal terbaik.</p>
        <hr>
        <p class="mb-0"><i class="fa fa-check-circle text-success"></i> <strong>Data saat ini:</strong> 
        {{ App\Models\Teach::count() }} data pengampu | 
        {{ App\Models\Room::count() }} kelas | 
        {{ App\Models\Lecturer::count() }} guru |
        {{ App\Models\Course::count() }} mata pelajaran</p>
    </div>

    <div class="card">
        <div class="card-body">
            <h5 class="card-title">⚙️ Parameter Generate Jadwal</h5>
            <h6 class="card-subtitle mb-3 text-muted">Atur parameter di bawah ini untuk memulai proses generate jadwal</h6>

            <!-- Tombol contoh pengisian -->
            <div class="row mb-4">
                <div class="col-12">
                    <label class="font-weight-bold">📌 Contoh Pengisian Cepat:</label>
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-sm btn-outline-info" onclick="setExample('basic')">🔰 Pemula (Cepat)</button>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="setExample('standard')">⭐ Standar (Normal)</button>
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="setExample('max')">🚀 Maksimal (Lambat)</button>
                    </div>
                </div>
            </div>

            {!! Form::open(['role' => 'form', 'route' => 'admin.generates.submit', 'id' => 'form-register', 'method' => 'get']) !!}
            
            <div class="row">
                <!-- KROMOSOM -->
                <div class="col-md-6">
                    <div class="param-card">
                        <div class="param-label">🧬 Kromosom</div>
                        <div class="param-desc">Jumlah jadwal berbeda yang akan dibuat. Semakin banyak, semakin besar peluang mendapat jadwal bagus.</div>
                        {!! Form::select('kromosom', [
                            '1' => '1 (Cepat - untuk testing)',
                            '2' => '2 (Ringan - rekomendasi pemula)',
                            '3' => '3 (Sedang)',
                            '4' => '4 (Berat)',
                            '5' => '5 (Lambat - untuk hasil maksimal)',
                        ], Input::get('kromosom'), ['class' => 'form-control required', 'id' => 'kromosom']) !!}
                        <div class="example-box">
                            💡 Contoh: Pilih "2" → sistem akan membuat 2 jadwal berbeda sebagai bahan perbandingan
                        </div>
                    </div>
                </div>

                <!-- GENERASI -->
                <div class="col-md-6">
                    <div class="param-card">
                        <div class="param-label">🔄 Generasi (Evolusi)</div>
                        <div class="param-desc">Berapa kali sistem akan memperbaiki jadwal. Semakin banyak, semakin bagus hasilnya.</div>
                        {!! Form::select('generasi', [
                            '1' => '1 (Tanpa perbaikan - untuk testing)',
                            '2' => '2 (Perbaikan ringan)',
                            '3' => '3 (Perbaikan sedang - rekomendasi)',
                            '4' => '4 (Perbaikan berat)',
                            '5' => '5 (Perbaikan maksimal - lambat)',
                        ], Input::get('generasi'), ['class' => 'form-control required', 'id' => 'generasi']) !!}
                        <div class="example-box">
                            💡 Analogi: Seperti revisi tugas. Generasi 1 = draft awal, Generasi 2 = hasil revisi, semakin banyak revisi semakin bagus.
                        </div>
                    </div>
                </div>

                <!-- CROSSOVER -->
                <div class="col-md-6">
                    <div class="param-card">
                        <div class="param-label">🔄 Crossover (Perkawinan Silang)</div>
                        <div class="param-desc">Seberapa agresif sistem menggabungkan jadwal yang bagus. Nilai 0-1.</div>
                        {!! Form::select('crossover', [
                            '0.1' => '0.1 (Sangat konservatif)',
                            '0.3' => '0.3 (Sedikit perubahan)',
                            '0.5' => '0.5 (Seimbang - rekomendasi)',
                            '0.8' => '0.8 (Agresif)',
                            '1' => '1 (Maksimal)',
                        ], Input::get('crossover'), ['class' => 'form-control required', 'id' => 'crossover']) !!}
                        <div class="example-box">
                            💡 Nilai 0.5 = setengah dari jadwal diambil dari induk, setengah lagi dari pasangan
                        </div>
                    </div>
                </div>

                <!-- MUTASI -->
                <div class="col-md-6">
                    <div class="param-card">
                        <div class="param-label">🎲 Mutasi (Perubahan Acak)</div>
                        <div class="param-desc">Seberapa sering sistem melakukan perubahan acak untuk mencari variasi baru. Nilai 0-1.</div>
                        {!! Form::select('mutasi', [
                            '0.05' => '0.05 (Sangat jarang berubah)',
                            '0.1' => '0.1 (Jarang berubah - rekomendasi pemula)',
                            '0.2' => '0.2 (Sedang - rekomendasi)',
                            '0.3' => '0.3 (Sering berubah)',
                            '0.5' => '0.5 (Sangat sering berubah)',
                        ], Input::get('mutasi'), ['class' => 'form-control required', 'id' => 'mutasi']) !!}
                        <div class="example-box">
                            💡 Nilai 0.1 = dari 100 jadwal, 10 jadwal akan diubah secara acak untuk mencari variasi
                        </div>
                    </div>
                </div>
            </div>

            <!-- TOMBOL GENERATE -->
            <div class="row mt-4">
                <div class="col-md-12 text-center">
                    {!! Form::hidden('tabs', Input::get('tabs') ? Input::get('tabs') : 1, ['id' => 'tabs']) !!}
                    <button type="submit" class="btn btn-generate btn-block">
                        <i class="fa fa-magic"></i> GENERATE JADWAL SEKARANG
                    </button>
                </div>
            </div>

            {!! Form::close() !!}

            <!-- TABEL PANDUAN -->
            <div class="row mt-5">
                <div class="col-12">
                    <div class="card bg-light">
                        <div class="card-body">
                            <h6 class="card-title">📖 Panduan Memilih Parameter</h6>
                            <table class="table table-sm table-bordered">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Tujuan</th>
                                        <th>Kromosom</th>
                                        <th>Generasi</th>
                                        <th>Crossover</th>
                                        <th>Mutasi</th>
                                        <th>Waktu Proses</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>🔰 Coba-coba / Testing</td>
                                        <td>1-2</td>
                                        <td>1</td>
                                        <td>0.5</td>
                                        <td>0.1</td>
                                        <td>⚡ Cepat</td>
                                    </tr>
                                    <tr>
                                        <td>⭐ Penggunaan Normal</td>
                                        <td>3-4</td>
                                        <td>2-3</td>
                                        <td>0.5-0.8</td>
                                        <td>0.1-0.2</td>
                                        <td>🐌 Sedang</td>
                                    </tr>
                                    <tr>
                                        <td>🚀 Hasil Maksimal</td>
                                        <td>5</td>
                                        <td>5</td>
                                        <td>1</td>
                                        <td>0.3</td>
                                        <td>🐢 Lambat</td>
                                    </tr>
                                </tbody>
                            </table>
                            <p class="mb-0 text-muted small">
                                <i class="fa fa-lightbulb-o"></i> Tips: Mulai dengan nilai kecil dulu untuk testing, setelah yakin baru naikkan parameternya.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@stop