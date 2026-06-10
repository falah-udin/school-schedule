<?php namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $table   = 'settings';
    protected $guarded = [];

    const CROSSOVER = 'total_gen';
    const MUTASI    = 'mutasi';
    
    /**
     * Get setting value by key
     */
    public static function get($key, $default = null)
    {
        $setting = self::where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }
    
    /**
     * Set setting value by key
     */
    public static function set($key, $value)
    {
        $setting = self::firstOrNew(['key' => $key]);
        $setting->value = $value;
        $setting->save();
        return $setting;
    }
}