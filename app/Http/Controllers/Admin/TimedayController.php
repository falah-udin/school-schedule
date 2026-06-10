<?php namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Day;
use App\Models\Time;
use App\Models\Timeday;
use Illuminate\Http\Request;
use DB;

class TimedayController extends Controller
{

    public function index(Request $request)
    {
        $timedays = Timeday::orderBy('id', 'desc')->paginate(10);
        // $timedays = Timeday::with('time')->with('day')->paginate(10);

        return view('admin-news.timeday.index', compact('timedays'));
    }

    public function create(Request $request)
    {

        $days  = Day::orderBy('name_day', 'desc')->pluck('name_day', 'id');
        $times = Time::orderBy('range', 'asc')->pluck('range', 'id');

        return view('admin-news.timeday.create', compact('days', 'times'));
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'days'  => 'required|exists:days,id',
            'times' => 'required|exists:times,id',
        ]);

        // Cek apakah kombinasi sudah ada
        $exists = Timeday::where('days_id', $request->days)
            ->where('times_id', $request->times)
            ->first();

        if ($exists) {
            return redirect()->back()->with('danger', 'Kombinasi hari dan waktu sudah ada!');
        }

        $params = [
            'days_id'  => $request->input('days'),
            'times_id' => $request->input('times'),
        ];

        Timeday::create($params);

        return redirect()->route('admin.timedays')->with('success', 'Kombinasi waktu berhasil ditambahkan!');
    }

    public function edit($id)
    {
        $timedays = Timeday::find($id);
        $days     = Day::orderBy('name_day', 'desc')->pluck('name_day', 'id');
        $times    = Time::orderBy('range', 'asc')->pluck('range', 'id');

        if ($timedays == null)
        {
            return view('admin-news.layouts.404');
        }

        return view('admin-news.timeday.edit', compact('timedays', 'days', 'times'));
    }

    public function update(Request $request, $id)
    {
        $this->validate($request, [
            'days'  => 'required|exists:days,id',
            'times' => 'required|exists:times,id',
        ]);

        // Cek unique combination
        $exists = Timeday::where('days_id', $request->days)
            ->where('times_id', $request->times)
            ->where('id', '!=', $id)
            ->first();

        if ($exists) {
            return redirect()->back()->with('danger', 'Kombinasi hari dan waktu sudah ada!');
        }

        $timedays = Timeday::find($id);
        $timedays->days_id  = $request->input('days');
        $timedays->times_id = $request->input('times');
        $timedays->save();

        return redirect()->route('admin.timedays')->with('success', 'Kombinasi waktu berhasil diubah!');
    }

    public function destroy($id)
    {
        $timeday = Timeday::find($id);
        
        if ($timeday) {
            $timeday->delete();
            return redirect()->route('admin.timedays')->with('success', 'Data berhasil dihapus!');
        }
        
        return redirect()->route('admin.timedays')->with('danger', 'Data tidak ditemukan!');
    }

    public function regenerate()
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
        
        return redirect()->route('admin.timedays')->with('success', 'Timedays berhasil diregenerate!');
    }

}
