<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QualityJointHistory extends Model
{
    protected $table = 'quality_joint_history';

    protected $fillable = ['month', 'joint_count', 'note'];
}
