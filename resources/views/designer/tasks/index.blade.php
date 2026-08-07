@extends('layouts.app')
@section('title', 'Designer Tasks')
@section('workspace-title', 'Designer Task Section')
@section('workspace-subtitle', 'Work through assigned tasks across the complete design production pipeline')
@section('content')
    @livewire('designer.task-kanban')
@endsection
