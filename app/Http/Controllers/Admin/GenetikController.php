<?php namespace App\Http\Controllers\Admin;

use App\Algoritma\GenerateAlgoritma;
use App\Http\Controllers\Controller;
use App\Models\Lecturer;
use App\Models\Schedule;
use App\Models\Room;
use App\Models\Day;
use App\Models\Time;
use App\Models\Setting;
use App\Models\Teach;
use Excel;
use Illuminate\Http\Request;
// use Symfony\Component\HttpFoundation\Session\Session;

class GenetikController extends Controller
{
    public function index(Request $request)
    {
        // $years = Teach::select('year')->groupBy('year')->pluck('year', 'year');

        // return view('admin.genetik.index', compact('years'));
        return view('admin-news.genetik.index');
    }

    public function submit(Request $request)
    {
        // $years            = Teach::select('year')->groupBy('year')->pluck('year', 'year');
        $input_kromosom   = $request->input('kromosom');
        // $input_year       = $request->input('year');
        // $input_semester   = $request->input('semester');
        $input_generasi   = $request->input('generasi');
        $input_crossover  = $request->input('crossover');
        $input_mutasi     = $request->input('mutasi');
        $count_lecturers  = Lecturer::count();
        $count_teachs     = Teach::count();
        $kromosom         = $input_kromosom * $input_generasi;
        $crossover        = $input_kromosom * $input_crossover;
        $generate         = new GenerateAlgoritma;
        // $data_kromosoms   = $generate->randKromosom($kromosom, $count_teachs, $input_year, $input_semester);
        // $testing = $generate->randomingProcess(0);
        // dd($testing);
        $data_kromosoms   = $generate->randKromosom($kromosom, $count_teachs);
        $result_schedules = $generate->checkPinalty();

        $total_gen        = Setting::firstOrNew(['key' => 'total_gen']);
        $total_gen->name  = 'Total Gen';
        $total_gen->value = $crossover;
        $total_gen->save();

        $mutasi        = Setting::firstOrNew(['key' => 'mutasi']);
        $mutasi->name  = 'Mutasi';
        $mutasi->value = (3 * $count_teachs) * $input_kromosom * $input_mutasi;
        $mutasi->save();

        return redirect()->route('admin.generates.result', 1);
        

    }

    public function result($id, Request $request)
    {
        $kromosom = Schedule::select('type')->groupBy('type')->get()->count();
        $crossover = Setting::where('key', Setting::CROSSOVER)->first();
        $mutasi = Setting::where('key', Setting::MUTASI)->first();
        $value_schedule = Schedule::where('type', $id)->first();
        
        // Ambil parameter filter kelas dari URL
        $filterClass = $request->input('class');
        
        // Query jadwal
        $query = Schedule::join('teachs', 'teachs.id', '=', 'schedules.teachs_id')
            ->join('lecturers', 'lecturers.id', '=', 'teachs.lecturers_id')
            ->join('days', 'days.id', '=', 'schedules.days_id')
            ->join('times', 'times.id', '=', 'schedules.times_id')
            ->join('rooms', 'rooms.id', '=', 'schedules.rooms_id')
            ->orderByRaw("FIELD(days.name_day, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu')")
            ->orderBy('times.time_begin', 'asc')
            ->where('schedules.type', $id)
            ->select('schedules.*');
        
        // Jika ada filter kelas
        if (!empty($filterClass)) {
            $query->where('rooms.name', $filterClass);
        }
        
        // Gunakan paginate dengan jumlah besar
        $schedules = $query->paginate(100);
        
        // PERBAIKAN: tambahkan schedules. sebelum type
        $classCounts = Schedule::where('schedules.type', $id)
            ->join('rooms', 'rooms.id', '=', 'schedules.rooms_id')
            ->select('rooms.name', \DB::raw('count(*) as total'))
            ->groupBy('rooms.name')
            ->orderBy('rooms.name')
            ->get();

        if (empty($value_schedule)) {
            abort(404);
        }

        for ($i = 1; $i <= $kromosom; $i++) {
            $value_schedules = Schedule::where('type', $i)->first();
            $data_kromosom[] = [
                'value_schedules' => $value_schedules->value ?? 0,
            ];
        }

        return view('admin-news.genetik.result', compact(
            'schedules', 'data_kromosom', 'id', 'value_schedule', 
            'crossover', 'mutasi', 'filterClass', 'classCounts'
        ));
    }

