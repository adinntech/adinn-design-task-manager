@extends('layouts.app')
@section('title','Create User')
@section('workspace-title','Create User')
@section('workspace-subtitle','Add a new role-based account')
@section('content')
<div class="page-head"><div><h1>Create User</h1><p>Create a secure account for an Adinn employee.</p></div><a href="{{ route('admin.users.index') }}" class="btn btn-secondary">Back to Users</a></div>
<div class="panel" style="max-width:900px"><div class="panel-body"><form method="POST" action="{{ route('admin.users.store') }}" onsubmit="const b=this.querySelector('button[type=submit],button:not([type])');if(b){b.disabled=true;b.innerHTML='<span class=btn-spinner></span>Creating...';}">@csrf @include('admin.users.partials.form',['user'=>null])</form></div></div>
@endsection
