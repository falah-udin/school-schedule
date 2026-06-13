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
    let pollingAttempts = 0;
    let lastTotal = 0;
    let noChangeCount = 0;
    let startTime = null;

    function selectAllClasses() {
        $('.class-checkbox').prop('checked', true);
        checkClassSelection(); // ← tambahkan
    }

    function unselectAllClasses() {
        $('.class-checkbox').prop('checked', false);
        checkClassSelection(); // ← tambahkan
    }

    function setExample(type) {
        console.log('setExample dipanggil dengan type:', type);
        
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
        } else if (type === 'advanced') {
            document.getElementById('kromosom').value = '3';
            document.getElementById('generasi').value = '2';
            document.getElementById('crossover').value = '0.7';
            document.getElementById('mutasi').value = '0.25';
        } else if (type === 'max') {
            document.getElementById('kromosom').value = '4';
            document.getElementById('generasi').value = '3';
            document.getElementById('crossover').value = '0.8';
            document.getElementById('mutasi').value = '0.3';
        } else if (type === 'extreme') {
            document.getElementById('kromosom').value = '5';
            document.getElementById('generasi').value = '4';
            document.getElementById('crossover').value = '0.9';
            document.getElementById('mutasi').value = '0.4';
        } else if (type === 'insane') {
            document.getElementById('kromosom').value = '5';
            document.getElementById('generasi').value = '5';
            document.getElementById('crossover').value = '1.0';
            document.getElementById('mutasi').value = '0.5';
        }
        
        $('#kromosom').trigger('change');
        $('#generasi').trigger('change');
        $('#crossover').trigger('change');
        $('#mutasi').trigger('change');
        
        updateEstimation();
    }
    
    function updateEstimation() {
        var kromosom = parseInt($('#kromosom').val()) || 1;
        var generasi = parseInt($('#generasi').val()) || 1;
        var totalKromosom = kromosom * generasi;
        
        var targetJpPerKromosom = 468;
        var estimasiJadwal = totalKromosom * targetJpPerKromosom;
        
        var estimasiWaktu = '';
        var warningText = '';
        
        // Estimasi lebih akurat (per kromosom ~2-3 menit untuk ekstrim)
        var estimasiMenit = totalKromosom * 2.5;
        
        if (totalKromosom <= 2) {
            estimasiWaktu = '~1 menit';
            warningText = '';
        } else if (totalKromosom <= 6) {
            estimasiWaktu = '~3-5 menit';
            warningText = '';
        } else if (totalKromosom <= 12) {
            estimasiWaktu = '~8-12 menit';
            warningText = '⚠️ Proses lama, harap tunggu';
        } else if (totalKromosom <= 20) {
            estimasiWaktu = '~15-25 menit';
            warningText = '⚠️⚠️ Proses sangat lama';
        } else {
            estimasiWaktu = '~30-45 menit';
            warningText = '⏰ Proses ekstrim, pastikan koneksi stabil';
        }
        
        $('#est-total-kromosom').text(totalKromosom);
        $('#est-total-jadwal').text(estimasiJadwal.toLocaleString());
        $('#est-per-kromosom').text(targetJpPerKromosom);
        $('#est-waktu').html(estimasiWaktu + (warningText ? '<br><small style="font-size:10px;">' + warningText + '</small>' : ''));
        
        var card = $('#estimation-card');
        if (totalKromosom <= 4) {
            card.css('background', 'linear-gradient(135deg, #28a745 0%, #1e7e34 100%)');
        } else if (totalKromosom <= 10) {
            card.css('background', 'linear-gradient(135deg, #ffc107 0%, #d39e00 100%)');
        } else if (totalKromosom <= 20) {
            card.css('background', 'linear-gradient(135deg, #fd7e14 0%, #dc3545 100%)');
        } else {
            card.css('background', 'linear-gradient(135deg, #dc3545 0%, #6c1a2a 100%)');
        }
    }

    $(document).ready(function() {
        console.log('Document ready');
        
        $('#kromosom, #generasi').on('change', function() {
            console.log('Change event triggered - Kromosom:', $('#kromosom').val(), 'Generasi:', $('#generasi').val());
            updateEstimation();
        });
        
        updateEstimation();
        
        $('#btn-generate').on('click', startGenerate);
        
        // 🔥 TAMBAHKAN INI: Validasi kelas checkbox
        function checkClassSelection() {
            var anyChecked = $('.class-checkbox:checked').length > 0;
            var $btnGenerate = $('#btn-generate');
            var $classWarning = $('#class-warning');
            
            if (anyChecked) {
                $btnGenerate.prop('disabled', false);
                $btnGenerate.css('opacity', '1');
                $btnGenerate.removeAttr('title');
                $btnGenerate.html('<i class="fa fa-magic"></i> GENERATE JADWAL SEKARANG');
                $classWarning.hide();
            } else {
                $btnGenerate.prop('disabled', true);
                $btnGenerate.css('opacity', '0.5');
                $btnGenerate.html('<i class="fa fa-exclamation-triangle"></i> PILIH KELAS TERLEBIH DAHULU - BARU GENERATE');
                $btnGenerate.attr('title', 'Silakan pilih minimal 1 kelas terlebih dahulu');
                $classWarning.show();
            }
        }
        
        // Event listener untuk checkbox
        $('.class-checkbox').on('change', function() {
            checkClassSelection();
        });
        
        // Set status awal
        checkClassSelection();
    });

    function updateStep(step, message, totalKromosom = null, currentKromosom = null) {
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
        
        var displayMessage = message;
        if (totalKromosom && currentKromosom) {
            displayMessage += `<br><strong>Progress Kromosom: ${currentKromosom} / ${totalKromosom}</strong>`;
        }
        
        $('.loading-message').html(displayMessage);
    }
    
    function updateProgress(percent, message, currentKromosom = null, totalKromosom = null) {
        $('.loading-progress-bar').css('width', percent + '%');
        if (message) {
            var msg = message;
            if (totalKromosom && currentKromosom) {
                msg += ` (${currentKromosom}/${totalKromosom})`;
            }
            $('.loading-message').html(msg);
        }
    }
    
    function addLogMessage(message, type) {
        var logClass = type || 'info';
        var logDiv = $('#log-box');
        if (logDiv.length === 0) return;
        var waktu = new Date().toLocaleTimeString();
        logDiv.append('<div class="' + logClass + '">[' + waktu + '] ' + message + '</div>');
        logDiv.scrollTop(logDiv[0].scrollHeight);
        
        // Auto-hide log jika terlalu panjang (keep last 100 messages)
        if (logDiv.children().length > 100) {
            logDiv.children().slice(0, 50).remove();
        }
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
        addLogMessage('📊 Estimasi selesai: ' + $('#est-waktu').text(), 'info');
        
        startTime = new Date();
    }
    
    function hideLoadingModal() {
        $('#loading-modal').hide();
        if (checkInterval) {
            clearInterval(checkInterval);
            checkInterval = null;
        }
    }
    
    function formatDuration(seconds) {
        var mins = Math.floor(seconds / 60);
        var secs = seconds % 60;
        return mins > 0 ? `${mins} menit ${secs} detik` : `${secs} detik`;
    }
    
    function startPolling(totalKromosomTarget = null) {
        pollingAttempts = 0;
        
        // Cek setiap 3 detik
        checkInterval = setInterval(function() {
            $.ajax({
                url: '{{ route("admin.generates.check-progress") }}',
                method: 'GET',
                timeout: 10000, // 10 detik timeout untuk polling
                success: function(data) {
                    addLogMessage(`📊 Progress: ${data.message}`, 'info');
                    
                    if (data.status === 'error') {
                        clearInterval(checkInterval);
                        addLogMessage(`❌ Generate GAGAL: ${data.message}`, 'error');
                        hideLoadingModal();
                        $('#btn-generate').prop('disabled', false);
                    }
                    
                    if (data.status === 'completed' || data.progress >= 100) {
                        clearInterval(checkInterval);
                        addLogMessage(`✅ SELESAI!`, 'success');
                        fetchFinalResult();
                    }
                },
                error: function(xhr) {
                    addLogMessage(`⚠️ Gagal cek status (${xhr.status}) - menunggu...`, 'warning');
                    // JANGAN STOP POLLING, terus coba
                }
            });
        }, 3000);
    }

    function fetchFinalResult() {
        addLogMessage('📡 Mengambil hasil akhir generate...', 'info');
        
        $.get('{{ route("admin.generates.result-data") }}', function(data) {
            showDetailedResult(data);
        }).fail(function() {
            addLogMessage('❌ Gagal mengambil data hasil, silakan refresh halaman', 'error');
            hideLoadingModal();
            $('#btn-generate').prop('disabled', false).text('GENERATE JADWAL SEKARANG');
            Swal.fire({
                icon: 'error',
                title: 'Gagal mengambil hasil',
                text: 'Silakan refresh halaman dan cek jadwal secara manual'
            });
        });
    }

    function showDetailedResult(data) {
        hideLoadingModal();
        
        var target = data.target_per_kromosom || 468;
        
        var detailHtml = '<table style="width:100%; margin:10px 0; border-collapse:collapse;">';
        detailHtml += '<tr style="background:#f8f9fa;"><th style="text-align:left; padding:8px;">Kromosom</th><th style="text-align:center; padding:8px;">Berhasil</th><th style="text-align:center; padding:8px;">Target</th><th style="text-align:center; padding:8px;">Status</th></tr>';
        
        var semuaSempurna = true;
        var adaYangBerhasil = false;
        var totalBerhasil = 0;
        var totalTarget = 0;
        
        if (data.kromosom_stats && data.kromosom_stats.length > 0) {
            for (var i = 0; i < data.kromosom_stats.length; i++) {
                var stat = data.kromosom_stats[i];
                var status = '';
                var statusColor = '';
                
                totalBerhasil += stat.count;
                totalTarget += stat.target;
                
                if (stat.count >= stat.target) {
                    status = '✅ Sempurna';
                    statusColor = '#28a745';
                    adaYangBerhasil = true;
                } else if (stat.count > 0) {
                    var percent = Math.floor((stat.count / stat.target) * 100);
                    status = '⚠️ ' + percent + '% (' + stat.count + '/' + stat.target + ')';
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
        detailHtml += 'Ringkasan';
        
        // Ringkasan total
        var totalPercent = totalTarget > 0 ? Math.floor((totalBerhasil / totalTarget) * 100) : 0;
        
        var icon = 'success';
        var title = '✅ Generate Selesai!';
        
        if (semuaSempurna) {
            title = '🎉 SEMPURNA! Semua Kromosom Berhasil 100%';
        } else if (totalPercent >= 80) {
            icon = 'success';
            title = '✅ Generate Berhasil (' + totalPercent + '%)';
        } else if (totalPercent >= 50) {
            icon = 'warning';
            title = '⚠️ Generate Sebagian Berhasil (' + totalPercent + '%)';
        } else if (totalBerhasil > 0) {
            icon = 'warning';
            title = '⚠️ Generate Kurang Optimal (' + totalPercent + '%)';
        } else {
            icon = 'error';
            title = '❌ Generate Gagal Total (0%)';
        }
        
        var totalJadwal = data.total || totalBerhasil;
        
        Swal.fire({
            icon: icon,
            title: title,
            html: '<div style="text-align:left;">' +
                '<p><strong>📊 Ringkasan:</strong></p>' +
                '<p>Total jadwal: <strong>' + totalJadwal.toLocaleString() + '</strong> JP</p>' +
                '<p>Keberhasilan: <strong>' + totalPercent + '%</strong></p>' +
                '<hr>' +
                detailHtml +
                '</div>',
            confirmButtonText: 'Lihat Jadwal',
            width: '700px',
            confirmButtonColor: '#28a745'
        }).then(function() {
            window.location.href = '{{ route("admin.generates.result", 1) }}';
        });
        
        $('#btn-generate').prop('disabled', false).text('GENERATE JADWAL SEKARANG');
    }

    function startGenerateProcess(mode, kromosom, generasi, crossover, mutasi) {
        var selectedClasses = [];
        $('.class-checkbox:checked').each(function() {
            selectedClasses.push($(this).val());
        });
        
        var totalKromosomTarget = parseInt(kromosom) * parseInt(generasi);
        
        $('#btn-generate').prop('disabled', true).text('⏳ MEMPROSES...');
        showLoadingModal();
        
        updateStep(2, 'Membangun kromosom...', totalKromosomTarget, 0);
        updateProgress(10);
        
        // 🔥 🔥 🔥 MULAI POLLING LANGSUNG (JANGAN TUNGGU RESPONSE) 🔥 🔥 🔥
        startPolling(totalKromosomTarget);
        addLogMessage('📡 Memulai monitoring proses generate...', 'info');
        
        // Kirim request ke server (biarkan jalan di background)
        $.ajax({
            url: '{{ route("admin.generates.submit.ajax") }}',
            method: 'GET',
            data: {
                kromosom: kromosom,
                generasi: generasi,
                crossover: crossover,
                mutasi: mutasi,
                mode: mode,
                classes: selectedClasses
            },
            timeout: 30000, // 30 detik saja (cukup untuk proses awal)
            success: function(response) {
                addLogMessage('✅ Generate berjalan di background', 'success');
            },
            error: function(xhr) {
                // 🔥 JANGAN STOP POLLING! TETAP LANJUTKAN
                if (xhr.status === 504 || xhr.status === 0 || xhr.status === 502) {
                    addLogMessage('⚠️ Server sedang memproses, polling tetap berjalan...', 'warning');
                    // Polling sudah jalan, tidak perlu apa-apa
                } else {
                    addLogMessage('⚠️ Error: ' + xhr.status, 'warning');
                }
            }
        });
    }

    function startGenerate() {
        var kromosom = $('#kromosom').val();
        var generasi = $('#generasi').val();
        var crossover = $('#crossover').val();
        var mutasi = $('#mutasi').val();
        var totalKromosom = parseInt(kromosom) * parseInt(generasi);
        
        var estimasiMenit = Math.ceil(totalKromosom * 2.5);
        var estimasiText = '';
        if (estimasiMenit <= 2) estimasiText = 'kurang dari 2 menit';
        else if (estimasiMenit <= 10) estimasiText = `sekitar ${estimasiMenit} menit`;
        else if (estimasiMenit <= 20) estimasiText = `sekitar ${estimasiMenit} menit (proses lama)`;
        else estimasiText = `${estimasiMenit} menit (sangat lama, harap bersabar)`;
        
        Swal.fire({
            title: 'Pilihan Generate',
            html: '<div style="text-align:left">' +
                '<p><strong>🧬 Parameter:</strong> Kromosom=' + kromosom + ', Generasi=' + generasi + '</p>' +
                '<p><strong>📊 Total Kromosom:</strong> ' + totalKromosom + '</p>' +
                '<p><strong>⏱️ Estimasi Waktu:</strong> ' + estimasiText + '</p>' +
                '<hr>' +
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
    
    // Paksa semua AJAX pakai HTTPS
    $.ajaxSetup({
        beforeSend: function(xhr, settings) {
            if (settings.url && settings.url.startsWith('http://')) {
                settings.url = settings.url.replace('http://', 'https://');
            }
        }
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
                    <div class="btn-group flex-wrap">
                        <button type="button" class="btn btn-sm btn-outline-success" onclick="setExample('basic')">🔰 Pemula (Cepat)</button>
                        <button type="button" class="btn btn-sm btn-outline-info" onclick="setExample('standard')">⭐ Standar (Normal)</button>
                        <button type="button" class="btn btn-sm btn-outline-warning" onclick="setExample('advanced')">⚡ Lanjutan (Agresif)</button>
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="setExample('max')">🚀 Maksimal (Lambat)</button>
                        <button type="button" class="btn btn-sm btn-outline-dark" onclick="setExample('extreme')">💀 Ekstrim (Sangat Lambat)</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="setExample('insane')">🤪 Gila (⚠️ Resiko Timeout)</button>
                    </div>
                </div>
            </div>

            <!-- CARD ESTIMASI (DINAMIS) -->
            <div class="param-card" id="estimation-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <div class="param-label" style="color: white;">📊 Estimasi Generate</div>
                <div class="param-desc" style="color: rgba(255,255,255,0.9);">
                    Berdasarkan parameter yang Anda pilih:
                </div>
                <div class="row mt-2">
                    <div class="col-md-3">
                        <div style="font-size: 28px; font-weight: bold;" id="est-total-kromosom">0</div>
                        <div style="font-size: 12px;">Total Kromosom</div>
                    </div>
                    <div class="col-md-3">
                        <div style="font-size: 28px; font-weight: bold;" id="est-total-jadwal">0</div>
                        <div style="font-size: 12px;">Perkiraan Jadwal</div>
                    </div>
                    <div class="col-md-3">
                        <div style="font-size: 28px; font-weight: bold;" id="est-per-kromosom">0</div>
                        <div style="font-size: 12px;">JP per Kromosom</div>
                    </div>
                    <div class="col-md-3">
                        <div style="font-size: 28px; font-weight: bold;" id="est-waktu">~30s</div>
                        <div style="font-size: 12px;">Estimasi Waktu</div>
                    </div>
                </div>
                <div class="mt-2" style="font-size: 12px; opacity: 0.8;">
                    <i class="fa fa-info-circle"></i> 
                    Kromosom × Generasi = Total kromosom | 
                    Target per kromosom: <span id="est-target-jp">468</span> JP (12 kelas × 39 JP)
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
                                <option value="4">4 (Berat - untuk Ekstrim)</option>
                                <option value="5">5 (Sangat Berat - untuk Gila)</option>
                            </select>
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
                                <option value="4">4 (Ekstrim)</option>
                                <option value="5">5 (Gila)</option>
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
                                <option value="0.7">0.7 (Agresif)</option>
                                <option value="0.8">0.8 (Maksimal)</option>
                                <option value="0.9">0.9 (Ekstrim)</option>
                                <option value="1.0">1.0 (Gila)</option>
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
                                <option value="0.4">0.4 (Ekstrim)</option>
                                <option value="0.5">0.5 (Gila)</option>
                            </select>
                        </div>
                    </div>
                    <!-- Pilih Kelas yang akan digenerate -->
                    <div class="param-card">
                        <div class="param-label">🏫 Pilih Kelas (Rombel)</div>
                        <div class="param-desc">Pilih kelas yang akan dijadwalkan.</div>
                        
                        <!-- 🔥 TAMBAHKAN PESAN PERINGATAN -->
                        <div id="class-warning" class="alert alert-warning py-1 mb-2" style="display: none; font-size: 12px;">
                            <i class="fa fa-exclamation-triangle"></i> <strong>Peringatan:</strong> Silakan pilih minimal 1 kelas terlebih dahulu!
                        </div>
                        
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