<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Schedule;
use App\Models\Day;
use App\Models\Teach;
use Illuminate\Support\Facades\DB;

echo "Test 1: Basic query\n";
$result = Day::all();
echo "Success: " . $result->count() . " rows\n\n";

echo "Test 2: orderByRaw with FIELD\n";
$result = Day::orderByRaw("FIELD(name_day, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu')")->get();
echo "Success: " . $result->count() . " rows\n\n";

echo "Test 3: Query dengan JOIN (tanpa whereHas)\n";
try {
    $result = DB::table('schedules')
        ->join('teachs', 'teachs.id', '=', 'schedules.teachs_id')
        ->where('schedules.type', 0)
        ->where('teachs.lecturers_id', 1)
        ->limit(1)
        ->get();
    echo "Success: " . $result->count() . " rows\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
echo "\n";

echo "Test 4: Query dengan orWhere (tanpa whereHas)\n";
try {
    $result = Schedule::where('type', 0)
        ->where('rooms_id', 1)
        ->orWhere('rooms_id', 2)
        ->limit(1)
        ->get();
    echo "Success: " . $result->count() . " rows\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
echo "\n";

echo "Test 5: Query kompleks dengan JOIN\n";
try {
    $teacherConflict = DB::table('schedules')
        ->join('teachs', 'teachs.id', '=', 'schedules.teachs_id')
        ->where('schedules.type', 0)
        ->where('schedules.days_id', 1)
        ->where('schedules.times_id', 1)
        ->where('teachs.lecturers_id', 1)
        ->exists();
    echo "Success: teacherConflict = " . ($teacherConflict ? 'true' : 'false') . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\nDone\n";
