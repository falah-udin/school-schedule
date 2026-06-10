<?php namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Day;
use App\Models\Timenotavailable;
use Illuminate\Http\Request;

class DayController extends Controller
{
    public function index(Request $request)
    {
        $days = Day::orderBy('id', 'desc')->paginate(10);

        return view('admin-news.day.index', compact('days'));
    }

    public function create(Request $request)
    {
        $days  = Day::orderBy('name_day', 'desc')->pluck('name_day', 'id');

        return view('admin-news.day.create', compact('days'));
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            // 'name_day'  => 'unique:days,name_day|required',

        ]);

        $params = [
            'name_day'  => $request->input('name_day', 'id'),
        ];

        $days = Day::create($params);

        return redirect()->route('admin.days');
    }

    public function edit($id)
    {
        $days = Day::find($id);

        if ($days == null)
        {
            return view('admin-news.layouts.404');
        }

        return view('admin-news.day.edit', compact('days'));
    }

    public function update(Request $request, $id)
    {
        $this->validate($request, [
            'name_day'  => 'required|unique:days,name_day',
        ]);

        $days            = Day::find($id);
        $days->name_day  = $request->input('name_day');
        $days->save();

        return redirect()->route('admin.days');
    }

    public function destroy($id)
    {
        // Cek apakah hari digunakan di timedays
        $timedaysCount = \App\Models\Timeday::where('days_id', $id)->count();
        
        if ($timedaysCount > 0) {
            $message = '<strong>⚠️ Hari tidak dapat dihapus!</strong><br><br>';
            $message .= 'Hari ini sudah digunakan di <strong>' . $timedaysCount . '</strong> data Timedays (kombinasi hari & waktu).<br><br>';
            $message .= '<strong>📌 Solusi:</strong><br>';
            $message .= '1. Hapus terlebih dahulu data Timedays yang menggunakan hari ini<br>';
            $message .= '2. Atau generate ulang waktu (akan otomatis menghapus semua data timedays)';
            
            return redirect()->route('admin.days')->with('danger', $message);
        }
        
        // Cek apakah hari digunakan di timenotavailable
        $timenotavailables = Timenotavailable::where('days_id', $id)->first();
        
        if (!empty($timenotavailables)) {
            $message = '<strong>⚠️ Hari tidak dapat dihapus!</strong><br><br>';
            $message .= 'Hari ini sudah digunakan di data <strong>Waktu Berhalangan (Time Not Available)</strong>.<br><br>';
            $message .= '<strong>📌 Solusi:</strong> Hapus data Waktu Berhalangan terlebih dahulu.';
            
            return redirect()->route('admin.days')->with('danger', $message);
        }
        
        // Jika tidak ada yang menggunakan, hapus
        Day::find($id)->delete();
        
        return redirect()->route('admin.days')->with('success', '<strong>✅ Hari berhasil dihapus!</strong>');
    }
}
