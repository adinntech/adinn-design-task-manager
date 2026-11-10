{{-- Fallback copy of the mail API's password-reset OTP template (used only when PASSWORD_RESET_MAIL_API_URL is empty). --}}
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f4f5f7;font-family:Arial,Helvetica,sans-serif;color:#1a1a1a;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f5f7;padding:24px 12px;">
<tr><td align="center">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:10px;overflow:hidden;border:1px solid #e4e7ec;">
    <tr>
        <td style="background:#e30613;padding:18px 24px;color:#ffffff;">
            <div style="font-size:18px;font-weight:bold;">Password Reset Request</div>
            <div style="font-size:13px;opacity:.9;margin-top:3px;">Adinn Design Task Manager</div>
        </td>
    </tr>
    <tr>
        <td style="padding:22px 24px;font-size:14px;line-height:1.6;">
            <p style="margin:0 0 12px;">Dear {{ $name }}{{ filled($code) ? ' - '.$code : '' }},</p>
            <p style="margin:0 0 16px;">We received a request to reset the password of your Design Task Manager account. Use the one-time password (OTP) below to continue.</p>
            <table role="presentation" align="center" cellpadding="0" cellspacing="0" style="margin:0 auto 6px;">
                <tr>
                    @foreach(str_split($otp) as $digit)
                    <td style="padding:0 3px;"><div style="width:42px;height:52px;line-height:52px;text-align:center;background:#fff0f1;border:1px solid #ffd2d5;border-radius:8px;font-size:26px;font-weight:bold;color:#e30613;font-family:'Courier New',Courier,monospace;">{{ $digit }}</div></td>
                    @endforeach
                </tr>
            </table>
            <p style="margin:0 0 18px;text-align:center;font-size:13px;color:#667085;">This OTP is valid for <strong>{{ $minutes }} minutes</strong> and can be used only once.</p>
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 16px;"><tr>
                <td style="background:#fff8e6;border-left:4px solid #f79009;border-radius:6px;padding:12px 14px;font-size:13px;line-height:1.55;color:#6b4b00;"><strong>Keep this code private.</strong> Never share it with anyone, including colleagues or support staff. Adinn will never ask you for your OTP.</td>
            </tr></table>
            <p style="margin:0 0 12px;">If you did not request a password reset, you can safely ignore this email — your password will remain unchanged. If this keeps happening, please inform your Designer Head or Admin.</p>
            <p style="margin:0;">Regards,<br>Design Task Manager</p>
        </td>
    </tr>
    <tr>
        <td style="padding:14px 24px;background:#f9fafb;border-top:1px solid #e4e7ec;font-size:11px;color:#98a2b3;">Automated message from Adinn Design Task Manager. Please do not reply to this email.</td>
    </tr>
</table>
</td></tr>
</table>
</body>
</html>
