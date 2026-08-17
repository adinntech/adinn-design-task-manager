@extends('layouts.app')

@section('title','All Tasks')
@section('workspace-title','All Tasks')
@section('workspace-subtitle','View all Designer tasks and review pending requests')

@section('content')
    <livewire:designer-head.task-kanban />
@endsection
