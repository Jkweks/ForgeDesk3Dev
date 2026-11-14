<?php

namespace App\Services\Pm;

use App\Models\BusinessJob;
use App\Models\PmFile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Registers files for Project Management work. Manual uploads and the F: share watcher both end up here,
 * so the import jobs behind it don't care where a file came from (docs/plans/pm-suite-integration.md 4a).
 * Identical content for the same job and type is recognised by hash and never registered or parsed twice.
 */
class PmFileIngestor
{
    public const DISK = 'local';

    /** @return array{0: PmFile, 1: bool} the file and whether it was already known */
    public function registerUpload(UploadedFile $upload, string $docType, ?BusinessJob $job, ?User $by): array
    {
        $bytes = file_get_contents($upload->getRealPath());
        $sha = hash('sha256', $bytes);

        $existing = $this->find($sha, $docType, $job);
        if ($existing) {
            return [$existing, true];
        }

        $ext = strtolower($upload->getClientOriginalExtension() ?: 'bin');
        $path = "pm_files/".substr($sha, 0, 2)."/{$sha}.{$ext}";
        Storage::disk(self::DISK)->put($path, $bytes);

        return [PmFile::create([
            'source' => PmFile::SOURCE_UPLOAD,
            'doc_type' => $docType,
            'original_name' => $upload->getClientOriginalName(),
            'storage_path' => $path,
            'sha256' => $sha,
            'size' => strlen($bytes),
            'file_mtime' => now(),
            'business_job_id' => $job?->id,
            'uploaded_by' => $by?->id,
        ]), false];
    }

    /** Register bytes Forge generated itself (e.g. a built submittal package). */
    public function registerBytes(string $bytes, string $name, string $docType, ?BusinessJob $job, ?User $by): PmFile
    {
        $sha = hash('sha256', $bytes);
        $path = 'pm_files/'.substr($sha, 0, 2)."/{$sha}.".(pathinfo($name, PATHINFO_EXTENSION) ?: 'bin');
        Storage::disk(self::DISK)->put($path, $bytes);

        return PmFile::create([
            'source' => PmFile::SOURCE_UPLOAD, 'doc_type' => $docType, 'original_name' => $name, 'storage_path' => $path,
            'sha256' => $sha, 'size' => strlen($bytes), 'file_mtime' => now(), 'business_job_id' => $job?->id, 'uploaded_by' => $by?->id,
        ]);
    }

    /**
     * Entry point for the F: watcher / reconciliation scan. The share is read-only, so the file is
     * referenced in place rather than copied.
     *
     * @return array{0: PmFile, 1: bool}
     */
    public function registerFromFdrive(string $fdrivePath, string $docType, ?BusinessJob $job, string $sha256, int $size, ?\DateTimeInterface $mtime = null): array
    {
        $existing = $this->find($sha256, $docType, $job);
        if ($existing) {
            if ($existing->fdrive_path !== $fdrivePath) {
                $existing->update(['fdrive_path' => $fdrivePath]); // moved or renamed: same content
            }

            return [$existing, true];
        }

        return [PmFile::create([
            'source' => PmFile::SOURCE_FDRIVE,
            'doc_type' => $docType,
            'original_name' => basename(str_replace('\\', '/', $fdrivePath)),
            'fdrive_path' => $fdrivePath,
            'sha256' => $sha256,
            'size' => $size,
            'file_mtime' => $mtime,
            'business_job_id' => $job?->id,
        ]), false];
    }

    public function bytes(PmFile $file): string
    {
        if ($file->storage_path && Storage::disk(self::DISK)->exists($file->storage_path)) {
            return Storage::disk(self::DISK)->get($file->storage_path);
        }
        if ($file->fdrive_path && self::canReadFdrivePath($file->fdrive_path)) {
            return file_get_contents($file->fdrive_path);
        }

        throw new RuntimeException("File {$file->original_name} is no longer available.");
    }

    /**
     * F: files are only ever read from inside the configured roots (FDRIVE_SCAN_ROOTS). The path comes from the
     * watcher, so this keeps a bad token or payload from turning "register a path" into "read any file".
     */
    public static function canReadFdrivePath(string $path): bool
    {
        $real = realpath($path);
        if ($real === false || ! is_file($real) || ! is_readable($real)) {
            return false;
        }
        foreach ((array) config('services.fdrive_ingest.roots') as $root) {
            $rootReal = realpath($root);
            if ($rootReal !== false && str_starts_with($real, rtrim($rootReal, '/\\').DIRECTORY_SEPARATOR)) {
                return true;
            }
        }

        return false;
    }

    private function find(string $sha, string $docType, ?BusinessJob $job): ?PmFile
    {
        return PmFile::where('sha256', $sha)->where('doc_type', $docType)
            ->where('business_job_id', $job?->id)->first();
    }
}
