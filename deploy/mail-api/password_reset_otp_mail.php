<?php

/*
 * Password Reset OTP mail API.
 *
 * Upload next to the existing mail APIs on adinndigital.com (this file does NOT
 * touch or include them). Called server-to-server by the Design Task Manager
 * (Laravel, "Forgot password" on the web and in the mobile app) once the person
 * has entered a Username / Email / Employee Code that matches an active account.
 * Only that account's own email address is ever sent to.
 *
 * Takes STRUCTURED fields only and renders the mail itself — it never accepts
 * HTML from the caller, so it cannot be used to send arbitrary content.
 *
 * Request (JSON, POST):
 *   mailtype            "password_reset_otp"                  (required)
 *   to                  account email (string, 1 address)     (required)
 *   employee_name       "Sathish Kumar D"                     (required)
 *   employee_code       "1761"                                (optional)
 *   otp                 "482913"  (4-8 digits)                (required)
 *   expires_in_minutes  10        (1-60)                      (required)
 *   idempotency_key     "password-reset-otp-42"               (required)
 *
 * Response contract the caller relies on:
 *   success → HTTP 200 {"status":"success","message":"..."}
 *   failure → HTTP 4xx/5xx {"status":"error","message":"..."}
 */

// ─────────────────────────────────────────
// Config
// ─────────────────────────────────────────
const PR_MAILTYPE = 'password_reset_otp';
const PR_FROM_EMAIL = 'noreply@adinndigital.com';
const PR_FROM_NAME = 'Design Task Manager';

// Shared secret. STRONGLY recommended: this endpoint mails any address it is
// given. Put a long random string here AND the same value in the Laravel .env
// as PASSWORD_RESET_MAIL_API_TOKEN. Leave '' to keep it open.
const PR_API_TOKEN = '';

// Sent idempotency keys are remembered here so a retried request (e.g. the
// caller timed out after the mail actually went out) is never delivered twice.
// Only a hash of the key is stored — never the OTP or the recipient.
define('PR_SENT_DIR', __DIR__ . '/password_reset_otp_sent');

// ─────────────────────────────────────────
// Headers
// ─────────────────────────────────────────
header("Content-Type: application/json; charset=utf-8");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Only POST allowed"]);
    exit;
}

function respond($code, array $body) {
    http_response_code($code);
    echo json_encode($body);
    exit;
}

if (PR_API_TOKEN !== '' && !hash_equals(PR_API_TOKEN, (string)($_SERVER['HTTP_X_API_TOKEN'] ?? ''))) {
    respond(401, ["status" => "error", "message" => "Unauthorized"]);
}

// ─────────────────────────────────────────
// Dependencies (reuse the existing PHPMailer install — no new packages)
// ─────────────────────────────────────────
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$autoloadPath = null;
foreach ([__DIR__ . '/vendor/autoload.php', __DIR__ . '/../vendor/autoload.php', __DIR__ . '/../../vendor/autoload.php'] as $candidate) {
    if (file_exists($candidate)) {
        $autoloadPath = $candidate;
        break;
    }
}
if ($autoloadPath === null) {
    respond(500, ["status" => "error", "message" => "PHPMailer autoload not found"]);
}
require_once $autoloadPath;

// ─────────────────────────────────────────
// Parse + validate input
// ─────────────────────────────────────────
$data = json_decode(file_get_contents("php://input"), true);
if (!is_array($data)) {
    respond(422, ["status" => "error", "message" => "Invalid or missing JSON body"]);
}
if (($data['mailtype'] ?? '') !== PR_MAILTYPE) {
    respond(422, ["status" => "error", "message" => "mailtype must be '" . PR_MAILTYPE . "'"]);
}

function validEmail($email) {
    return is_string($email) && !preg_match('/[\r\n]/', $email) && filter_var($email, FILTER_VALIDATE_EMAIL);
}

$to = trim((string)($data['to'] ?? ''));
if (!validEmail($to)) {
    respond(422, ["status" => "error", "message" => "'to' must be one valid email address"]);
}

$name = trim(preg_replace('/[\r\n]+/', ' ', (string)($data['employee_name'] ?? '')));
if ($name === '' || mb_strlen($name) > 120) {
    respond(422, ["status" => "error", "message" => "'employee_name' is required (max 120 characters)"]);
}
$code = trim(preg_replace('/[\r\n]+/', ' ', (string)($data['employee_code'] ?? '')));
if (mb_strlen($code) > 30) {
    respond(422, ["status" => "error", "message" => "'employee_code' is too long"]);
}

$otp = (string)($data['otp'] ?? '');
if (!preg_match('/^\d{4,8}$/', $otp)) {
    respond(422, ["status" => "error", "message" => "'otp' must be 4 to 8 digits"]);
}

$minutes = $data['expires_in_minutes'] ?? null;
if (!is_numeric($minutes) || (int)$minutes < 1 || (int)$minutes > 60) {
    respond(422, ["status" => "error", "message" => "'expires_in_minutes' must be between 1 and 60"]);
}
$minutes = (int)$minutes;

$idempotencyKey = trim((string)($data['idempotency_key'] ?? ''));
if (!preg_match('/^[A-Za-z0-9._:-]{1,120}$/', $idempotencyKey)) {
    respond(422, ["status" => "error", "message" => "'idempotency_key' is required (letters, digits, . _ : - ; max 120)"]);
}

$subject = 'Your password reset OTP - Design Task Manager';

