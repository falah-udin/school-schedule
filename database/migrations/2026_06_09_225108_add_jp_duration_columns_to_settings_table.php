<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddJpDurationColumnsToSettingsTable extends Migration
{
    public function up()
    {
        // Cek apakah kolom description sudah ada, jika belum tambahkan
        if (!Schema::hasColumn('settings', 'description')) {
            Schema::table('settings', function (Blueprint $table) {
                $table->string('description')->nullable()->after('value');
            });
        }
        
        // Insert default settings jika belum ada
        $settings = [
            ['key' => 'jp_duration', 'value' => '40', 'description' => 'Durasi 1 Jam Pelajaran (menit)'],
            ['key' => 'start_time', 'value' => '07:00', 'description' => 'Jam mulai sekolah'],
            ['key' => 'jp_per_day', 'value' => '6', 'description' => 'Jumlah jam pelajaran per hari'],
            ['key' => 'break_duration', 'value' => '15', 'description' => 'Durasi istirahat (menit)'],
            ['key' => 'break_after', 'value' => '3', 'description' => 'Istirahat setelah JP ke-'],
        ];
        
        foreach ($settings as $setting) {
            if (!DB::table('settings')->where('key', $setting['key'])->exists()) {
                DB::table('settings')->insert($setting);
            }
        }
    }

    public function down()
    {
        if (Schema::hasColumn('settings', 'description')) {
            Schema::table('settings', function (Blueprint $table) {
                $table->dropColumn('description');
            });
        }
        
        // Hapus setting yang ditambahkan
        DB::table('settings')->whereIn('key', [
            'jp_duration', 'start_time', 'jp_per_day', 'break_duration', 'break_after'
        ])->delete();
    }
}