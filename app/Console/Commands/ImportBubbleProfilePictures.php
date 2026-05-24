<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Laravel\Facades\Image;

class ImportBubbleProfilePictures extends Command
{
    protected array $oversized = [];

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:bubble-photos 
                            {--limit= : Limit the number of photos to download} 
                            {--force : Re-download photos even if profile_photo_path is already set} 
                            {--disk= : The disk to upload photos to (defaults to filesystems.default)}
                            {--dry-run : Only show what would be downloaded without actually doing it}
                            {--user-id= : Only process a specific user ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Download profile pictures from external URLs into local or cloud storage';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $limit = $this->option('limit');
        $force = $this->option('force');
        $dryRun = $this->option('dry-run');
        $disk = $this->option('disk') ?: 'public';
        $userId = $this->option('user-id');

        if ($userId) {
            return $this->handleSingleUser((int) $userId, $force, $dryRun, $disk);
        }

        if (! $dryRun && ! $this->confirm('Running this command will delete the current profile photo directory. Are you sure you want to continue?')) {
            return Command::FAILURE;
        }

        if (! $dryRun) {
            Storage::disk($disk)->deleteDirectory('profile-photos');
            Storage::disk($disk)->makeDirectory('profile-photos');
            $this->info('Profile photo directory has been cleaned.');
        }

        $query = User::query()
            ->whereNotNull('profile_photo_url')
            ->where(function ($q) {
                $q->where('profile_photo_url', 'like', 'http%')
                    ->orWhere('profile_photo_url', 'like', '//%');
            });

        if (! $force) {
            $query->whereNull('profile_photo_path');
        }

        if ($limit) {
            $query->limit((int) $limit);
        }

        $users = $query->get();

        if ($users->isEmpty()) {
            $this->info('No profile photos found to download.');

            return Command::SUCCESS;
        }

        $this->info("Found {$users->count()} profile photos to process.");
        $this->line("Target Disk: {$disk}");

        if ($dryRun) {
            $this->warn('Running in DRY RUN mode. No files will be downloaded.');
        }

        $bar = $this->output->createProgressBar($users->count());
        $bar->start();

        $successCount = 0;
        $errorCount = 0;

        foreach ($users as $user) {
            $result = $this->processUser($user, $dryRun, $disk);

            if ($result === true) {
                $successCount++;
            } elseif ($result === false) {
                $errorCount++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $this->info('Finished processing.');
        $this->line("- Success: {$successCount}");
        $this->line("- Errors: {$errorCount}");

        $this->printOversizedWarning();

        return Command::SUCCESS;
    }

    protected function printOversizedWarning(): void
    {
        if (empty($this->oversized)) {
            return;
        }

        $this->newLine();
        $this->warn('⚠ Some photos exceeded 200 KB at quality 50:');
        foreach ($this->oversized as $id) {
            $this->line("  - User ID: {$id}");
        }
        $this->line('Run with --user-id={id} --force to inspect individually.');
    }

    protected function handleSingleUser(int $userId, bool $force, bool $dryRun, string $disk): int
    {
        $user = User::find($userId);

        if (! $user) {
            $this->error("User ID {$userId} not found.");

            return Command::FAILURE;
        }

        $this->line("User: {$user->name} ({$user->email})");

        // Resolve photo URL — check staging DB if no external URL set
        $url = $this->resolvePhotoUrl($user);

        if (! $url) {
            $this->error("No photo URL found for user {$userId} (bubble_id: {$user->bubble_id}).");

            return Command::FAILURE;
        }

        $user->profile_photo_url = $url;

        // Check if file already exists on disk
        if (! $force && $user->profile_photo_path) {
            if (Storage::disk($disk)->exists($user->profile_photo_path)) {
                $this->line('Profile photo already exists on disk. Use --force to re-download.');

                return Command::SUCCESS;
            }

            $this->line('profile_photo_path is set but file is missing — will re-download.');
        }

        if ($dryRun) {
            $this->line("[Dry Run] Would download: {$url}");

            return Command::SUCCESS;
        }

        $result = $this->processUser($user, false, $disk);

        if ($result === true) {
            $this->info('Done.');
            $this->printOversizedWarning();

            return Command::SUCCESS;
        }

        return Command::FAILURE;
    }

    protected function processUser(User $user, bool $dryRun, string $disk): ?bool
    {
        $url = $user->profile_photo_url;

        if (str_starts_with($url, '//')) {
            $url = 'https:'.$url;
        }

        try {
            if ($dryRun) {
                $this->line("\n[Dry Run] Would download: {$url} for User ID: {$user->id}");

                return true;
            }

            $response = Http::timeout(30)->get($url);

            if ($response->failed()) {
                $this->error("\nFailed to download photo for User ID: {$user->id}. Status: ".$response->status());

                return false;
            }

            try {
                $img = Image::decode($response->body());
            } catch (\Exception $e) {
                $img = $this->decodeWithImageMagick($response->body());
            }

            if (! $img) {
                $this->error("\nCould not decode image for User ID: {$user->id}. Unsupported format.");

                return false;
            }

            $img->scale(width: 1200, height: 1200);

            $filename = 'user-'.$user->id.'-'.time().'.jpg';
            $path = 'profile-photos/'.$filename;

            $quality = 85;
            do {
                $encoded = $img->encode(new JpegEncoder($quality));
                $size = strlen((string) $encoded);
                $quality -= 5;
            } while ($size > 200 * 1024 && $quality >= 55);

            if ($size > 200 * 1024) {
                $this->oversized[] = $user->id;
            }

            Storage::disk($disk)->put($path, (string) $encoded);

            if (! Storage::disk($disk)->exists($path)) {
                $this->error("\nFile was not written to disk for User ID: {$user->id}");

                return false;
            }

            $localUrl = Storage::disk($disk)->url($path);

            $user->update([
                'profile_photo_path' => $path,
                'profile_photo_url' => $localUrl,
            ]);

            return true;
        } catch (\Exception $e) {
            $this->error("\nError downloading photo for User ID: {$user->id}: ".$e->getMessage());

            return false;
        }
    }

    protected function decodeWithImageMagick(string $imageData): ?\Intervention\Image\Image
    {
        $tempInput = tempnam(sys_get_temp_dir(), 'heic_');
        $tempOutput = $tempInput.'.jpg';

        try {
            file_put_contents($tempInput, $imageData);

            exec('convert "'.addslashes($tempInput).'" "'.addslashes($tempOutput).'" 2>&1', $output, $exitCode);

            if ($exitCode !== 0 || ! file_exists($tempOutput) || filesize($tempOutput) === 0) {
                return null;
            }

            return Image::decode(file_get_contents($tempOutput));
        } finally {
            @unlink($tempInput);
            @unlink($tempOutput);
        }
    }

    protected function resolvePhotoUrl(User $user): ?string
    {
        $url = $user->profile_photo_url;

        if ($url && (str_starts_with($url, 'http') || str_starts_with($url, '//'))) {
            return $url;
        }

        return $this->fetchPhotoUrlFromStaging($user->bubble_id);
    }

    protected function fetchPhotoUrlFromStaging(?string $bubbleId): ?string
    {
        if (! $bubbleId) {
            return null;
        }

        $path = storage_path('app/bubble_staging.sqlite');

        if (! file_exists($path)) {
            return null;
        }

        try {
            $db = new \PDO("sqlite:{$path}");
            $db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            $stmt = $db->prepare('SELECT raw_json FROM staged_records WHERE external_id = ?');
            $stmt->execute([$bubbleId]);
            $raw = $stmt->fetchColumn();

            if (! $raw) {
                return null;
            }

            $data = json_decode($raw, true);

            $url = $data['profile_photo_url_text'] ?? $data['profile_photo_file'] ?? null;

            if (! $url) {
                return null;
            }

            if (str_starts_with($url, '//')) {
                return 'https:'.$url;
            }

            return $url;
        } catch (\Exception $e) {
            return null;
        }
    }
}
