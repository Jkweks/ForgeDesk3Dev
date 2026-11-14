<?php

namespace App\Models;

use App\Models\Concerns\LogsPmChanges;
use Illuminate\Database\Eloquent\Model;

class PmManufacturer extends Model
{

    use LogsPmChanges;

    public function pmChangeType(): string
    {
        return 'manufacturer';
    }
    protected $fillable = ['name', 'website', 'notes'];

    public function products()
    {
        return $this->hasMany(PmProduct::class);
    }
}
