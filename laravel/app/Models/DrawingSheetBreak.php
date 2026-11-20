<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A numbered break (match) line on one sheet of a multi-sheet elevation. */
class DrawingSheetBreak extends Model
{
    public $timestamps = false;

    protected $fillable = ['drawing_sheet_id', 'num', 'x'];
}
