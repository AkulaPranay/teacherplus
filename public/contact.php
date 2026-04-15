<?php
require '../includes/config.php';

require '../vendor/PHPMailer/src/PHPMailer.php';
require '../vendor/PHPMailer/src/SMTP.php';
require '../vendor/PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$page_title = "Contact us - Teacher Plus";
$success    = '';
$error      = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name']    ?? '');
    $email   = trim($_POST['email']   ?? '');
    $phone   = trim($_POST['phone']   ?? '');
    $message = trim($_POST['message'] ?? '');

    if (!$name || !$email || !$phone) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // ── Save to DB ────────────────────────────────────────────────────────
        $stmt = $conn->prepare("
            INSERT INTO contact_messages (name, email, phone, message, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        if ($stmt) {
            $stmt->bind_param('ssss', $name, $email, $phone, $message);
            $stmt->execute();
        }

        // ── Send email — identical setup to mail_test.php ────────────────────
        $mail = new PHPMailer(true);

        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            ]
        ];

        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'gaintern2@gmail.com';
        $mail->Password   = 'dtxhbsxworsycfex';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('gaintern2@gmail.com', 'Teacher Plus');
        $mail->addAddress('gaintern2@gmail.com');
        $mail->addReplyTo($email, $name);

        $mail->isHTML(true);
        $mail->Subject = 'New Contact Message from ' . $name;
        $mail->Body    = '
            <html><body style="font-family:Arial,sans-serif;color:#333;">
                <h2 style="color:#f87407;margin-bottom:20px;">New Contact Message</h2>
                <table border="1" cellpadding="10" cellspacing="0"
                       style="border-collapse:collapse;width:100%;max-width:600px;">
                    <tr style="background:#f5f5f5;">
                        <td style="width:120px;font-weight:bold;">Name</td>
                        <td>' . htmlspecialchars($name) . '</td>
                    </tr>
                    <tr>
                        <td style="font-weight:bold;">Email</td>
                        <td><a href="mailto:' . htmlspecialchars($email) . '">'
                            . htmlspecialchars($email) . '</a></td>
                    </tr>
                    <tr style="background:#f5f5f5;">
                        <td style="font-weight:bold;">Phone</td>
                        <td>' . htmlspecialchars($phone) . '</td>
                    </tr>
                    <tr>
                        <td style="font-weight:bold;vertical-align:top;">Message</td>
                        <td>' . nl2br(htmlspecialchars($message)) . '</td>
                    </tr>
                </table>
                <p style="margin-top:20px;font-size:12px;color:#888;">
                    Sent from the Teacher Plus contact form.
                </p>
            </body></html>
        ';
        $mail->AltBody = "Name: $name\nEmail: $email\nPhone: $phone\nMessage:\n$message";

        $mail->send();
        $success = 'Thank you! Your message has been sent successfully. We will get back to you soon.';
    }
}

include '../includes/header.php';
?>

<style>
.contact-page {
    background: #f0f0f0;
    min-height: 500px;
    padding: 50px 0 70px;
}
.contact-inner {
    max-width: 1100px;
    margin: 0 auto;
    padding: 0 20px;
    display: flex;
    gap: 0;
}

