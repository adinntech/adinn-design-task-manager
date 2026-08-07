@extends('layouts.app')

@section('title', $task->task_id.' - '.$task->task_name)

@section('content')
    @livewire('designer.task-detail', ['task' => $task])
@endsection
