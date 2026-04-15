<?php
/**
 * TeacherPlus – Mailer Helper
 * All transactional emails are sent from this file.
 *
 * Usage:
 *   require_once '../includes/mailer.php';
 *   tp_send_registration($to_email, $full_name, $username, $plan_name);
 *   tp_send_payment_confirmation($to_email, $full_name, $plan_name, $amount, $order_id);
 *   tp_send_expiry_reminder($to_email, $full_name, $expiry_date);
 *   tp_send_password_reset($to_email, $full_name, $reset_token);
 */

// ── Load PHPMailer ──────────────────────────────────────────────────────────
$phpmailer_base = __DIR__ . '/../vendor/PHPMailer/src/';
require_once $phpmailer_base . 'PHPMailer.php';
require_once $phpmailer_base . 'SMTP.php';
require_once $phpmailer_base . 'Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ── SMTP Configuration ─────────────────────────────────────────────────────
// Change these values to match your mail provider.
define('TP_SMTP_HOST',   'smtp.gmail.com');
define('TP_SMTP_USER',   'gaintern2@gmail.com');        // ← your Gmail / SMTP login
define('TP_SMTP_PASS',   'dtxhbsxworsycfex');           // ← Gmail App Password
define('TP_SMTP_PORT',   587);
define('TP_FROM_EMAIL',  'no-reply@teacherplus.org');
define('TP_FROM_NAME',   'Teacher Plus');
define('TP_SITE_URL',    'http://localhost/teacherplus'); // ← change for production

// ── Brand colours (inline CSS) ─────────────────────────────────────────────
define('TP_COLOR_ORANGE', '#f87407');
define('TP_COLOR_NAVY',   '#1f2a4a');
define('TP_COLOR_PURPLE', '#3d348b');

// ── Internal: build a PHPMailer instance ───────────────────────────────────
function _tp_mailer(): PHPMailer {
    $mail = new PHPMailer(true);
    $mail->SMTPOptions = [
        'ssl' => [
            'verify_peer'       => false,
            'verify_peer_name'  => false,
            'allow_self_signed' => true,
        ],
    ];
    $mail->isSMTP();
    $mail->Host       = TP_SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = TP_SMTP_USER;
    $mail->Password   = TP_SMTP_PASS;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = TP_SMTP_PORT;
    $mail->CharSet    = 'UTF-8';
    $mail->setFrom(TP_FROM_EMAIL, TP_FROM_NAME);
    $mail->isHTML(true);
    return $mail;
}

// ── Internal: wrap content in the branded email shell ─────────────────────
function _tp_email_shell(string $title, string $body_html): string {
    return '
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f5f5f5;font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#333;">

  <!-- Wrapper -->
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f5f5f5;padding:30px 0;">
    <tr><td align="center">

      <!-- Card -->
      <table width="600" cellpadding="0" cellspacing="0"
             style="background:#ffffff;border-radius:8px;overflow:hidden;
                    box-shadow:0 4px 20px rgba(0,0,0,0.08);max-width:600px;width:100%;">

        <!-- Header -->
        <tr>
          <td style="background:' . TP_COLOR_NAVY . ';padding:28px 40px;text-align:center;">
            <span style="color:' . TP_COLOR_ORANGE . ';font-size:26px;font-weight:bold;
                         letter-spacing:2px;">TEACHER PLUS</span>
          </td>
        </tr>

        <!-- Title bar -->
        <tr>
          <td style="background:' . TP_COLOR_ORANGE . ';padding:12px 40px;">
            <span style="color:#ffffff;font-size:16px;font-weight:bold;">' . htmlspecialchars($title) . '</span>
          </td>
        </tr>

        <!-- Body -->
        <tr>
          <td style="padding:36px 40px 28px;">
            ' . $body_html . '
          </td>
        </tr>

        <!-- Footer -->
        <tr>
          <td style="background:#f0f0f0;padding:20px 40px;text-align:center;
                     font-size:12px;color:#888;border-top:1px solid #e0e0e0;">
            &copy; ' . date('Y') . ' Teacher Plus &nbsp;|&nbsp;
            A15, Vikrampuri, Secunderabad, Telangana – 500009, India<br>
            <a href="' . TP_SITE_URL . '" style="color:' . TP_COLOR_ORANGE . ';text-decoration:none;">
              teacherplus.org
            </a>
          </td>
        </tr>

      </table>
      <!-- /Card -->

    </td></tr>
  </table>

</body>
</html>';
}

