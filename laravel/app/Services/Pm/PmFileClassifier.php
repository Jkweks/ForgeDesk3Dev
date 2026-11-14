<?php

namespace App\Services\Pm;

use App\Models\BusinessJob;

/**
 * Decides what a file on the F: share is and which job it belongs to, from its path alone
 * (docs/plans/pm-suite-integration.md, 4a). Anything it cannot place confidently is left
 * unclassified for a person to assign; it never guesses.
 */
class PmFileClassifier
{
    public const UNCLASSIFIED = 'unclassified';

    /** @return array{doc_type: string, job_number: ?string, job: ?BusinessJob} */
    public function classify(string $path): array
    {
        $normalized = str_replace('\\', '/', $path);
        $name = basename($normalized);
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        $type = self::UNCLASSIFIED;
        if (preg_match('/^BidEstimate_.+\.(xls|xlsx|xlsm)$/i', $name)) {
            $type = 'bid_estimate';
        } elseif ($ext === 'pdf' && preg_match('#/(shop[ _-]?drawings?|drawings)/#i', $normalized)) {
            $type = 'drawing_set';
        } elseif ($ext === 'pdf' && preg_match('#/submittals?/#i', $normalized)) {
            $type = 'submittal_document';
        }

        $jobNumber = $this->jobNumber($normalized);
        $job = $jobNumber ? BusinessJob::withoutGlobalScopes()->where('job_number', $jobNumber)->first() : null;

        return ['doc_type' => $type, 'job_number' => $jobNumber, 'job' => $job];
    }

    /** First path folder that starts with a job number ("2301 Wege Pharmacy", "2301_Wege"). */
    private function jobNumber(string $normalizedPath): ?string
    {
        $folders = array_slice(explode('/', $normalizedPath), 0, -1);
        foreach ($folders as $folder) {
            if (preg_match('/^(\d{3,6})(?:[\s_\-.]|$)/', trim($folder), $m)) {
                return $m[1];
            }
        }

        return null;
    }
}