/* ── Left panel ── */
.contact-info-panel {
    background: #f0f0f0;
    padding: 30px 50px 40px 30px;
    min-width: 340px;
    flex: 0 0 340px;
}
.contact-info-panel h2 {
    color: #3d348b;
    font-size: 1.5rem;
    font-weight: 700;
    margin: 0 0 6px 0;
    font-family: 'Roboto', sans-serif;
}
.contact-underline {
    width: 36px;
    height: 3px;
    background: #f87407;
    border-radius: 2px;
    margin-bottom: 28px;
}
.info-row {
    display: flex;
    align-items: flex-start;
    gap: 16px;
    margin-bottom: 36px;
}
.info-icon-wrap {
    width: 40px;
    height: 40px;
    border: 1.5px solid #999;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    margin-top: 2px;
    color: #444;
    font-size: 15px;
}
.info-content {
    font-family: 'Roboto', sans-serif;
    font-size: 14px;
    color: #53585c;
    line-height: 1.7;
}
.info-content strong {
    display: block;
    color: #333;
    font-weight: 600;
    margin-bottom: 3px;
}
.info-content a { color: #53585c; text-decoration: none; }
.info-content a:hover { color: #f87407; }

/* ── Right panel ── */
.contact-form-panel {
    background: #f0f0f0;
    padding: 30px 30px 40px 30px;
    flex: 1;
}
.contact-form-panel h2 {
    color: #3d348b;
    font-size: 1.5rem;
    font-weight: 700;
    margin: 0 0 6px 0;
    font-family: 'Roboto', sans-serif;
}
.form-underline {
    width: 36px;
    height: 3px;
    background: #f87407;
    border-radius: 2px;
    margin-bottom: 24px;
}
.contact-form label {
    display: block;
    font-size: 13px;
    color: #444;
    margin-bottom: 5px;
    font-family: 'Roboto', sans-serif;
}
.contact-form label span { color: #dc3545; }
.contact-form input[type="text"],
.contact-form input[type="email"],
.contact-form input[type="tel"],
.contact-form textarea {
    width: 100%;
    border: 1px solid #ccc;
    border-radius: 3px;
    padding: 9px 12px;
    font-size: 14px;
    font-family: 'Roboto', sans-serif;
    color: #53585c;
    background: #fff;
    box-sizing: border-box;
    outline: none;
    transition: border-color 0.2s;
    margin-bottom: 14px;
}
.contact-form input:focus,
.contact-form textarea:focus { border-color: #f87407; }
.contact-form textarea { height: 110px; resize: vertical; }

.recaptcha-wrap { margin: 6px 0 14px; }
.recaptcha-box {
    border: 1px solid #d3d3d3;
    border-radius: 4px;
    background: #f9f9f9;
    padding: 14px 16px;
    display: flex;
    align-items: center;
    gap: 12px;
    width: 240px;
    font-size: 13px;
    color: #444;
}
.recaptcha-box input[type="checkbox"] {
    width: 20px;
    height: 20px;
    cursor: pointer;
    flex-shrink: 0;
}
.recaptcha-logo { margin-left: auto; text-align: center; line-height: 1.3; }

.btn-send {
    background: #c0392b;
    color: #fff;
    border: none;
    border-radius: 3px;
    padding: 10px 24px;
    font-size: 13px;
    font-weight: 700;
    letter-spacing: 0.05em;
    cursor: pointer;
    font-family: 'Roboto', sans-serif;
    transition: background 0.2s;
}
.btn-send:hover { background: #a93226; }

.alert-success-custom {
    background: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
    border-radius: 4px;
    padding: 14px 18px;
    margin-bottom: 16px;
    font-size: 14px;
    font-family: 'Roboto', sans-serif;
}
.alert-error-custom {
    background: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
    border-radius: 4px;
    padding: 14px 18px;
    margin-bottom: 16px;
    font-size: 14px;
    font-family: 'Roboto', sans-serif;
}

@media (max-width: 768px) {
    .contact-inner { flex-direction: column; }
    .contact-info-panel { min-width: 100%; flex: none; padding: 30px 20px; }
}
</style>

<div class="contact-page">
    <div class="contact-inner">

        <!-- LEFT — Contact Info -->
        <div class="contact-info-panel">
            <h2>Contact us</h2>
            <div class="contact-underline"></div>

            <div class="info-row">
                <div class="info-icon-wrap">
                    <i class="fas fa-map-marker-alt"></i>
                </div>
                <div class="info-content">
                    <strong>Address:</strong>
                    Teacher Plus<br>
                    A15, Vikrampuri<br>
                    Secunderabad<br>
                    Telangana &ndash; 500009<br>
                    India
                </div>
            </div>

            <div class="info-row">
                <div class="info-icon-wrap">
                    <i class="fas fa-envelope"></i>
                </div>
                <div class="info-content">
                    <strong>Email:</strong>
                    <a href="mailto:circulation@teacherplus.org">circulation@teacherplus.org</a>
                </div>
            </div>

            <div class="info-row">
                <div class="info-icon-wrap">
                    <i class="fas fa-phone-alt"></i>
                </div>
                <div class="info-content">
                    <strong>Phone:</strong>
                    <a href="tel:+918977942097">+91 89779 42097</a>
                </div>
            </div>
        </div>

        <!-- RIGHT — Message Form -->
        <div class="contact-form-panel">
            <h2>Send your message</h2>
            <div class="form-underline"></div>

            <?php if ($success): ?>
                <div class="alert-success-custom">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert-error-custom">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if (!$success): ?>
            <form class="contact-form" method="post" action="">

                <label>Name<span>*</span></label>
                <input type="text" name="name"
                       value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required>

                <label>Email<span>*</span></label>
                <input type="email" name="email"
                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>

                <label>Phone<span>*</span></label>
                <input type="tel" name="phone"
                       value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" required>

                <label>Message (optional)</label>
                <textarea name="message"><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>

                <p style="font-size:13px;color:#555;margin-bottom:6px;">recaptcha</p>
                <div class="recaptcha-wrap">
                    <div class="recaptcha-box">
                        <input type="checkbox" id="not-robot" required>
                        <label for="not-robot" style="margin:0;cursor:pointer;font-size:13px;">
                            I'm not a robot
                        </label>
                        <div class="recaptcha-logo">
                            <i class="fas fa-shield-alt" style="font-size:20px;color:#4a90d9;"></i><br>
                            <span style="font-size:9px;color:#888;">reCAPTCHA<br>Privacy &middot; Terms</span>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn-send">SEND</button>

            </form>
            <?php endif; ?>
        </div>

    </div>
</div>

<?php include '../includes/footer.php'; ?>