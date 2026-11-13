@extends('layouts.app')

@section('title','How To Do ?')
@section('workspace-title','How To Do ?')
@section('workspace-subtitle','Short video guides for creating and managing your design tasks')

@section('content')
<style>
    .howto-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:16px}
    .howto-card{background:#fff;border:1px solid #e4e7ec;border-radius:14px;overflow:hidden;display:flex;flex-direction:column}
    .howto-card video{display:block;width:100%;aspect-ratio:16/9;background:#101828}
    .howto-meta{padding:12px 14px 14px}
    .howto-title{font-size:13px;font-weight:900;color:#101828;line-height:1.35}
    .howto-sub{margin-top:4px;font-size:10px;color:#667085}
    .howto-empty{background:#fff;border:1px dashed #d0d5dd;border-radius:14px;padding:34px 18px;text-align:center;color:#667085;font-size:12px}
    .howto-empty strong{display:block;color:#344054;font-size:14px;margin-bottom:4px}
</style>

@if($error)
    <div class="howto-empty">
        <strong>The videos could not be loaded right now</strong>
        Please refresh the page in a moment.
    </div>
@elseif(empty($videos))
    <div class="howto-empty">
        <strong>No tutorial videos yet</strong>
        Videos will appear here as soon as they are added.
    </div>
@else
    <div class="howto-grid">
        @foreach($videos as $video)
            <article class="howto-card">
                <video controls preload="metadata" playsinline>
                    {{-- #t=0.5 makes the browser show a frame as the preview instead of a black box. --}}
                    <source src="{{ $video['url'] }}#t=0.5" type="video/mp4">
                    Your browser cannot play this video.
                </video>
                <div class="howto-meta">
                    <div class="howto-title">{{ $video['title'] }}</div>
                    <div class="howto-sub">{{ number_format($video['size_bytes'] / 1048576, 1) }} MB</div>
                </div>
            </article>
        @endforeach
    </div>
@endif
@endsection
