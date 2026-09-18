<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * MaterialCheckController::stageMaterialCheckFile() saves every checked
 * estimate/CSV to disk so a later "commit to reservation" call can claim it
 * as a JobDocument — but most checks never get committed, and a claimed
 * file is moved out of the staging dir (see
 * BusinessJobController::attachMaterialCheckDocument()). This sweeps up
 * whatever's left behind: an unclaimed file whose cache-tracked TTL
 * (MaterialCheckController::STAGING_TTL_HOURS) has long since lapsed, plus
 * anything left dangling by a request that errored mid-check. Deleting by
 * file age rather than re-checking the cache means an already-expired
 * cache entry doesn't leave the file behind forever.
 */
class CleanMaterialCheckStaging extends Command
{
    protected $signature = 'material-check:clean-staging';

    protected $description = 'Delete abandoned material-check staging files older than a day';

    private const MAX_AGE_HOURS = 24;

    public function handle(): int
    {
        $disk = Storage::disk('local');
        $cutoff = now()->subHours(self::MAX_AGE_HOURS)->getTimestamp();
        $deleted = 0;

        foreach ($disk->files('material_check_staging') as $path) {
            if ($disk->lastModified($path) < $cutoff) {
                $disk->delete($path);
                $deleted++;
            }
        }

        $this->info("Deleted {$deleted} abandoned material-check staging file(s).");

        return self::SUCCESS;
    }
}
