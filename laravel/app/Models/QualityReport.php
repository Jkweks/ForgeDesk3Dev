<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QualityReport extends Model
{
    protected $table = 'quality_reports';

    /** Lifecycle states, in order: pending_review -> verified -> reviewed (rejected is a terminal branch off pending_review). */
    public const STATUSES = ['pending_review', 'verified', 'reviewed', 'rejected'];

    protected $fillable = [
        'elevation_id', 'work_order_id', 'status',
        'report_date', 'pre_forge_completed_date', 'completed_at', 'inspector_name', 'problem_type', 'replacement_needed', 'issue_description',
        'raw_extracted_text', 'extracted_fields', 'elevation_tag_guess', 'job_text_guess',
        'auto_matched', 'match_confidence', 'match_candidates', 'matched_by_user_id',
        'verified_by', 'verified_at', 'rejected_reason',
        'reviewed_by', 'reviewed_at',
        'uploaded_by',
    ];

    protected $casts = [
        'report_date' => 'date',
        'pre_forge_completed_date' => 'date',
        'completed_at' => 'datetime',
        'replacement_needed' => 'boolean',
        'extracted_fields' => 'array',
        'match_candidates' => 'array',
        'auto_matched' => 'boolean',
        'match_confidence' => 'float',
        'verified_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function elevation(): BelongsTo
    {
        return $this->belongsTo(FdWoElevation::class, 'elevation_id');
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(FdWorkOrder::class, 'work_order_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function matchedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'matched_by_user_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function files(): HasMany
    {
        return $this->hasMany(QualityReportFile::class);
    }

    public function verify(int $userId): void
    {
        if ($this->status !== 'pending_review') {
            throw new \RuntimeException('Only a pending report can be verified.');
        }

        $this->update([
            'status' => 'verified',
            'verified_by' => $userId,
            'verified_at' => now(),
        ]);
    }

    public function reject(int $userId, ?string $reason): void
    {
        if ($this->status !== 'pending_review') {
            throw new \RuntimeException('Only a pending report can be rejected.');
        }

        $this->update([
            'status' => 'rejected',
            'verified_by' => $userId,
            'verified_at' => now(),
            'rejected_reason' => $reason,
        ]);
    }

    public function review(int $userId): void
    {
        if ($this->status !== 'verified') {
            throw new \RuntimeException('Only a verified report can be marked reviewed.');
        }

        $this->update([
            'status' => 'reviewed',
            'reviewed_by' => $userId,
            'reviewed_at' => now(),
        ]);
    }

    public function reassignElevation(int $elevationId, int $userId): void
    {
        $elevation = FdWoElevation::findOrFail($elevationId);

        $this->update([
            'elevation_id' => $elevation->id,
            'work_order_id' => $elevation->work_order_id,
            'auto_matched' => false,
            'matched_by_user_id' => $userId,
        ]);
    }
}
