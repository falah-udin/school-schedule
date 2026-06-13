<?php namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Teach;
use Illuminate\Http\Request;

class CoursesController extends Controller
{
    public function index(Request $request)
    {
        $courses = Course::orderBy('id', 'desc');

        if (!empty($request->searchname)) {
            $courses = $courses->where('name', 'LIKE', '%' . $request->searchname . '%');
        }

        $courses = $courses->paginate(10);

        return view('admin-news.courses.index', compact('courses'));
    }

    public function create(Request $request)
    {
        return view('admin-news.courses.create');
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'namecourses' => 'required|unique:courses,name'
        ]);

        $params = [
            'name' => $request->input('namecourses'),
            'hours_per_week' => (int)$request->input('hours_per_week', 0),
            'min_hours_per_day' => (int)$request->input('min_hours_per_day', 0),
            'max_hours_per_day' => (int)$request->input('max_hours_per_day', 0),
        ];

        $courses = Course::create($params);

        return redirect()->route('admin.courses')->with('success', 'Mata Pelajaran berhasil ditambahkan!');
    }

    public function edit($id)
    {
        $courses = Course::find($id);

        if ($courses == null) {
            return view('admin-news.layouts.404');
        }

        return view('admin-news.courses.edit', compact('courses'));
    }

    // 🔥 METHOD UPDATE menggunakan GET (sesuai route)
    public function update(Request $request, $id)
    {
        $this->validate($request, [
            'namecourses' => 'required|unique:courses,name,' . $id
        ]);

        $courses = Course::find($id);
        
        if (!$courses) {
            return redirect()->route('admin.courses')->with('danger', 'Data tidak ditemukan!');
        }
        
        $courses->name = $request->input('namecourses');
        $courses->hours_per_week = (int)$request->input('hours_per_week', 0);
        $courses->min_hours_per_day = (int)$request->input('min_hours_per_day', 0);
        $courses->max_hours_per_day = (int)$request->input('max_hours_per_day', 0);
        $courses->save();

        return redirect()->route('admin.courses')->with('success', '<strong>✅ Mata Pelajaran berhasil diubah!</strong>');
    }

    public function destroy($id)
    {
        $course = Course::find($id);
        
        if (!$course) {
            return redirect()->route('admin.courses')->with('danger', '<strong>⚠️ Data tidak ditemukan!</strong>');
        }
        
        $teachCount = Teach::where('courses_id', $id)->count();
        
        if ($teachCount > 0) {
            $message = '<strong>⚠️ GAGAL MENGHAPUS!</strong><br><br>';
            $message .= 'Mata Pelajaran <strong>"' . $course->name . '"</strong> sedang digunakan di <strong>' . $teachCount . '</strong> data Pengampu.<br><br>';
            $message .= '<strong>📌 Solusi:</strong><br>';
            $message .= '• Hapus terlebih dahulu data Pengampu yang terkait<br>';
            $message .= '• Setelah itu, baru hapus Mata Pelajaran ini<br><br>';
            $message .= '<a href="' . route('admin.teachs') . '?search=' . urlencode($course->name) . '" class="btn btn-danger btn-sm" style="color:white; background:#dc3545;">🔍 Lihat Data Pengampu</a>';
            
            return redirect()->route('admin.courses')->with('danger', $message);
        }
        
        $course->delete();
        
        return redirect()->route('admin.courses')->with('success', '<strong>✅ BERHASIL!</strong> Mata Pelajaran "' . $course->name . '" berhasil dihapus.');
    }

    // Simpan alokasi waktu semua mapel
    public function allocationSave(Request $request)
    {
        $courses = $request->input('courses');
        $hours_per_week = $request->input('hours_per_week');
        $min_hours_per_day = $request->input('min_hours_per_day');
        $max_hours_per_day = $request->input('max_hours_per_day');
        
        foreach ($courses as $courseId) {
            $course = Course::find($courseId);
            if ($course) {
                $course->hours_per_week = $hours_per_week[$courseId] ?? 0;
                $course->min_hours_per_day = $min_hours_per_day[$courseId] ?? 0;
                $course->max_hours_per_day = $max_hours_per_day[$courseId] ?? 0;
                $course->save();
            }
        }
        
        return redirect()->route('admin.courses')->with('success', 'Alokasi waktu berhasil disimpan!');
    }

    // Halaman atur alokasi waktu semua mapel
    public function allocation()
    {
        $courses = Course::with('teachs')->get();
        $totalKelas = \App\Models\Room::count();
        
        return view('admin-news.courses.allocation', compact('courses', 'totalKelas'));
    }

    // Edit alokasi per mata pelajaran
    public function allocationEdit($id)
    {
        $course = Course::findOrFail($id);
        
        return view('admin-news.courses.allocation_edit', compact('course'));
    }

    // Update alokasi per mata pelajaran
    public function allocationUpdate(Request $request, $id)
    {
        $course = Course::findOrFail($id);
        $course->hours_per_week = (int)$request->input('hours_per_week', 0);
        $course->min_hours_per_day = (int)$request->input('min_hours_per_day', 0);
        $course->max_hours_per_day = (int)$request->input('max_hours_per_day', 0);
        $course->save();
        
        return redirect()->route('admin.courses')->with('success', 'Alokasi waktu ' . $course->name . ' berhasil diupdate!');
    }
}