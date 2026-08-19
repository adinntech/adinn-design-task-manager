@extends('layouts.app')

@section('title','Assigned Tasks')
@section('workspace-title','Assigned Tasks')
@section('workspace-subtitle','Manage assigned Designer tasks and review pending requests')

@section('content')
    <livewire:designer-head.task-kanban />
@endsection
