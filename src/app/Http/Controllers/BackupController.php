<?php

namespace Backpack\BackupManager\app\Http\Controllers;

use Illuminate\Routing\Controller;

class BackupController extends Controller
{
    public function index()
    {
        if (! count(config('backup.backup.destination.disks'))) {
            abort(500, trans('backpack::backup.no_disks_configured'));
        }

        return view('backupmanager::backup');
    }
}
