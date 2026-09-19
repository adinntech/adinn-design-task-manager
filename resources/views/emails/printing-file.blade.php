@php
    $linkified = preg_replace(
        '/(https?:\/\/[^\s<]+)/i',
        '<a href="$1" target="_blank" rel="noopener">$1</a>',
        e($mailBody)
    );
@endphp
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="font-family: Arial, sans-serif; font-size: 14px; color: #1a1a1a; line-height: 1.6;">
    <div>{!! nl2br($linkified) !!}</div>

    @if(!empty($attachmentLinks))
        <div style="margin-top: 24px;">
            <strong>Attachments</strong>
            <ul style="padding-left: 18px;">
                @foreach($attachmentLinks as $attachment)
                    <li>
                        <a href="{{ $attachment['url'] }}" target="_blank" rel="noopener">{{ $attachment['name'] }}</a>
                        @if(!empty($attachment['size_bytes']))
                            <span style="color:#667085;"> ({{ number_format($attachment['size_bytes'] / 1024 / 1024, 1) }} MB)</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</body>
</html>
