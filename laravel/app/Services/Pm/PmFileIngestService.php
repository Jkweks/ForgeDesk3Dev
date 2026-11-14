<?php

namespace App\Services\Pm;

use App\Jobs\ImportDrawingSet;
use App\Models\DrawingSet;
use App\Models\PmFile;

/**
 * One entry point for "a file appeared or changed on the F: share", used by the Windows-side watcher
 * (webhook) and by the reconciliation scan. Cheap checks first; it never parses a file it has already seen.
 *
 *   unchanged path/size/mtime        -> skip (no hashing, no read)
 *   same content already registered  -> record the new path (move/rename or a Forge-generated file), skip import
 *   new, job and type known          -> register and import (estimates: the CONTRACT tab; drawings: queued)
 *   anything else                    -> register as 'review' with a reason, never guessed
 */
class PmFileIngestService
{
    public function __construct(private PmFileIngestor $files, private PmFileClassifier $classifier, private BidEstimateImporter $estimates) {}

    /**
     * @param  array{path: string, size: int, sha256?: ?string, mtime?: ?string}  $event
     * @return array{file: ?PmFile, action: string}
     */
    public function handle(array $event): array
    {
        $path = PmFileIngestor::toLocalPath($event['path']);
        $size = (int) $event['size'];
        $mtime = ! empty($event['mtime']) ? new \DateTimeImmutable($event['mtime']) : null;

        // 1. Unchanged since we last saw this path: do nothing.
        $seen = PmFile::where('fdrive_path', $path)->orderByDesc('id')->first();
        if ($seen && $seen->size === $size && $seen->file_mtime && $mtime && $seen->file_mtime->equalTo($mtime)) {
            return ['file' => $seen, 'action' => 'unchanged'];
        }

        // 2. Content hash: from the watcher, or read from the (read-only) mount.
        $sha = $event['sha256'] ?? (PmFileIngestor::canReadFdrivePath($path) ? hash_file('sha256', $path) : null);
        if (! $sha) {
            return ['file' => null, 'action' => 'needs_hash'];
        }

        $class = $this->classifier->classify($path);

        // 3. Content we already hold (moved, renamed, or a file Forge generated and someone saved to F:).
        $known = PmFile::where('sha256', $sha)->orderBy('id')->first();
        // Same bytes are the same document wherever they now live; but the same bytes filed under a different
        // job (a copied template) are a new file for that job.
        $sameJob = ! $class['job'] || ! $known?->business_job_id || $known->business_job_id === $class['job']->id;
        if ($known && $sameJob) {
            if ($known->fdrive_path !== $path) {
                $known->update(['fdrive_path' => $path, 'file_mtime' => $mtime ?? $known->file_mtime]);
            }

            return ['file' => $known, 'action' => 'known'];
        }

        [$file] = $this->files->registerFromFdrive($path, $class['doc_type'], $class['job'], $sha, $size, $mtime);

        // 4. Decide what to do with it.
        $job = $class['job'];
        if ($class['doc_type'] === PmFileClassifier::UNCLASSIFIED) {
            return $this->review($file, 'Could not tell what kind of document this is.');
        }
        if (! $job) {
            return $this->review($file, $class['job_number']
                ? "No job {$class['job_number']} exists yet."
                : 'Could not find a job number in the folder path.');
        }

        if ($class['doc_type'] === 'bid_estimate') {
            try {
                $this->estimates->import($file, $job, 'CONTRACT');

                return ['file' => $file->fresh(), 'action' => 'imported'];
            } catch (\Throwable $e) {
                $file->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 1000)]);

                return ['file' => $file->fresh(), 'action' => 'failed'];
            }
        }
        if ($class['doc_type'] === 'drawing_set') {
            $set = DrawingSet::firstOrCreate(['business_job_id' => $job->id, 'pm_file_id' => $file->id], ['status' => 'queued']);
            if ($set->wasRecentlyCreated) {
                ImportDrawingSet::dispatch($set);
            }
            $file->update(['status' => 'imported']);

            return ['file' => $file->fresh(), 'action' => 'queued'];
        }

        // Types with no automatic import yet are filed against the job and left for a person.
        $file->update(['status' => 'new']);

        return ['file' => $file->fresh(), 'action' => 'filed'];
    }

    private function review(PmFile $file, string $reason): array
    {
        $file->update(['status' => 'review', 'error' => $reason]);

        return ['file' => $file->fresh(), 'action' => 'review'];
    }
}
