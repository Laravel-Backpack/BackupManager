@extends(backpack_view('layouts.top_left'))

@php
  $breadcrumbs = [
    trans('backpack::crud.admin') => backpack_url('dashboard'),
    trans('backpack::backup.backups') => false,
  ];
@endphp

@section('header')
    <section class="container-fluid">
      <h2>
        <span class="text-capitalize">{{ trans('backpack::backup.backups') }}</span>
      </h2>
    </section>
@endsection

@section('content')
<livewire:backpack.backupmanager::backuptable />
@endsection