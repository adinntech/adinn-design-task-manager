@extends('layouts.app')
@section('title', $task->task_id.' - '.$task->display_task_name)
@section('workspace-title', 'Designer Task Detail')
@section('workspace-subtitle', 'Review requirements, comments and pipeline history')
@section('content')
    @livewire('designer.task-detail', ['task' => $task])
@endsection
