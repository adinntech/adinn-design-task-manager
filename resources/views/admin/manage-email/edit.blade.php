@extends('layouts.app')
@section('title','Edit Record')
@section('workspace-title','Manage Email')
@section('workspace-subtitle','Update name or email address')
@section('content')

<div class="page-head">
    <div><h1>Edit {{ $record->name }}</h1><p>Only Name and Email Address can be changed.</p></div>
    <a href="{{ route('admin.manage-email.index') }}" class="btn btn-secondary">Back to Manage Email</a>
</div>

<div class="panel" style="max-width:600px">
    <div class="panel-body">
        @if($errors->any())<div class="flash flash-error">{{ $errors->first() }}</div>@endif
        <form method="POST" action="{{ route('admin.manage-email.update',$record) }}" onsubmit="const b=this.querySelector('button[type=submit]');b.disabled=true;b.innerHTML='<span class=btn-spinner></span>Saving...';">
            @csrf
            @method('PUT')
            <div class="form-grid">
                <div>
                    <label class="label">Name</label>
                    <input class="premium-input" name="name" value="{{ old('name',$record->name) }}" required>
                </div>
                <div>
                    <label class="label">Email Address</label>
                    <input class="premium-input" type="email" name="mail" value="{{ old('mail',$record->mail) }}" required>
                </div>
            </div>
            <div style="margin-top:16px">
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>
@endsection
