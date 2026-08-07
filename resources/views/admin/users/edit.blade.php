@extends('layouts.app')
@section('title','Edit User')
@section('workspace-title','Edit User')
@section('workspace-subtitle','Update account details and access')
@section('content')
<div class="page-head"><div><h1>Edit {{ $user->name }}</h1><p>Update role, status or reset the password.</p></div><a href="{{ route('admin.users.index') }}" class="btn btn-secondary">Back to Users</a></div>
<div class="panel" style="max-width:900px"><div class="panel-body"><form method="POST" action="{{ route('admin.users.update',$user) }}">@csrf @method('PUT') @include('admin.users.partials.form',['user'=>$user])</form></div></div>
@endsection
