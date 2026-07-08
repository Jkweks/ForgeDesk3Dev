<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DevIssue extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'console_output',
        'page_url',
        'user_agent',
        'reported_by',
        'status',
        'priority',
        'notes',
    ];
}
