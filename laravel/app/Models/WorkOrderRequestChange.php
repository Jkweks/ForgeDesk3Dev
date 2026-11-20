<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One edit of a pending work order request by Fabrication: what changed and why. */
class WorkOrderRequestChange extends Model
{
    protected $fillable = ['work_order_request_id', 'user_id', 'summary', 'note'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
