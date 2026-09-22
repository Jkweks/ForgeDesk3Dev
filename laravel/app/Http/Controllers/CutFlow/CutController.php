<?php

namespace App\Http\Controllers\CutFlow;

use App\Http\Controllers\Controller;
use App\Models\CutFlow\CutLogEntry;

class CutController extends Controller
{
    /**
     * Public read-only page a label's QR code points at — scan the code
     * printed on a cut piece to see the record it came from. No auth: this
     * is meant to be opened straight from a phone camera on the shop floor.
     */
    public function show(CutLogEntry $cutLogEntry)
    {
        return view('cutflow.cuts-show', ['entry' => $cutLogEntry]);
    }
}