    public function resultMatrix($id, Request $request)
    {
        $filterClass = $request->input('class');
        $value_schedule = Schedule::where('type', $id)->first();
        
        if (empty($value_schedule)) {
            abort(404);
        }
        
        // Data untuk navigasi (jumlah jadwal per kelas)
        $classCounts = Schedule::where('schedules.type', $id)
            ->join('rooms', 'rooms.id', '=', 'schedules.rooms_id')
            ->select('rooms.name', \DB::raw('count(*) as total'))
            ->groupBy('rooms.name')
            ->orderBy('rooms.name')
            ->get();
        
        // Data hari dan jam (urut)
        $days = Day::orderByRaw("FIELD(name_day, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu')")->get();
        $times = Time::orderBy('time_begin')->get();
        
        // Data kromosom untuk dropdown
        $kromosom = Schedule::select('type')->groupBy('type')->get()->count();
        $crossover = Setting::where('key', Setting::CROSSOVER)->first();
        $mutasi = Setting::where('key', Setting::MUTASI)->first();
        
        for ($i = 1; $i <= $kromosom; $i++) {
            $value_schedules = Schedule::where('type', $i)->first();
            $data_kromosom[] = [
                'value_schedules' => $value_schedules->value ?? 0,
            ];
        }
        
        $scheduleMatrix = [];
        $schedules = collect();
        
        if (!empty($filterClass)) {
            // Tampilan matriks untuk 1 kelas
            $room = Room::where('name', $filterClass)->first();
            if ($room) {
                $schedulesRaw = Schedule::where('schedules.type', $id)
                    ->where('rooms_id', $room->id)
                    ->with(['teach.course', 'teach.lecturer', 'day', 'time'])
                    ->get();
                
                // Susun ke matrix [hari][jam]
                foreach ($schedulesRaw as $s) {
                    $scheduleMatrix[$s->day->name_day][$s->time->range] = [
                        'course' => $s->teach->course->name,
                        'teacher' => $s->teach->lecturer->name
                    ];
                }
            }
        } else {
            // Tampilan list untuk semua kelas (pakai paginate)
            $schedules = Schedule::join('teachs', 'teachs.id', '=', 'schedules.teachs_id')
                ->join('rooms', 'rooms.id', '=', 'schedules.rooms_id')
                ->join('days', 'days.id', '=', 'schedules.days_id')
                ->join('times', 'times.id', '=', 'schedules.times_id')
                ->orderByRaw("FIELD(days.name_day, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu')")
                ->orderBy('times.time_begin', 'asc')
                ->where('schedules.type', $id)
                ->select('schedules.*')
                ->paginate(100);
        }
        
        return view('admin-news.genetik.result_matrix', compact(
            'id', 'filterClass', 'classCounts', 'days', 'times', 
            'scheduleMatrix', 'schedules', 'value_schedule', 
            'data_kromosom', 'crossover', 'mutasi', 'kromosom'
        ));
    }


    public function excel($id)
    {
        $schedules = Schedule::orderBy('days_id', 'desc')
            ->orderBy('times_id', 'desc')
            ->where('type', $id)
            ->get();

        return Excel::create('Algoritma Genetika', function ($excel) use ($schedules)
        {
            $excel->sheet('Genetika', function ($sheet) use ($schedules)
            {
                $sheet->loadView('admin-news.genetik.export')->with('schedules', $schedules);
            });
        })->export('xlsx');

        return redirect()->back();
    }

