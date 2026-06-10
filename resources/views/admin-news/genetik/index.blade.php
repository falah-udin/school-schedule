@extends('admin-news.layouts.master')

@section('title')
{{ $title = 'Generate Jadwal Otomatis' }}
@stop

@section('style')
<link href="{{ asset('new_template/assets/libs/tablesaw/dist/tablesaw.css') }}" rel="stylesheet">
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
        transition: all 0.3s;
    }
    .btn-generate:hover {
        background: #218838;
        transform: scale(1.02);
    }
    .btn-generate:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }
    .info-tips {
        background: #e8f4f8;
        border-left: 4px solid #17a2b8;
        padding: 12px;
        border-radius: 5px;
        margin-top: 15px;
    }
    
    /* Modern Loading Modal */
    .loading-modal {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        backdrop-filter: blur(5px);
        z-index: 9999;
        display: none;
        justify-content: center;
        align-items: center;
    }
    .loading-card {
        background: white;
        border-radius: 16px;
        padding: 30px;
        min-width: 350px;
        max-width: 500px;
        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        animation: slideIn 0.3s ease;
    }
    @keyframes slideIn {
        from { transform: translateY(-30px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }
    .loading-spinner {
        width: 60px;
        height: 60px;
        margin: 0 auto 20px;
        border: 4px solid #e9ecef;
        border-top: 4px solid #17a2b8;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    .loading-title {
        text-align: center;
        font-size: 20px;
        font-weight: bold;
        margin-bottom: 15px;
        color: #2c3e50;
    }
    .loading-message {
        text-align: center;
        color: #6c757d;
        margin-bottom: 20px;
    }
    .loading-progress {
        background: #e9ecef;
        border-radius: 10px;
        height: 8px;
        overflow: hidden;
        margin-bottom: 15px;
    }
    .loading-progress-bar {
        background: linear-gradient(90deg, #17a2b8, #28a745);
        width: 0%;
        height: 100%;
        transition: width 0.3s ease;
        border-radius: 10px;
    }
    .loading-steps {
        margin-top: 20px;
    }
    .step {
        display: flex;
        align-items: center;
        padding: 8px 0;
        color: #6c757d;
        font-size: 13px;
    }
    .step.active {
        color: #17a2b8;
        font-weight: bold;
    }
    .step.completed {
        color: #28a745;
    }
    .step-icon {
        width: 24px;
        margin-right: 10px;
        text-align: center;
    }
    .step-line {
        flex: 1;
        height: 1px;
        background: #dee2e6;
        margin: 0 10px;
    }
    .loading-footer {
        text-align: center;
        margin-top: 20px;
        font-size: 12px;
        color: #adb5bd;
    }

    .log-box {
    background: #1e1e1e;
    color: #d4d4d4;
    padding: 10px;
    border-radius: 5px;
    font-family: monospace;
    font-size: 12px;
    height: 150px;
    overflow-y: auto;
    margin-top: 15px;
    }
    .log-box .info { color: #4ec9b0; }
    .log-box .success { color: #6a9955; }
    .log-box .warning { color: #dcdcaa; }
    .log-box .error { color: #f48771; }

</style>
@stop

@section('script')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    let checkInterval = null;
    let currentStep = 1;

    function selectAllClasses() {
        $('.class-checkbox').prop('checked', true);
    }

    function unselectAllClasses() {
        $('.class-checkbox').prop('checked', false);
    }

    function setExample(type) {
        if (type === 'basic') {
            document.getElementById('kromosom').value = '1';
            document.getElementById('generasi').value = '1';
            document.getElementById('crossover').value = '0.5';
            document.getElementById('mutasi').value = '0.1';
        } else if (type === 'standard') {
            document.getElementById('kromosom').value = '2';
            document.getElementById('generasi').value = '2';
            document.getElementById('crossover').value = '0.5';
            document.getElementById('mutasi').value = '0.2';
        } else if (type === 'max') {
            document.getElementById('kromosom').value = '3';
            document.getElementById('generasi').value = '3';
            document.getElementById('crossover').value = '0.8';
            document.getElementById('mutasi').value = '0.3';
        }
    }
    
    function updateStep(step, message) {
        for (let i = 1; i <= 4; i++) {
            $('#step-' + i).removeClass('active completed');
            if (i < step) {
                $('#step-' + i).addClass('completed');
                $('#step-icon-' + i).html('<i class="fa fa-check-circle"></i>');
            } else if (i === step) {
                $('#step-' + i).addClass('active');
                $('#step-icon-' + i).html('<i class="fa fa-spinner fa-pulse"></i>');
            } else {
                $('#step-icon-' + i).html('<i class="fa fa-circle-o"></i>');
            }
        }
        if (message) {
            $('.loading-message').html(message);
        }
    }
    
    function updateProgress(percent, message) {
        $('.loading-progress-bar').css('width', percent + '%');
        if (message) {
            $('.loading-message').html(message);
        }
    }
    
    function addLogMessage(message, type) {
        var logClass = type || 'info';
        var logDiv = $('#log-box');
        if (logDiv.length === 0) return;
        logDiv.append('<div class="' + logClass + '">[' + new Date().toLocaleTimeString() + '] ' + message + '</div>');
        logDiv.scrollTop(logDiv[0].scrollHeight);
    }
    
    function showLoadingModal() {
        $('#loading-modal').css('display', 'flex');
        currentStep = 1;
        updateStep(1, 'Mempersiapkan data...');
        updateProgress(0);
        
        if ($('#log-box').length === 0) {
            $('.loading-steps').after('<div id="log-box" class="log-box" style="margin-top:15px; background:#1e1e1e; color:#d4d4d4; padding:10px; border-radius:5px; font-family:monospace; font-size:12px; height:150px; overflow-y:auto;"></div>');
        }
        $('#log-box').empty();
        addLogMessage('🚀 Memulai proses generate jadwal...', 'info');
    }
    
    function hideLoadingModal() {
        $('#loading-modal').hide();
    }
    
    function startPolling() {
        let pollCount = 0;
        let lastTotal = 0;
        let noChangeCount = 0;
        
        checkInterval = setInterval(function() {
            pollCount++;
            
            $.get('{{ route("admin.generates.check") }}', function(data) {
                addLogMessage('Status: ' + data.message + ' (' + data.progress + '%) - Total: ' + data.total + ' jadwal', 'info');
                updateProgress(data.progress);
                updateStep(Math.min(4, Math.floor(data.progress / 25) + 1));
                
                // Cek jika tidak ada perubahan
                if (data.total === lastTotal && data.total > 0) {
                    noChangeCount++;
                    if (noChangeCount >= 3) {
                        clearInterval(checkInterval);
                        // Tampilkan notifikasi detail
                        showDetailedResult(data);
                    }
                } else {
                    noChangeCount = 0;
                    lastTotal = data.total;
                }
                
                if (data.status === 'completed' || data.progress >= 100) {
                    clearInterval(checkInterval);
                    showDetailedResult(data);
                }
                
                if (pollCount > 60) {
                    clearInterval(checkInterval);
                    addLogMessage('⏰ Timeout polling, silakan cek hasil secara manual', 'warning');
                    $('#btn-generate').prop('disabled', false).text('GENERATE JADWAL SEKARANG');
                    $('#loading-modal').hide();
                }
            }).fail(function() {
                addLogMessage('⚠️ Gagal mengambil status, mencoba lagi...', 'warning');
            });
        }, 3000);
    }

    function showDetailedResult(data) {
        hideLoadingModal();
        
        var target = data.target_per_kromosom || 468;
        
        // Detail per kromosom
        var detailHtml = '<table style="width:100%; margin:10px 0; border-collapse:collapse;">';
        detailHtml += '<tr style="background:#f8f9fa;"><th style="text-align:left; padding:8px;">Kromosom</th><th style="text-align:center; padding:8px;">Berhasil</th><th style="text-align:center; padding:8px;">Target</th><th style="text-align:center; padding:8px;">Status</th></tr>';
        
        var semuaSempurna = true;
        var adaYangBerhasil = false;
        
        if (data.kromosom_stats && data.kromosom_stats.length > 0) {
            for (var i = 0; i < data.kromosom_stats.length; i++) {
                var stat = data.kromosom_stats[i];
                var status = '';
                var statusColor = '';
                
                if (stat.count >= stat.target) {
                    status = '✅ Sempurna';
                    statusColor = '#28a745';
                    adaYangBerhasil = true;
                } else if (stat.count > 0) {
                    status = '⚠️ ' + stat.percentage + '% (' + stat.count + '/' + stat.target + ')';
                    statusColor = '#ffc107';
                    semuaSempurna = false;
                    adaYangBerhasil = true;
                } else {
                    status = '❌ Gagal (0)';
                    statusColor = '#dc3545';
                    semuaSempurna = false;
                }
                
                detailHtml += '<tr>';
                detailHtml += '<td style="text-align:left; padding:8px;"><strong>Kromosom ' + stat.type + '</strong></td>';
                detailHtml += '<td style="text-align:center; padding:8px;">' + stat.count + '</td>';
                detailHtml += '<td style="text-align:center; padding:8px;">' + stat.target + '</td>';
                detailHtml += '<td style="text-align:center; padding:8px; color:' + statusColor + ';">' + status + '</td>';
                detailHtml += '</tr>';
            }
        } else {
            detailHtml += '<tr><td colspan="4" style="text-align:center;">Tidak ada data kromosom</td></tr>';
        }
        detailHtml += '</table>';
        
        var icon = 'success';
        var title = '✅ Generate Selesai!';
        
        if (semuaSempurna) {
            title = '🎉 SEMPURNA! Semua Kromosom Berhasil';
        } else if (adaYangBerhasil) {
            icon = 'warning';
            title = '⚠️ Generate Sebagian Berhasil';
        } else {
            icon = 'error';
            title = '❌ Generate Gagal Total';
        }
        
        Swal.fire({
            icon: icon,
            title: title,
            html: '<strong>Total:</strong> ' + data.total + ' jadwal<br><br>' + detailHtml,
            confirmButtonText: 'Lihat Jadwal',
            width: '650px'
        }).then(function() {
            window.location.href = '{{ route("admin.generates.result", 1) }}';
        });
    }
    
    // Paksa semua AJAX pakai HTTPS
    $.ajaxSetup({
        beforeSend: function(xhr, settings) {
            if (settings.url && settings.url.startsWith('http://')) {
                settings.url = settings.url.replace('http://', 'https://');
            }
        }
    });

    function startGenerateProcess(mode, kromosom, generasi, crossover, mutasi) {
        var selectedClasses = [];
        $('.class-checkbox:checked').each(function() {
            selectedClasses.push($(this).val());
        });
        
        $('#btn-generate').prop('disabled', true).text('⏳ MEMPROSES...');
        showLoadingModal();
        
        updateStep(2, 'Membangun kromosom...');
        updateProgress(25);
        
        // Kirim request ke server
        $.ajax({
            url: '{{ route("admin.generates.submit") }}',
            method: 'GET',
            data: {
                kromosom: kromosom,
                generasi: generasi,
                crossover: crossover,
                mutasi: mutasi,
                mode: mode,
                classes: selectedClasses
            },
            timeout: 30000,
            success: function(response) {
                if (response.redirect) {
                    updateProgress(100);
                    updateStep(4, 'Selesai! Mengalihkan...');
                    setTimeout(function() {
                        window.location.href = response.redirect;
                    }, 1000);
                } else {
                    // Jika tidak ada redirect, mulai polling
                    addLogMessage('Menunggu proses selesai...', 'info');
                    startPolling();
                }
            },
            error: function(xhr) {
                if (xhr.status === 504 || xhr.status === 0) {
                    addLogMessage('⚠️ Proses generate masih berjalan di server...', 'warning');
                    addLogMessage('🔄 Memeriksa status secara berkala...', 'info');
                    startPolling();
                } else {
                    addLogMessage('❌ Error: ' + xhr.statusText, 'error');
                    $('#btn-generate').prop('disabled', false).text('GENERATE JADWAL SEKARANG');
                    $('#loading-modal').hide();
                }
            }
        });
    }

    function startGenerate() {
        var kromosom = $('#kromosom').val();
        var generasi = $('#generasi').val();
        var crossover = $('#crossover').val();
        var mutasi = $('#mutasi').val();
        
        Swal.fire({
            title: 'Pilihan Generate',
            html: '<div style="text-align:left">' +
                '<p><strong>🧬 Parameter:</strong> Kromosom=' + kromosom + ', Generasi=' + generasi + '</p>' +
                '<p><strong>📌 Pilih mode:</strong></p>' +
                '<label style="display:block; padding:8px; margin:5px 0; background:#f8f9fa; border-radius:5px; cursor:pointer;">' +
                '<input type="radio" name="mode" value="append" checked> ➕ <strong>Tambah</strong> - Tambah ke jadwal yang sudah ada</label>' +
                '<label style="display:block; padding:8px; margin:5px 0; background:#f8f9fa; border-radius:5px; cursor:pointer;">' +
                '<input type="radio" name="mode" value="replace_filter"> 🗑️ <strong>Timpa (Filter)</strong> - Hapus jadwal hanya untuk kelas yang dipilih</label>' +
                '<label style="display:block; padding:8px; margin:5px 0; background:#fff3cd; border-radius:5px; cursor:pointer;">' +
                '<input type="radio" name="mode" value="replace_all"> 🔄 <strong>Reset Semua</strong> - Hapus SEMUA jadwal, generate baru</label>' +
                '</div>',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Generate',
            cancelButtonText: 'Batal',
            preConfirm: function() {
                return document.querySelector('input[name="mode"]:checked').value;
            }
        }).then(function(result) {
            if (result.isConfirmed) {
                var mode = result.value;
                startGenerateProcess(mode, kromosom, generasi, crossover, mutasi);
            }
        });
    }
    
    $(document).ready(function() {
        $('#btn-generate').on('click', startGenerate);
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
                    <li class="breadcrumb-item active">Generate Jadwal</li>
                </ol>
            </nav>
        </div>
    </div>
</div>

<div class="container-fluid">
    @include('admin-news._partials.notifications')
    
    <div class="info-card">
        <h5><i class="fa fa-info-circle"></i> Apa yang dilakukan halaman ini?</h5>
        <p>Sistem akan membuat <strong>jadwal pelajaran otomatis</strong> berdasarkan data yang sudah Anda masukkan.</p>
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

            <div class="row mb-4">
                <div class="col-12">
                    <label class="font-weight-bold">📌 Contoh Pengisian Cepat:</label>
                    <div class="btn-group">
                        <button type="button" class="btn btn-sm btn-outline-info" onclick="setExample('basic')">🔰 Pemula (Cepat)</button>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="setExample('standard')">⭐ Standar (Normal)</button>
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="setExample('max')">🚀 Maksimal (Lambat)</button>
                    </div>
                </div>
            </div>

            <form id="generate-form" action="{{ route('admin.generates.submit') }}" method="GET">
                <div class="row">
                    <div class="col-md-6">
                        <div class="param-card">
                            <div class="param-label">🧬 Kromosom</div>
                            <div class="param-desc">Jumlah jadwal berbeda yang akan dibuat (1-3)</div>
                            <select name="kromosom" id="kromosom" class="form-control">
                                <option value="1">1 (Cepat - testing)</option>
                                <option value="2">2 (Ringan)</option>
                                <option value="3">3 (Sedang)</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="param-card">
                            <div class="param-label">🔄 Generasi (Evolusi)</div>
                            <div class="param-desc">Berapa kali sistem akan memperbaiki jadwal (1-3)</div>
                            <select name="generasi" id="generasi" class="form-control">
                                <option value="1">1 (Testing)</option>
                                <option value="2">2 (Normal)</option>
                                <option value="3">3 (Maksimal)</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="param-card">
                            <div class="param-label">🔄 Crossover</div>
                            <div class="param-desc">Perkawinan silang jadwal (0.1 - 1.0)</div>
                            <select name="crossover" id="crossover" class="form-control">
                                <option value="0.1">0.1 (Konservatif)</option>
                                <option value="0.5" selected>0.5 (Seimbang)</option>
                                <option value="0.8">0.8 (Agresif)</option>
                                <option value="1.0">1.0 (Maksimal)</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="param-card">
                            <div class="param-label">🎲 Mutasi</div>
                            <div class="param-desc">Perubahan acak (0.05 - 0.5)</div>
                            <select name="mutasi" id="mutasi" class="form-control">
                                <option value="0.05">0.05 (Sangat jarang)</option>
                                <option value="0.1" selected>0.1 (Jarang)</option>
                                <option value="0.2">0.2 (Sedang)</option>
                                <option value="0.3">0.3 (Sering)</option>
                                <option value="0.5">0.5 (Sangat sering)</option>
                            </select>
                        </div>
                    </div>
                    <!-- Pilih Kelas yang akan digenerate -->
                    <div class="param-card">
                        <div class="param-label">🏫 Pilih Kelas (Rombel)</div>
                        <div class="param-desc">Pilih kelas yang akan dijadwalkan. Kosongkan untuk semua kelas.</div>
                        <div class="row">
                            @php
                                $rooms = App\Models\Room::orderBy('name')->get();
                            @endphp
                            @foreach($rooms as $room)
                            <div class="col-md-2">
                                <label style="display:block; margin:5px 0;">
                                    <input type="checkbox" name="classes[]" value="{{ $room->id }}" class="class-checkbox">
                                    {{ $room->name }}
                                </label>
                            </div>
                            @endforeach
                        </div>
                        <div class="mt-2">
                            <button type="button" class="btn btn-sm btn-outline-info" onclick="selectAllClasses()">Pilih Semua</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="unselectAllClasses()">Hapus Semua</button>
                        </div>
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-md-12 text-center">
                        <button type="button" id="btn-generate" class="btn btn-generate">
                            <i class="fa fa-magic"></i> GENERATE JADWAL SEKARANG
                        </button>
                    </div>
                </div>
            </form>

            <div class="info-tips">
                <i class="fa fa-lightbulb-o"></i> 
                <strong>Tips:</strong> Mulai dengan parameter kecil (Kromosom=1, Generasi=1) untuk testing. 
                Proses generate bisa memakan waktu 1-3 menit tergantung parameter yang dipilih.
            </div>
        </div>
    </div>
</div>

<!-- MODERN LOADING MODAL -->
<div id="loading-modal" class="loading-modal">
    <div class="loading-card">
        <div class="loading-spinner"></div>
        <div class="loading-title">🔄 Membuat Jadwal</div>
        <div class="loading-message">Sedang memproses data...</div>
        <div class="loading-progress">
            <div class="loading-progress-bar"></div>
        </div>
        <div class="loading-steps">
            <div class="step" id="step-1">
                <div class="step-icon" id="step-icon-1"><i class="fa fa-circle-o"></i></div>
                <span>Mempersiapkan data</span>
            </div>
            <div class="step" id="step-2">
                <div class="step-icon" id="step-icon-2"><i class="fa fa-circle-o"></i></div>
                <span>Membangun kromosom</span>
            </div>
            <div class="step" id="step-3">
                <div class="step-icon" id="step-icon-3"><i class="fa fa-circle-o"></i></div>
                <span>Menjadwalkan mata pelajaran</span>
            </div>
            <div class="step" id="step-4">
                <div class="step-icon" id="step-icon-4"><i class="fa fa-circle-o"></i></div>
                <span>Menyimpan hasil</span>
            </div>
        </div>
        <div class="loading-footer">
            <i class="fa fa-info-circle"></i> Proses bisa memakan waktu beberapa menit
        </div>
    </div>
</div>
@stop