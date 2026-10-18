<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use App\Traits\Auditable;

class Branch extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'radius_km' => 'float',
            'status' => \App\Enums\BranchStatus::class,
        ];
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function region()
    {
        return $this->belongsTo(Region::class);
    }

    public function district()
    {
        return $this->belongsTo(District::class);
    }
}
