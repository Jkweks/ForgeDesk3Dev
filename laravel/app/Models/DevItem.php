<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DevItem extends Model
{
    public const TYPES = ['bug', 'feature', 'note'];

    public const PRIORITIES = ['future', 'low', 'normal', 'high', 'critical'];

    public const STATUSES = ['open', 'in_progress', 'resolved', 'wont_fix'];

    protected $fillable = [
        'type', 'source', 'title', 'description', 'console_output', 'page_url', 'user_agent',
        'priority', 'status', 'resolution_notes', 'created_by', 'resolved_at',
    ];

    protected $casts = ['resolved_at' => 'datetime'];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Markdown block for one item — the format pasted back to Claude for fixes. */
    public function toMarkdown(): string
    {
        $out = "## #{$this->id} [{$this->type}] {$this->title}\n";
        $out .= "- Source: {$this->source}\n";
        $out .= "- Status: {$this->status} | Priority: {$this->priority}\n";
        if ($this->page_url) {
            $out .= "- Page: {$this->page_url}\n";
        }
        $out .= '- Reported: '.$this->created_at?->toDateTimeString().($this->creator ? ' by '.$this->creator->name : '')."\n";
        if ($this->description) {
            $out .= "\n{$this->description}\n";
        }
        if ($this->console_output) {
            $out .= "\nConsole output:\n```\n{$this->console_output}\n```\n";
        }
        if ($this->resolution_notes) {
            $out .= "\nResolution notes: {$this->resolution_notes}\n";
        }

        return $out;
    }
}