// ─────────────────────────────────────────────────────────────────────────────
// 1. ACCOUNT REGISTRATION
// ─────────────────────────────────────────────────────────────────────────────
/**
 * @param string $to_email   Subscriber's email
 * @param string $full_name  Subscriber's display name
 * @param string $username   Login username
 * @param string $plan_name  Plan they signed up for
 * @return bool
 */
function tp_send_registration(
    string $to_email,
    string $full_name,
    string $username,
    string $plan_name
): bool {
    try {
        $mail = _tp_mailer();
        $mail->addAddress($to_email, $full_name);
        $mail->Subject = 'Welcome to Teacher Plus – Account Created';

        $body = '
<p style="font-size:16px;">Dear <strong>' . htmlspecialchars($full_name) . '</strong>,</p>

<p>Thank you for registering with <strong>Teacher Plus</strong>!
Your account has been created successfully.</p>

<table width="100%" cellpadding="0" cellspacing="0"
       style="background:#fff8f0;border-left:4px solid ' . TP_COLOR_ORANGE . ';
              border-radius:4px;padding:16px 20px;margin:20px 0;">
  <tr><td>
    <p style="margin:4px 0;"><strong>Username:</strong> ' . htmlspecialchars($username) . '</p>
    <p style="margin:4px 0;"><strong>Plan selected:</strong> ' . htmlspecialchars($plan_name) . '</p>
  </td></tr>
</table>

<p>Your subscription will be <strong>activated within 24 hours</strong> after we verify
your payment. Once activated, you will receive a separate confirmation email.</p>

<p style="margin:28px 0;">
  <a href="' . TP_SITE_URL . '/public/login.php"
     style="background:' . TP_COLOR_ORANGE . ';color:#fff;padding:12px 28px;
            border-radius:5px;text-decoration:none;font-weight:bold;font-size:15px;">
    Login to Your Account
  </a>
</p>

<p style="color:#555;">If you have any questions, please write to us at
<a href="mailto:circulation@teacherplus.org"
   style="color:' . TP_COLOR_ORANGE . ';">circulation@teacherplus.org</a>.</p>

<p>Warm regards,<br><strong>The Teacher Plus Team</strong></p>';

        $mail->Body    = _tp_email_shell('Account Registration Confirmation', $body);
        $mail->AltBody = strip_tags($body);
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('[TeacherPlus Mailer] Registration email failed: ' . $e->getMessage());
        return false;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 2. PAYMENT CONFIRMATION
// ─────────────────────────────────────────────────────────────────────────────
/**
 * @param string $to_email     Subscriber's email
 * @param string $full_name    Subscriber's display name
 * @param string $plan_name    Plan name
 * @param float  $amount       Amount paid (₹)
 * @param int    $order_id     Order / transaction ID
 * @param string $expiry_date  Subscription valid until (Y-m-d)
 * @return bool
 */
function tp_send_payment_confirmation(
    string $to_email,
    string $full_name,
    string $plan_name,
    float  $amount,
    int    $order_id,
    string $expiry_date = ''
): bool {
    try {
        $mail = _tp_mailer();
        $mail->addAddress($to_email, $full_name);
        $mail->Subject = 'Teacher Plus – Payment Confirmed & Subscription Activated';

        $expiry_row = $expiry_date
            ? '<p style="margin:4px 0;"><strong>Valid until:</strong> '
              . date('d F Y', strtotime($expiry_date)) . '</p>'
            : '';

        $body = '
<p style="font-size:16px;">Dear <strong>' . htmlspecialchars($full_name) . '</strong>,</p>

<p>Great news! We have received your payment and your
<strong>Teacher Plus</strong> subscription is now <strong>active</strong>.</p>

<!-- Receipt box -->
<table width="100%" cellpadding="0" cellspacing="0"
       style="background:#fff8f0;border:1px solid #fdd5a0;border-radius:6px;
              margin:20px 0;border-collapse:collapse;">
  <tr style="background:' . TP_COLOR_ORANGE . ';">
    <td colspan="2" style="padding:10px 20px;color:#fff;font-weight:bold;font-size:14px;">
      Payment Receipt
    </td>
  </tr>
  <tr>
    <td style="padding:10px 20px;border-bottom:1px solid #fdd5a0;width:40%;color:#555;">Order ID</td>
    <td style="padding:10px 20px;border-bottom:1px solid #fdd5a0;font-weight:bold;">#' . $order_id . '</td>
  </tr>
  <tr>
    <td style="padding:10px 20px;border-bottom:1px solid #fdd5a0;color:#555;">Plan</td>
    <td style="padding:10px 20px;border-bottom:1px solid #fdd5a0;">' . htmlspecialchars($plan_name) . '</td>
  </tr>
  <tr>
    <td style="padding:10px 20px;border-bottom:1px solid #fdd5a0;color:#555;">Amount Paid</td>
    <td style="padding:10px 20px;border-bottom:1px solid #fdd5a0;font-weight:bold;color:' . TP_COLOR_ORANGE . ';">
      &#8377;' . number_format($amount, 2) . '
    </td>
  </tr>
  <tr>
    <td style="padding:10px 20px;color:#555;">Payment Date</td>
    <td style="padding:10px 20px;">' . date('d F Y') . '</td>
  </tr>
  ' . ($expiry_row ? '<tr><td style="padding:10px 20px;color:#555;">Valid Until</td><td style="padding:10px 20px;font-weight:bold;">' . date('d F Y', strtotime($expiry_date)) . '</td></tr>' : '') . '
</table>

<p>You now have full access to all <strong>Teacher Plus</strong> premium content,
including the complete article archive, e-magazines, and worksheets.</p>

<p style="margin:28px 0;">
  <a href="' . TP_SITE_URL . '/public/login.php"
     style="background:' . TP_COLOR_ORANGE . ';color:#fff;padding:12px 28px;
            border-radius:5px;text-decoration:none;font-weight:bold;font-size:15px;">
    Start Reading Now
  </a>
</p>

<p style="color:#555;">For any queries, reach us at
<a href="mailto:circulation@teacherplus.org"
   style="color:' . TP_COLOR_ORANGE . ';">circulation@teacherplus.org</a>.</p>

<p>Happy reading!<br><strong>The Teacher Plus Team</strong></p>';

        $mail->Body    = _tp_email_shell('Payment Confirmation', $body);
        $mail->AltBody = strip_tags($body);
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('[TeacherPlus Mailer] Payment confirmation email failed: ' . $e->getMessage());
        return false;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 3. SUBSCRIPTION EXPIRY REMINDER
// ─────────────────────────────────────────────────────────────────────────────
/**
 * @param string $to_email    Subscriber's email
 * @param string $full_name   Subscriber's display name
 * @param string $expiry_date Expiry date (Y-m-d)
 * @param int    $days_left   Days until expiry (for subject line context)
 * @return bool
 */
function tp_send_expiry_reminder(
    string $to_email,
    string $full_name,
    string $expiry_date,
    int    $days_left = 7
): bool {
    try {
        $mail = _tp_mailer();
        $mail->addAddress($to_email, $full_name);

        $urgency = $days_left <= 3 ? 'URGENT: ' : '';
        $mail->Subject = $urgency . 'Your Teacher Plus Subscription Expires in ' . $days_left . ' Day(s)';

        $alert_colour  = $days_left <= 3 ? '#dc3545' : TP_COLOR_ORANGE;
        $formatted_exp = date('d F Y', strtotime($expiry_date));

        $body = '
<p style="font-size:16px;">Dear <strong>' . htmlspecialchars($full_name) . '</strong>,</p>

<p>This is a friendly reminder that your <strong>Teacher Plus</strong> subscription is
expiring soon.</p>

<!-- Alert box -->
<table width="100%" cellpadding="0" cellspacing="0"
       style="background:#fff3cd;border-left:5px solid ' . $alert_colour . ';
              border-radius:4px;margin:20px 0;padding:0;">
  <tr>
    <td style="padding:18px 22px;">
      <p style="margin:0;font-size:16px;font-weight:bold;color:' . $alert_colour . ';">
        ⏰ Subscription expires on ' . $formatted_exp . ' (' . $days_left . ' day(s) remaining)
      </p>
    </td>
  </tr>
</table>

<p>Renew now to continue enjoying:</p>
<ul style="color:#444;line-height:1.9;">
  <li>✅ Full access to all articles and the complete archive</li>
  <li>✅ Monthly e-magazine PDF download</li>
  <li>✅ Classroom worksheets and activity sheets</li>
  <li>✅ Weekly newsletters with curated content</li>
</ul>

<p style="margin:28px 0;">
  <a href="' . TP_SITE_URL . '/public/subscribe-new.php"
     style="background:' . $alert_colour . ';color:#fff;padding:12px 28px;
            border-radius:5px;text-decoration:none;font-weight:bold;font-size:15px;">
    Renew My Subscription
  </a>
</p>

<p style="color:#555;">Need help? Write to us at
<a href="mailto:circulation@teacherplus.org"
   style="color:' . TP_COLOR_ORANGE . ';">circulation@teacherplus.org</a>.</p>

<p>Thank you for being part of the Teacher Plus community!<br>
<strong>The Teacher Plus Team</strong></p>';

        $mail->Body    = _tp_email_shell('Subscription Expiry Reminder', $body);
        $mail->AltBody = strip_tags($body);
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('[TeacherPlus Mailer] Expiry reminder email failed: ' . $e->getMessage());
        return false;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 4. PASSWORD RESET
// ─────────────────────────────────────────────────────────────────────────────
/**
 * @param string $to_email    Subscriber's email
 * @param string $full_name   Subscriber's display name
 * @param string $reset_token Secure random token stored in DB
 * @return bool
 */
function tp_send_password_reset(
    string $to_email,
    string $full_name,
    string $reset_token
): bool {
    try {
        $mail = _tp_mailer();
        $mail->addAddress($to_email, $full_name);
        $mail->Subject = 'Teacher Plus – Password Reset Request';

        $reset_link = TP_SITE_URL . '/public/reset-password.php?token=' . urlencode($reset_token);

        $body = '
<p style="font-size:16px;">Dear <strong>' . htmlspecialchars($full_name) . '</strong>,</p>

<p>We received a request to reset the password for your <strong>Teacher Plus</strong> account.
If you did not make this request, please ignore this email — your account is safe.</p>

<!-- Reset box -->
<table width="100%" cellpadding="0" cellspacing="0"
       style="background:#f0f4ff;border-left:4px solid ' . TP_COLOR_PURPLE . ';
              border-radius:4px;margin:20px 0;padding:0;">
  <tr>
    <td style="padding:18px 22px;">
      <p style="margin:0 0 10px;font-weight:bold;color:' . TP_COLOR_PURPLE . ';">
        🔐 Password Reset Link
      </p>
      <p style="margin:0;font-size:13px;color:#555;">
        This link is valid for <strong>60 minutes</strong> and can only be used once.
      </p>
    </td>
  </tr>
</table>

<p style="margin:28px 0;">
  <a href="' . $reset_link . '"
     style="background:' . TP_COLOR_PURPLE . ';color:#fff;padding:12px 28px;
            border-radius:5px;text-decoration:none;font-weight:bold;font-size:15px;">
    Reset My Password
  </a>
</p>

<p style="font-size:12px;color:#888;word-break:break-all;">
  If the button above does not work, copy and paste this link into your browser:<br>
  <a href="' . $reset_link . '" style="color:' . TP_COLOR_PURPLE . ';">' . $reset_link . '</a>
</p>

<hr style="border:none;border-top:1px solid #eee;margin:28px 0;">

<p style="font-size:13px;color:#888;">
  ⚠️ If you did not request a password reset, please contact us immediately at
  <a href="mailto:circulation@teacherplus.org"
     style="color:' . TP_COLOR_ORANGE . ';">circulation@teacherplus.org</a>.
</p>

<p>Regards,<br><strong>The Teacher Plus Team</strong></p>';

        $mail->Body    = _tp_email_shell('Password Reset Request', $body);
        $mail->AltBody = strip_tags($body);
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('[TeacherPlus Mailer] Password reset email failed: ' . $e->getMessage());
        return false;
    }
}
