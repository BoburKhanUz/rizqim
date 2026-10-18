<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Region extends Model
{
    protected $guarded = ['id'];
    
    public $timestamps = false; // We can keep timestamps, no big deal, but let's keep them

    public function districts()
    {
        return $this->hasMany(District::class);
    }
}
