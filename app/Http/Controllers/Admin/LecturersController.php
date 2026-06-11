<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lecturer;  // ← panggil model Anda
use Illuminate\Http\Request;

class LecturersController extends Controller
{
    // INDEX - Menampilkan semua data
    public function index(Request $request)
    {
        $searchname = $request->get('searchname');
        
        if ($searchname) {
            $lecturers = Lecturer::where('name', 'like', '%' . $searchname . '%')
                                ->orderBy('id', 'desc')
                                ->paginate(10);
        } else {
            $lecturers = Lecturer::orderBy('id', 'desc')->paginate(10);
        }
        
        return view('admin-news.lecturer.index', compact('lecturers'));
    }

    // CREATE - Form tambah data
    public function create()
    {
        return view('admin-news.lecturer.create');
    }

    // STORE - Simpan data baru ke database
    public function store(Request $request)
    {
        $this->validate($request, [
            'name' => 'required|unique:lecturers,name'
        ]);

        $lecturer = Lecturer::create(['name' => $request->name]);
        
        return redirect()->route('admin.lecturers')
            ->with('success', '✅ <strong>BERHASIL!</strong><br><br>Guru <strong>"' . $lecturer->name . '"</strong> berhasil ditambahkan.');
    }

    public function update(Request $request, $id)
    {
        $lecturer = Lecturer::findOrFail($id);
        
        $this->validate($request, [
            'name' => 'required|unique:lecturers,name,' . $id
        ]);
        
        $oldName = $lecturer->name;
        $lecturer->name = $request->name;
        $lecturer->save();
        
        return redirect()->route('admin.lecturers')
            ->with('success', '✅ <strong>BERHASIL!</strong><br><br>Nama guru berhasil diubah dari <strong>"' . $oldName . '"</strong> menjadi <strong>"' . $lecturer->name . '"</strong>.');
    }

    // EDIT - Form edit data (ini yang Anda gunakan)
    public function edit($id)
    {
        // Ambil 1 data dari database berdasarkan ID
        $lecturer = Lecturer::findOrFail($id);  // ← hasilnya $lecturer (SINGULAR)
        
        // Kirim ke view dengan nama variabel $lecturer
        return view('admin-news.lecturer.edit', compact('lecturer'));
        //                                      ^^^^^^^^^^^^^^^^
        //                                      Mengirim $lecturer, BUKAN $lecturers!
    }


    // DESTROY - Hapus data
    public function destroy($id)
    {
        // Cari data lecturer
        $lecturer = Lecturer::find($id);
        
        if (!$lecturer) {
            return redirect()->route('admin.lecturers')
                ->with('danger', '⚠️ <strong>Data tidak ditemukan!</strong><br><br>Guru yang ingin dihapus tidak ada dalam sistem.');
        }
        
        // Cara SIMPLE: Cek apakah ada relasi di tabel teachs
        $hasRelation = \DB::table('teachs')->where('lecturers_id', $id)->exists();
        
        if ($hasRelation) {
            return redirect()->route('admin.lecturers')
                ->with('danger', 
                    '❌ <strong>GAGAL MENGHAPUS GURU!</strong><br><br>' .
                    'Guru <strong>"' . $lecturer->name . '"</strong> masih memiliki jadwal mengajar.<br><br>' .
                    '📌 <strong>SOLUSI:</strong><br>' .
                    '• Hapus terlebih dahulu jadwal mengajar guru tersebut di menu <strong>Data Mengajar</strong><br>' .
                    '• Setelah semua jadwal terhapus, baru hapus data guru ini.'
                );
        }
        
        // Jika tidak ada relasi, hapus
        $lecturer->delete();
        
        return redirect()->route('admin.lecturers')
            ->with('success', '✅ <strong>BERHASIL!</strong><br><br>Guru <strong>"' . $lecturer->name . '"</strong> telah dihapus dari sistem.');
    }
}