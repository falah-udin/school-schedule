<?php namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Lecturer;
use App\Models\Teach;
use App\Models\Room;
use Illuminate\Http\Request;

class TeachController extends Controller
{

    public function index(Request $request)
    {
        $search = $request->input('search');
        $filterClass = $request->input('filter_class');
        $filterLecturer = $request->input('filter_lecturer');
        
        $teachs = Teach::select('teachs.*')
            ->join('lecturers', 'lecturers.id', '=', 'teachs.lecturers_id')
            ->join('courses', 'courses.id', '=', 'teachs.courses_id')
            ->join('rooms', 'rooms.id', '=', 'teachs.class_room');
        
        // Filter berdasarkan pencarian
        if (!empty($search)) {
            $teachs = $teachs->where(function($q) use ($search) {
                $q->where('lecturers.name', 'LIKE', '%' . $search . '%')
                ->orWhere('courses.name', 'LIKE', '%' . $search . '%')
                ->orWhere('rooms.name', 'LIKE', '%' . $search . '%');
            });
        }
        
        // Filter berdasarkan kelas
        if (!empty($filterClass)) {
            $teachs = $teachs->where('rooms.name', $filterClass);
        }
        
        // Filter berdasarkan guru
        if (!empty($filterLecturer)) {
            $teachs = $teachs->where('lecturers.name', $filterLecturer);
        }
        
        $teachs = $teachs->orderBy('teachs.id', 'desc')->paginate(15);
        
        return view('admin-news.teach.index', compact('teachs'));
    }

    public function create(Request $request)
    {
        $lecturers = Lecturer::orderBy('name', 'asc')->pluck('name', 'id');
        $courses   = Course::orderBy('name', 'asc')->pluck('name', 'id');
        $room      = Room::orderBy('name', 'asc')->pluck('name', 'id');

        return view('admin-news.teach.create', compact('lecturers', 'courses','room'));
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'roomclass' => 'required',
            'lecturers' => 'required',
            'courses'   => 'required',
        ]);

        $params = [
            'class_room'   => $request->input('roomclass'),
            'lecturers_id' => $request->input('lecturers'),
            'courses_id'   => $request->input('courses'),
        ];

        $teachs = Teach::create($params);

        return redirect()->route('admin.teachs');
    }

    public function edit($id)
    {
        $teachs    = Teach::find($id);
        $lecturers = Lecturer::orderBy('name', 'asc')->pluck('name', 'id');
        $courses   = Course::orderBy('name', 'asc')->pluck('name', 'id');
        $room      = Room::orderBy('name', 'asc')->pluck('name', 'id');

        if ($teachs == null)
        {
            return view('admin-news.layouts.404');
        }

        return view('admin-news.teach.edit', compact('teachs', 'lecturers', 'courses','room'));
    }

    public function update(Request $request, $id)
    {
        $this->validate($request, [
            'roomclass' => 'required',
            'lecturers' => 'required',
            'courses'   => 'required',
        ]);

        $teachs               = Teach::find($id);
        $teachs->class_room   = $request->input('roomclass');
        $teachs->lecturers_id = $request->input('lecturers');
        $teachs->courses_id   = $request->input('courses');
        $teachs->save();

        return redirect()->route('admin.teachs');
    }

    public function destroy($id)
    {
        // Cek apakah teach digunakan di schedule
        $scheduleCount = \App\Models\Schedule::where('teachs_id', $id)->count();
        
        if ($scheduleCount > 0) {
            $message = '<strong>⚠️ Pengampu tidak dapat dihapus!</strong><br><br>';
            $message .= 'Data ini sudah digunakan di <strong>' . $scheduleCount . '</strong> jadwal.<br><br>';
            $message .= '<strong>📌 Solusi:</strong> Hapus jadwal terlebih dahulu, atau generate ulang jadwal.';
            
            return redirect()->route('admin.teachs')->with('danger', $message);
        }
        
        Teach::find($id)->delete();
        return redirect()->route('admin.teachs')->with('success', '<strong>✅ Pengampu berhasil dihapus!</strong>');
    }
}
