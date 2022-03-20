<?php

namespace Backpack\BackupManager\app\Http\Livewire;

use Artisan;
use Carbon\Carbon;
use Exception;
use Illuminate\View\View;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Livewire\Component;
use Log;
use Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Backup extends Component
{
    public $backups = [];

    public function render(): View
    {
        $this->backups = [];

        foreach (config('backup.backup.destination.disks') as $diskName) {
            $disk = Storage::disk($diskName);
            $files = $disk->allFiles();

            // make an array of backup files, with their filesize and creation date
            foreach ($files as $k => $file) {
                // only take the zip files into account
                if (substr($file, -4) === '.zip' && $disk->exists($file)) {
                    $this->backups[] = (object) [
                        'filePath' => $file,
                        'fileName' => str_replace('backups/', '', $file),
                        'fileSize' => round((int) $disk->size($file) / 1048576, 2),
                        'lastModified' => Carbon::createFromTimeStamp($disk->lastModified($file))->formatLocalized('%d %B %Y, %H:%M'),
                        'diskName' => $diskName,
                        'download' => is_a($disk->getAdapter(), LocalFilesystemAdapter::class, true),
                    ];
                }
            }
        }

        // reverse the backups, so the newest one would be on top
        $this->backups = array_reverse($this->backups);

        return view('backupmanager::livewire.backup');
    }

    public function create(): bool | string
    {
        $command = config('backpack.backupmanager.artisan_command_on_button_click') ?? 'backup:run';

        $flags = $command === 'backup:run' ? config('backup.backpack_flags', []) : [];

        try {
            foreach (config('backpack.backupmanager.ini_settings', []) as $setting => $value) {
                ini_set($setting, $value);
            }

            Log::info('Backpack\BackupManager -- Called backup:run from admin interface');

            Artisan::call($command, $flags);

            $output = Artisan::output();
            if (strpos($output, 'Backup failed because')) {
                preg_match('/Backup failed because(.*?)$/ms', $output, $match);
                $message = "Backpack\BackupManager -- backup process failed because ";
                $message .= isset($match[1]) ? $match[1] : '';
                Log::error($message.PHP_EOL.$output);
            } else {
                Log::info("Backpack\BackupManager -- backup process has started");
            }
        } catch (Exception $e) {
            Log::error($e);

            return $e->getMessage();
        }

        return true;
    }

    public function download(int $index): StreamedResponse | string
    {
        $backup = (object) $this->backups[$index];
        $disk = Storage::disk($backup->diskName);

        if (! in_array($backup->diskName, config('backup.backup.destination.disks'))) {
            return trans('backpack::backup.unknown_disk');
        }

        if (! $backup->download) {
            return trans('backpack::backup.only_local_downloads_supported');
        }

        if (! $disk->exists($backup->fileName)) {
            return trans('backpack::backup.backup_doesnt_exist');
        }

        return $disk->download($backup->fileName);
    }

    public function delete(int $index): bool | string
    {
        $backup = (object) $this->backups[$index];
        $disk = Storage::disk($backup->diskName);

        if (! $disk->exists($backup->fileName)) {
            return trans('backpack::backup.backup_doesnt_exist');
        }

        return $disk->delete($backup->fileName);
    }
}
