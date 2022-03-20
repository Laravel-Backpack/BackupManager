<div>
    <button id="create-new-backup-button" onclick="onCreate()" class=" btn btn-primary ladda-button mb-2">
        <span><i class="la la-plus"></i> {{ trans('backpack::backup.create_a_new_backup') }}</span>
    </button>

    <div class="card">
        <div class="card-body p-0">
            <table class="table table-hover pb-0 mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>{{ trans('backpack::backup.location') }}</th>
                        <th>{{ trans('backpack::backup.date') }}</th>
                        <th class="text-right">{{ trans('backpack::backup.file_size') }}</th>
                        <th class="text-right">{{ trans('backpack::backup.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($backups as $key => $backup)
                    <tr>
                        <td>{{ $key + 1 }}</td>
                        <td>{{ $backup->diskName }}</td>
                        <td>{{ $backup->lastModified }}</td>
                        <td class="text-right">{{ $backup->fileSize }} MB</td>
                        <td class="text-right">
                            @if ($backup->download)
                            <a class="btn btn-sm text-primary" style="cursor: pointer" onclick="onDownload({{ $key }})">
                                <i class="la la-cloud-download"></i> {{ trans('backpack::backup.download') }}
                            </a>
                            @endif
                            <a class="btn btn-sm text-primary" style="cursor: pointer" onclick="onDelete({{ $key }})">
                                <i class="la la-trash-o"></i> {{ trans('backpack::backup.delete') }}
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

@section('after_styles')
@livewireStyles
@endsection

@section('after_scripts')
@livewireScripts

<script>
    const trans = {
        create_confirmation_title: "{{ trans('backpack::backup.create_confirmation_title') }}",
        create_started_message: "{{ trans('backpack::backup.create_started_message') }}",
        create_error_title: "{{ trans('backpack::backup.create_error_title') }}",
        create_completed_title: "{{ trans('backpack::backup.create_completed_title') }}",
        download_confirmation_title: "{{ trans('backpack::backup.download_confirmation_title') }}",
        delete_error_title: "{{ trans('backpack::backup.delete_error_title') }}",
        delete_confirm: "{{ trans('backpack::backup.delete_confirm') }}",
        delete_cancel_title: "{{ trans('backpack::backup.delete_cancel_title') }}",
        delete_cancel_message: "{{ trans('backpack::backup.delete_cancel_message') }}",
        delete_error_title: "{{ trans('backpack::backup.delete_error_title') }}",
        delete_confirmation_title: "{{ trans('backpack::backup.delete_confirmation_title') }}",
        delete_confirmation_message: "{{ trans('backpack::backup.delete_confirmation_message') }}",
    }

    const noty = (title, message = '', type = 'success') => new Noty({text: `<strong>${title}</strong><br>${message}`, type}).show();

    const onCreate = () => {
        noty(trans.create_confirmation_title, trans.create_started_message);
        Pace.start();

        @this.create()
            .then(result => {
                Pace.stop();
                result === 'string'
                    ? noty(trans.create_error_title, result, 'warning')
                    : noty(trans.create_completed_title);
            });
    }

    const onDownload = index => {
        noty(trans.download_confirmation_title);

        @this.download(index)
            .then(result => {
                result === 'string'
                    ? noty(trans.delete_error_title, result, 'warning')
                    : null;
            });
    }

    const onDelete = index => {
        if (!confirm(trans.delete_confirm)) {
            return noty(trans.delete_cancel_title, trans.delete_cancel_message, 'info');
        }

        @this.delete(index)
            .then(result => {
                result === 'string'
                    ? noty(trans.delete_error_title, result, 'warning')
                    : noty(trans.delete_confirmation_title, trans.delete_confirmation_message);
            });
    }
</script>
@endsection