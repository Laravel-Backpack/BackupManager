<?php

namespace Backpack\BackupManager\app\Http\Controllers;

use Carbon\Carbon;
use Exception;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Local\LocalFilesystemAdapter;

class BackupController extends Controller
{
    protected array $data;

    public function index()
    {
        if (!count(config('backup.backup.destination.disks'))) {
            abort(500, trans('backpack::backup.no_disks_configured'));
        }

        $this->data['backups'] = [];

        foreach (config('backup.backup.destination.disks') as $diskName) {
            $disk = Storage::disk($diskName);
            $files = $disk->allFiles();

            // make an array of backup files, with their filesize and creation date
            foreach ($files as $file) {
                // remove diskname from filename
                $fileName = str_replace('backups/', '', $file);
                $downloadLink = route('backup.download', ['file_name' => $fileName, 'disk' => $diskName]);
                $deleteLink = route('backup.destroy', ['file_name' => $fileName, 'disk' => $diskName]);

                // only take the zip files into account
                if (substr($file, -4) == '.zip' && $disk->exists($file)) {
                    $this->data['backups'][] = (object) [
                        'filePath'     => $file,
                        'fileName'     => $fileName,
                        'fileSize'     => round((int) $disk->size($file) / 1048576, 2),
                        'lastModified' => Carbon::createFromTimeStamp($disk->lastModified($file), config('app.timezone'))->isoFormat('DD MMMM YYYY, HH:mm'),
                        'diskName'     => $diskName,
                        'downloadLink' => is_a($disk->getAdapter(), LocalFilesystemAdapter::class, true) ? $downloadLink : null,
                        'deleteLink'   => $deleteLink,
                    ];
                }
            }
        }

        // reverse the backups, so the newest one would be on top
        $this->data['backups'] = array_reverse($this->data['backups']);
        $this->data['title'] = trans('backpack::backup.backups');

        return view('backupmanager::backup', $this->data);
    }

    public function create()
    {
        $command = config('backpack.backupmanager.artisan_command_on_button_click') ?? 'backup:run';

        try {
            foreach (config('backpack.backupmanager.ini_settings', []) as $setting => $value) {
                ini_set($setting, $value);
            }

            Log::info('Backpack\BackupManager -- Called backup:run from admin interface');

            Artisan::call($command);

            $output = Artisan::output();
            if (strpos($output, 'Backup failed because')) {
                preg_match('/Backup failed because(.*?)$/ms', $output, $match);
                $message = "Backpack\BackupManager -- backup process failed because ".($match[1] ?? '');
                Log::error($message.PHP_EOL.$output);

                return response($message, 500);
            }
        } catch (Exception $e) {
            Log::error($e);

            return response($e->getMessage(), 500);
        }

        return true;
    }

    /**
     * Downloads a backup zip file.
     */
    public function download()
    {
        $diskName = Request::input('disk');
        $fileName = Request::input('file_name');

        if (!$this->isBackupDisk($diskName)) {
            abort(500, trans('backpack::backup.unknown_disk'));
        }

        if (!$this->isValidBackupFileName($fileName)) {
            abort(404, trans('backpack::backup.backup_doesnt_exist'));
        }

        $disk = Storage::disk($diskName);

        if (!is_a($disk->getAdapter(), LocalFilesystemAdapter::class, true)) {
            abort(404, trans('backpack::backup.only_local_downloads_supported'));
        }

        if (!$disk->exists($fileName)) {
            abort(404, trans('backpack::backup.backup_doesnt_exist'));
        }

        return $disk->download($fileName);
    }

    /**
     * Deletes a backup file.
     */
    public function delete()
    {
        $diskName = Request::input('disk');
        $fileName = Request::input('file_name');

        if (!$this->isBackupDisk($diskName)) {
            return response(trans('backpack::backup.unknown_disk'), 500);
        }

        if (!$this->isValidBackupFileName($fileName)) {
            return response(trans('backpack::backup.backup_doesnt_exist'), 404);
        }

        $disk = Storage::disk($diskName);

        if (!$disk->exists($fileName)) {
            return response(trans('backpack::backup.backup_doesnt_exist'), 404);
        }

        return $disk->delete($fileName);
    }

    /**
     * Check if disk is a backup disk.
     *
     * @param string $diskName
     *
     * @return bool
     */
    private function isBackupDisk(string $diskName)
    {
        return in_array($diskName, config('backup.backup.destination.disks'));
    }

    /**
     * Only allow plain .zip filenames, to prevent path traversal.
     */
    private function isValidBackupFileName($fileName)
    {
        if (!is_string($fileName) || $fileName === '' || $fileName !== basename($fileName)) {
            return false;
        }

        if (str_contains($fileName, '\\') || str_starts_with($fileName, '.')) {
            return false;
        }

        return str_ends_with(strtolower($fileName), '.zip');
    }
}
