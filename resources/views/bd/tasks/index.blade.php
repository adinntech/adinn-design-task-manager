@extends('layouts.app')

@section('title','BD Task Kanban')
@section('workspace-title','BD Workspace')
@section('workspace-subtitle','Create client design requirements and track every assigned task')

@section('content')
    <livewire:bd.task-kanban />
@endsection
