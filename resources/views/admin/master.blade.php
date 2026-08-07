@extends('layouts.app')
@section('title','Master Controls')
@section('workspace-title','Master Controls')
@section('workspace-subtitle','Reference structure for verticals, task natures and workflow statuses')
@section('content')
<div class="page-head"><div><h1>Master Controls</h1><p>Current production configuration. This page is intentionally read-only until master tables are introduced safely.</p></div></div>
<div class="dashboard-grid"><section class="panel"><div class="panel-header"><div class="panel-title">Verticals & Task Natures</div></div><div class="panel-body"><div class="activity-list">@foreach($verticals as $key=>$item)<div class="activity-item"><strong>{{ $item[0] }}</strong><p>{{ implode(' · ',$item[1]) }}</p></div>@endforeach</div></div></section><section class="panel"><div class="panel-header"><div class="panel-title">Workflow Statuses</div></div><div class="panel-body"><div class="activity-list">@foreach($statuses as $key=>$label)<div class="activity-item"><strong>{{ $label }}</strong><p>{{ $key }}</p></div>@endforeach</div></div></section></div>
@endsection