    public function showClasses($id)
    {
        // $years          = Teach::select('year')->groupBy('year')->pluck('year', 'year');
        $classes        = Teach::select('class_room')->groupBy('class_room')->havingRaw('COUNT(*) > 1')->get();
        $kromosom       = Schedule::select('type')->groupBy('type')->get()->count();
        $crossover      = Setting::where('key', Setting::CROSSOVER)->first();
        $mutasi         = Setting::where('key', Setting::MUTASI)->first();
        $value_schedule = Schedule::where('type', $id)->first();

        $lecturer       = Lecturer::select('id', 'name')->get();
        $rooms          = Room::select('id', 'name')->get();

        $schedule      = Schedule::orderBy('days_id', 'desc')
            ->orderBy('times_id', 'desc')
            ->where('type', $id)
            ->select(
                'schedules.id',
                'schedules.type',
                'schedules.teachs_id',
                'schedules.days_id',
                'schedules.times_id',
                'schedules.rooms_id',
                'schedules.value',
                'schedules.value_process'
                )
            ->get();

        if (empty($value_schedule))
        {
            abort(404);
        }

        for ($i = 1; $i <= $kromosom; $i++)
        {
            $value_schedules = Schedule::where('type', $i)->first();
            $data_kromosom[] = [
                'value_schedules' => $value_schedules->value,
            ];
        }

        $days       = Day::select('name_day')->get();
        $times      = Time::select('range')->get();

        $schedules  = [];

        foreach ($schedule as $s) {
            // $schedules[] = $s->time->range;
            foreach ($times as $t) {
                // $schedules[] = $s->time->range;
                if($s->time->range == $t->range){
                    // $schedules[] = $t->range;

                    $schedules["{$t->range}"][$s->day->name_day][] = $s->teach->course->name  .' - '. $s->teach->lecturer->name .' - '.$s->teach->room->name; // pelajaran guru kelas
                    
                }
            }
        }

        // dd($schedules); //cek crossover & mutasi
        return view('admin-news.genetik.classes', compact('schedules', 'data_kromosom', 'id', 'value_schedule', 'crossover', 'mutasi', 'classes','lecturer', 'rooms', 'times', 'days'));
        // return view('admin.genetik.classes', compact('schedules', 'years', 'data_kromosom', 'id', 'value_schedule', 'crossover', 'mutasi', 'classes','lecturer'));
    }

    public function showTeacherSearch(int $id, Request $request)
    {
        $teachName      = $request->teachName;
        $crossover      = Setting::where('key', Setting::CROSSOVER)->first();
        $mutasi         = Setting::where('key', Setting::MUTASI)->first();
        $lecturer       = Lecturer::select('id', 'name')->get();
        $rooms          = Room::select('id', 'name')->get();        
        $days           = Day::select('name_day')->get();
        $times          = Time::select('range')->get();

        $schedule      = Schedule::orderBy('days_id', 'desc')
            ->orderBy('times_id', 'desc')
            ->where('type', $id)
            ->select(
                'schedules.id',
                'schedules.type',
                'schedules.teachs_id',
                'schedules.days_id',
                'schedules.times_id',
                'schedules.rooms_id',
                'schedules.value',
                'schedules.value_process'
                )
            ->get();

        $schedules  = [];

        foreach ($schedule as $s) {
            // $schedules[] = $s->time->range;
            foreach ($times as $t) {
                // $schedules[] = $s->time->range;
                if($s->time->range == $t->range && $s->teach->lecturer->name == $teachName){
                    // $schedules[] = $t->range;

                    $schedules["{$t->range}"][$s->day->name_day][] = $s->teach->course->name  .' - '. $s->teach->lecturer->name .' - '.$s->teach->room->name; // pelajaran guru kelas
                    
                }
            }
        }

        return view('admin-news.genetik.filter', compact('lecturer','schedules','rooms','times','days','teachs')); 
    }

    public function showClassesSearch(int $id, Request $request)
    {
        $className      = $request->className;
        $crossover      = Setting::where('key', Setting::CROSSOVER)->first();
        $mutasi         = Setting::where('key', Setting::MUTASI)->first();
        
        $lecturer       = Lecturer::select('id', 'name')->get();
        $rooms          = Room::select('id', 'name')->get();
        $days           = Day::select('name_day')->get();
        $times          = Time::select('range')->get();
        $teachs         = Teach::select('id')->get();
        
        $schedule      = Schedule::orderBy('days_id', 'desc')
            ->orderBy('times_id', 'desc')
            ->where('type', $id)
            ->select(
                'schedules.id',
                'schedules.type',
                'schedules.teachs_id',
                'schedules.days_id',
                'schedules.times_id',
                'schedules.rooms_id',
                'schedules.value',
                'schedules.value_process'
                )
            ->get();

        $schedules  = [];

        foreach ($schedule as $s) {
            // $schedules[] = $s->time->range;
            foreach ($times as $t) {
                // $schedules[] = $s->time->range;
                if($s->time->range == $t->range && $s->teach->room->name == $className){
                    // $schedules[] = $t->range;

                    $schedules["{$t->range}"][$s->day->name_day][] = $s->teach->course->name  .' - '. $s->teach->lecturer->name .' - '.$s->teach->room->name; // pelajaran guru kelas
                    
                }
            }
        }

        return view('admin.genetik.filterClass', compact('lecturer','schedules','rooms','times','days','teachs')); 
    }
}
