<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FdUser extends Model
{
    protected $table = 'fd_users';

    protected $fillable = ['name', 'initials', 'role', 'email', 'active', 'fab_pin'];

    protected $hidden = ['fab_pin'];

    protected $casts = ['active' => 'boolean'];

    public function workOrders(): HasMany
    {
        return $this->hasMany(FdWorkOrder::class, 'assigned_to_id');
    }

    public function stages(): HasMany
    {
        return $this->hasMany(FdWoStage::class, 'assigned_to_id');
    }
}
