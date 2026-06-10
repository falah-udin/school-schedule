<?php namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Time;
use App\Models\Timeday;
use App\Models\Day;
use App\Models\Setting;
use App\Models\Timenotavailable;
use Illuminate\Http\Request;
use DB;

class TimeController extends Controller
{
    public function index(Request $request)
    {
        // Kirim variabel $times (bukan $timedays)
        $times = Time::orderBy('id', 'desc')->paginate(10);

        return view('admin-news.time.index', compact('times'));
    }

    public function create(Request $request)
    {
        return view('admin-news.time.create');
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'time_begin'  => 'required|unique:times,time_begin',
            'time_finish' => 'required|unique:times,time_finish'
        ]);

        $begin  = $request->input('time_begin');
        $finish = $request->input('time_finish');
        $range  = $request->input('time_begin') . " - " . $request->input('time_finish');

        $params = [
            'time_begin'  => $begin,
            'time_finish' => $finish,
            'range'       => $range
        ];

        $times = Time::create($params);

        return redirect()->route('admin.times');
    }

    public function edit($id)
    {
        $times = Time::find($id);

        if ($times == null)
        {
            return view('admin-news.layouts.404');
        }

        return view('admin-news.time.edit', compact('times'));
    }

    public function update(Request $request, $id)
    {
        $this->validate($request, [
            'time_begin'  => 'required|unique:times,time_begin,' . $id,
            'time_finish' => 'required|unique:times,time_finish,' . $id
        ]);

        $times              = Time::find($id);
        $begin              = $request->input('time_begin');
        $finish             = $request->input('time_finish');
        $range              = $request->input('time_begin') . " - " . $request->input('time_finish');
        $times->time_begin  = $begin;
        $times->time_finish = $finish;
        $times->range       = $range;
        $times->save();

        return redirect()->route('admin.times');
    }

    public function destroy($id)
    {
        // Cek apakah waktu digunakan di timedays
        $timedaysCount = Timeday::where('times_id', $id)->count();
        
        if ($timedaysCount > 0) {
            return redirect()->route('admin.times')->with('danger', 
                '⚠️ Waktu tidak dapat dihapus! <br><br>' .
                'Waktu ini sudah digunakan di <strong>' . $timedaysCount . '</strong> data Timedays (kombinasi hari & waktu).<br><br>' .
                '📌 Solusi:<br>' .
                '1. Hapus terlebih dahulu data Timedays yang menggunakan waktu ini<br>' .
                '2. Atau generate ulang waktu (akan otomatis menghapus semua data)'
            );
        }
        
        // Cek apakah waktu digunakan di timenotavailable
        $timenotavailables = Timenotavailable::where('times_id', $id)->first();
        
        if (!empty($timenotavailables)) {
            return redirect()->route('admin.times')->with('danger', 
                '⚠️ Waktu tidak dapat dihapus! <br><br>' .
                'Waktu ini sudah digunakan di data Waktu Berhalangan (Time Not Available).<br><br>' .
                '📌 Solusi: Hapus data Waktu Berhalangan terlebih dahulu.'
            );
        }
        
        // Jika tidak ada yang menggunakan, hapus
        Time::find($id)->delete();
        
        return redirect()->route('admin.times')->with('success', '✅ Waktu berhasil dihapus!');
    }

    /**
     * Generate waktu otomatis berdasarkan durasi JP
     */
    public function generate(Request $request)
    {
        try {
            // Ambil parameter dari GET
            $duration = $request->input('duration', Setting::get('jp_duration', 40));
            $startTime = $request->input('start_time', Setting::get('start_time', '07:00'));
            $jpPerDay = $request->input('jp_per_day', Setting::get('jp_per_day', 6));
            $breakDuration = $request->input('break_duration', Setting::get('break_duration', 15));
            $breakAfter = $request->input('break_after', Setting::get('break_after', 3));
            
            // Simpan ke settings
            Setting::set('jp_duration', $duration);
            Setting::set('start_time', $startTime);
            Setting::set('jp_per_day', $jpPerDay);
            Setting::set('break_duration', $breakDuration);
            Setting::set('break_after', $breakAfter);
            
            // Generate times
            $times = [];
            $current = $startTime;
            
            for ($i = 1; $i <= $jpPerDay; $i++) {
                $start = $current;
                $end = $this->addMinutes($start, $duration);
                
                $times[] = [
                    'time_begin' => $start,
                    'time_finish' => $end,
                    'range' => $start . ' - ' . $end,
                ];
                
                $current = $end;
                
                if ($i == $breakAfter && $i < $jpPerDay) {
                    $current = $this->addMinutes($current, $breakDuration);
                }
            }
            
            // Hapus data lama
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            Time::truncate();
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
            
            // Insert data baru
            foreach ($times as $time) {
                Time::create($time);
            }
            
            // Regenerate timedays
            $this->regenerateTimeDays();
            
            return redirect()->route('admin.times')->with('success', 'Waktu berhasil digenerate!');
            
        } catch (\Exception $e) {
            return redirect()->route('admin.times')->with('danger', 'Gagal generate: ' . $e->getMessage());
        }
    }
    
    /**
     * Tambah menit ke waktu
     */
    private function addMinutes($time, $minutes)
    {
        $timestamp = strtotime($time);
        $newTimestamp = $timestamp + ($minutes * 60);
        return date('H:i', $newTimestamp);
    }
    
    /**
     * Regenerate timedays berdasarkan times yang baru
     */
    private function regenerateTimeDays()
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        Timeday::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        
        $days = Day::orderByRaw("FIELD(name_day, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu')")->get();
        $times = Time::orderBy('time_begin')->get();
        
        foreach ($days as $day) {
            foreach ($times as $time) {
                Timeday::create([
                    'days_id' => $day->id,
                    'times_id' => $time->id,
                ]);
            }
        }
    }
}