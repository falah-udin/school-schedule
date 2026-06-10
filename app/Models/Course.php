<?php namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    protected $table = 'courses';
    protected $guarded = [];
    
    protected $casts = [
        'hours_per_week' => 'integer',
        'min_hours_per_day' => 'integer',
        'max_hours_per_day' => 'integer',
    ];
    
    // Default values jika kolom belum ada
    public function getHoursPerWeekAttribute($value)
    {
        return $value ?? $this->getDefaultHoursPerWeek();
    }
    
    private function getDefaultHoursPerWeek()
    {
        $defaults = [
            'Matematika' => 4,
            'Bahasa Indonesia' => 4,
            'Bahasa Inggris' => 4,
            'IPA' => 3,
            'IPS' => 3,
            'PPKn' => 2,
            'Agama' => 2,
            'Penjaskes' => 2,
            'SBK/Prakarya' => 1,
            'MULOK 1' => 1,
        ];
        
        return $defaults[$this->name] ?? 2;
    }
}