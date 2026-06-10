<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddHoursAllocationToCoursesTable extends Migration
{
    public function up()
    {
        Schema::table('courses', function (Blueprint $table) {
            if (!Schema::hasColumn('courses', 'hours_per_week')) {
                $table->integer('hours_per_week')->default(2)->after('name');
            }
            if (!Schema::hasColumn('courses', 'min_hours_per_day')) {
                $table->integer('min_hours_per_day')->default(1)->after('hours_per_week');
            }
            if (!Schema::hasColumn('courses', 'max_hours_per_day')) {
                $table->integer('max_hours_per_day')->default(2)->after('min_hours_per_day');
            }
        });
    }

    public function down()
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn(['hours_per_week', 'min_hours_per_day', 'max_hours_per_day']);
        });
    }
}