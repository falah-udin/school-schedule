<?php namespace App\Http\Controllers\Newtemp;

use App\Http\Controllers\Controller;
use App\Models\Lecturer;
use Illuminate\Http\Request;

class LecturersController extends Controller
{
    public function index(Request $request)
    {
        $lecturers = Lecturer::orderBy('id', 'desc')->paginate(10);
        return view('newtemp.lecturers.index', compact('lecturers'));
    }

    public function create()
    {
        return view('newtemp.lecturers.create');
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'name' => 'required|unique:lecturers,name'
        ]);

        Lecturer::create(['name' => $request->name]);
        return redirect()->route('new.lecturer')->with('success', 'Guru berhasil ditambahkan');
    }

    public function edit($id)
    {
        $lecturer = Lecturer::find($id);
        return view('newtemp.lecturers.edit', compact('lecturer'));
    }

    public function update(Request $request, $id)
    {
        $lecturer = Lecturer::find($id);
        $lecturer->name = $request->name;
        $lecturer->save();
        return redirect()->route('new.lecturer')->with('success', 'Guru berhasil diubah');
    }

    public function destroy($id)
    {
        Lecturer::find($id)->delete();
        return redirect()->route('new.lecturer')->with('success', 'Guru berhasil dihapus');
    }
}