// ─────────────────────────────────────────
// Duplicate protection (per idempotency_key)
// ─────────────────────────────────────────
if (!is_dir(PR_SENT_DIR) && !@mkdir(PR_SENT_DIR, 0750, true) && !is_dir(PR_SENT_DIR)) {
    respond(500, ["status" => "error", "message" => "Duplicate-protection storage is not writable"]);
}
if (!file_exists(PR_SENT_DIR . '/.htaccess')) {
    @file_put_contents(PR_SENT_DIR . '/.htaccess', "Require all denied\nDeny from all\n");
}

$keyHash = sha1($idempotencyKey);
$sentMarker = PR_SENT_DIR . "/$keyHash.json";
$lockHandle = @fopen(PR_SENT_DIR . "/$keyHash.lock", 'c');
if ($lockHandle === false) {
    respond(500, ["status" => "error", "message" => "Duplicate-protection storage is not writable"]);
}
if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
    respond(409, ["status" => "error", "message" => "This OTP mail is already being sent; retry later"]);
}
if (file_exists($sentMarker)) {
    flock($lockHandle, LOCK_UN);
    respond(200, ["status" => "success", "message" => "OTP mail already sent for this request (duplicate ignored)", "duplicate" => true]);
}

// ─────────────────────────────────────────
// Template — all dynamic values escaped
// ─────────────────────────────────────────
function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function renderOtpHtml($name, $code, $otp, $minutes) {
    $digits = str_split($otp);
    ob_start(); ?>
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f4f5f7;font-family:Arial,Helvetica,sans-serif;color:#1a1a1a;">
<div style="display:none;max-height:0;overflow:hidden;opacity:0;">Your password reset OTP is <?= e($otp) ?>. It is valid for <?= (int)$minutes ?> minutes.</div>
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
            <p style="margin:0 0 12px;">Dear <?= e($name) ?><?= $code !== '' ? ' - ' . e($code) : '' ?>,</p>
            <p style="margin:0 0 16px;">We received a request to reset the password of your Design Task Manager account. Use the one-time password (OTP) below to continue.</p>

            <table role="presentation" align="center" cellpadding="0" cellspacing="0" style="margin:0 auto 6px;">
                <tr>
                    <?php foreach ($digits as $d): ?>
                    <td style="padding:0 3px;">
                        <div style="width:42px;height:52px;line-height:52px;text-align:center;background:#fff0f1;border:1px solid #ffd2d5;border-radius:8px;font-size:26px;font-weight:bold;color:#e30613;font-family:'Courier New',Courier,monospace;"><?= e($d) ?></div>
                    </td>
                    <?php endforeach; ?>
                </tr>
            </table>
            <p style="margin:0 0 18px;text-align:center;font-size:13px;color:#667085;">This OTP is valid for <strong><?= (int)$minutes ?> minutes</strong> and can be used only once.</p>

            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 16px;">
                <tr>
                    <td style="background:#fff8e6;border-left:4px solid #f79009;border-radius:6px;padding:12px 14px;font-size:13px;line-height:1.55;color:#6b4b00;">
                        <strong>Keep this code private.</strong> Never share it with anyone, including colleagues or support staff. Adinn will never ask you for your OTP.
                    </td>
                </tr>
            </table>

            <p style="margin:0 0 12px;">If you did not request a password reset, you can safely ignore this email — your password will remain unchanged. If this keeps happening, please inform your Designer Head or Admin.</p>
            <p style="margin:0;">Regards,<br>Design Task Manager</p>
        </td>
    </tr>
    <tr>
        <td style="padding:14px 24px;background:#f9fafb;border-top:1px solid #e4e7ec;font-size:11px;color:#98a2b3;">
            Automated message from Adinn Design Task Manager. Please do not reply to this email.
        </td>
    </tr>
</table>
</td></tr>
</table>
</body>
</html>
<?php
    return ob_get_clean();
}

function renderOtpText($name, $code, $otp, $minutes) {
    return implode("\n", [
        "Dear $name" . ($code !== '' ? " - $code" : '') . ",",
        "",
        "We received a request to reset the password of your Design Task Manager account.",
        "",
        "Your one-time password (OTP): $otp",
        "This OTP is valid for $minutes minutes and can be used only once.",
        "",
        "Keep this code private. Never share it with anyone. Adinn will never ask you for your OTP.",
        "",
        "If you did not request a password reset, you can safely ignore this email - your password will remain unchanged.",
        "",
        "Regards,",
        "Design Task Manager",
        "(Automated message - please do not reply.)",
    ]);
}

// ─────────────────────────────────────────
// Send
// ─────────────────────────────────────────
try {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host     = 'localhost';
    $mail->SMTPAuth = false;
    $mail->Port     = 25;
    $mail->CharSet  = 'UTF-8';

    $mail->setFrom(PR_FROM_EMAIL, PR_FROM_NAME);
    $mail->isHTML(true);
    $mail->addAddress($to);

    $mail->Subject = $subject;
    $mail->Body    = renderOtpHtml($name, $code, $otp, $minutes);
    $mail->AltBody = renderOtpText($name, $code, $otp, $minutes);
    $mail->send();

    // Remember the key only after a successful send (no recipient, no OTP stored).
    @file_put_contents($sentMarker, json_encode(['sent_at' => gmdate('c')]), LOCK_EX);

    flock($lockHandle, LOCK_UN);
    respond(200, ["status" => "success", "message" => "Password reset OTP mail sent successfully", "duplicate" => false]);
} catch (Exception $e) {
    flock($lockHandle, LOCK_UN);
    respond(500, ["status" => "error", "message" => $e->getMessage()]);
}
