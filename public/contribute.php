<?php
session_start();
require '../includes/config.php';

// PHPMailer must be loaded at the TOP of the file (not inside any if/else)
require '../vendor/PHPMailer/src/PHPMailer.php';
require '../vendor/PHPMailer/src/SMTP.php';
require '../vendor/PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Fetch staff emails from database
$staff_emails = [];
$staff_result = $conn->query("SELECT email FROM users WHERE role = 'staff' AND email IS NOT NULL");
while ($row = $staff_result->fetch_assoc()) {
    if (filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
        $staff_emails[] = $row['email'];
    }
}

$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION['contrib'] = array_merge($_SESSION['contrib'] ?? [], $_POST);

    if (isset($_POST['next']) && $step < 3) {
        header("Location: contribute.php?step=" . ($step + 1));
        exit;
    }

    if (isset($_POST['previous']) && $step > 1) {
        header("Location: contribute.php?step=" . ($step - 1));
        exit;
    }

    if (isset($_POST['submit'])) {
        $d = $_SESSION['contrib'];

        // Required fields check
        if (empty($d['title']) || empty($d['body']) || empty($d['author_name']) || empty($d['author_email'])) {
            $errors[] = "Please fill all required fields (*)";
        }

        $featured_image = null;
        if (!empty($_FILES['featured_image']['name'])) {
            $upload_dir = '../uploads/contributions/' . date('Y-m') . '/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $ext = strtolower(pathinfo($_FILES['featured_image']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

            if (in_array($ext, $allowed) && $_FILES['featured_image']['size'] < 3000000) {
                $filename = time() . '-featured.' . $ext;
                $target = $upload_dir . $filename;
                if (move_uploaded_file($_FILES['featured_image']['tmp_name'], $target)) {
                    $featured_image = 'uploads/contributions/' . date('Y-m') . '/' . $filename;
                } else {
                    $errors[] = "Failed to save image.";
                }
            } else {
                $errors[] = "Invalid image (jpg/png/gif/webp, max 3MB)";
            }
        }

        if (empty($errors)) {
            $stmt = $conn->prepare("
                INSERT INTO pending_contributions (
                    title, subtitle, category, tags, body, summary,
                    author_name, author_email, author_phone, author_bio,
                    featured_image, image_captions,	article_references, author_notes,
                    additional_comments, social_media, author_website, newsletter
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("ssssssssssssssssss",
                $d['title'], $d['subtitle'], $d['category'], $d['tags'],
                $d['body'], $d['summary'],
                $d['author_name'], $d['author_email'], $d['author_phone'], $d['author_bio'],
                $featured_image, $d['image_captions'], $d['article_references'], $d['author_notes'],
                $d['additional_comments'], $d['social_media'], $d['author_website'], $d['newsletter']
            );

            if ($stmt->execute()) {
                $new_id = $conn->insert_id;
                unset($_SESSION['contrib']);
                $success = "Thank you! Your article has been submitted for review.";

                // Send email to staff using PHPMailer
                if (!empty($staff_emails)) {
                    $mail = new PHPMailer(true);
                 
$mail->SMTPOptions = [
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false,
        'allow_self_signed' => true
    ]
];

                    try {
                        // SMTP settings - CHANGE THESE
                        $mail->isSMTP();
                        $mail->Host       = 'smtp.gmail.com';
                        $mail->SMTPAuth   = true;
                        $mail->Username   = 'gaintern2@gmail.com';          // ← CHANGE TO YOUR EMAIL
                        $mail->Password   = 'dtxhbsxworsycfex';       // ← CHANGE TO APP PASSWORD
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                        $mail->Port       = 587;
                       
                        $mail->setFrom('no-reply@teacherplus.org', 'TeacherPlus');
                        foreach ($staff_emails as $email) {
                            $mail->addAddress($email);
                        }

                        $mail->isHTML(true);
                        $mail->Subject = 'New Contribution Submitted - ID #' . $new_id;

                        $mail->Body = '
                        <html>
                        <body style="font-family:Arial,sans-serif;">
                            <h2>New Article Contribution</h2>
                            <p>A new article has been submitted for review.</p>

                            <table border="1" cellpadding="8" style="border-collapse:collapse; width:100%;">
                                <tr><td><b>Title</b></td><td>' . htmlspecialchars($d['title']) . '</td></tr>
                                <tr><td><b>Author</b></td><td>' . htmlspecialchars($d['author_name']) . '</td></tr>
                                <tr><td><b>Email</b></td><td>' . htmlspecialchars($d['author_email']) . '</td></tr>
                                <tr><td><b>Phone</b></td><td>' . htmlspecialchars($d['author_phone'] ?? '-') . '</td></tr>
                                <tr><td><b>Category</b></td><td>' . htmlspecialchars($d['category'] ?? '-') . '</td></tr>
                                <tr><td><b>Tags</b></td><td>' . htmlspecialchars($d['tags'] ?? '-') . '</td></tr>
                                <tr><td><b>Image</b></td><td>' . 
                                    ($featured_image ? '<a href="http://localhost/teacherplus/' . $featured_image . '">View Image</a>' : 'No image') . 
                                '</td></tr>
                            </table>

                            <h4>Body Preview:</h4>
                            <pre style="background:#f8f9fa; padding:15px;">' . 
                            htmlspecialchars(substr($d['body'], 0, 500)) . (strlen($d['body']) > 500 ? '...' : '') . 
                            '</pre>

                            <p><b>Review here:</b><br>
                            <a href="http://localhost/teacherplus/admin/content/pending-contributions.php">Pending Contributions</a></p>

                            <p style="color:#777;">Submitted: ' . date('d M Y H:i:s') . '</p>
                        </body>
                        </html>';

                        $mail->send();
                    } catch (Exception $e) {
                        error_log("PHPMailer Error: {$mail->ErrorInfo}");
                        $errors[] = "Email could not be sent (check logs).";
                    }
                }
            } else {
                $errors[] = "Database error.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contribute Article</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background-color: #f5f5f5;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        
        main {
            flex: 1;
        }
        
        .container {
            max-width: 1000px;
        }
        
        /* Step Progress */
        .step-progress {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 40px 0 50px 0;
            position: relative;
        }
        
        .step-progress::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 0;
            right: 0;
            height: 2px;
            background-color: #ddd;
            z-index: 0;
        }
        
        .step-item {
            text-align: center;
            flex: 1;
            position: relative;
            z-index: 1;
        }
        
        .step-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #ddd;
            color: white;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-bottom: 10px;
            font-size: 16px;
        }
        
        .step-item.active .step-circle {
            background-color: #28a745;
        }
        
        .step-item.completed .step-circle {
            background-color: #28a745;
        }
        
        .step-text {
            font-size: 14px;
            color: #666;
        }
        
        .step-item.active .step-text {
            font-weight: bold;
            color: #333;
        }
        
        /* Form Styling */
        .form-section {
            background: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .section-title {
            color: #ff6b35;
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            display: block;
        }
        
        .form-group label .required {
            color: #ff6b35;
        }
        
        .form-control {
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 10px 12px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            border-color: #ff6b35;
            box-shadow: 0 0 0 0.2rem rgba(255, 107, 53, 0.25);
        }
        
        textarea.form-control {
            resize: vertical;
            min-height: 120px;
        }
        
        .form-text {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        
        /* Row with multiple columns */
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        /* Button styling */
        .btn-container {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            margin-top: 40px;
        }
        
        .btn-primary, .btn-success {
            padding: 10px 30px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
        }
        
        .btn-primary {
            background-color: #007bff;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #0056b3;
        }
        
        .btn-success {
            background-color: #ff6b35;
            color: white;
        }
        
        .btn-success:hover {
            background-color: #e55a24;
        }
        
        .btn-secondary {
            background-color: #6c757d;
            color: white;
            padding: 10px 30px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        
        .alert {
            margin-bottom: 25px;
            border-radius: 4px;
        }
        
        .alert-success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        
        .form-check {
            margin-bottom: 15px;
        }
        
        .form-check-input {
            width: 18px;
            height: 18px;
            margin-top: 2px;
        }
        
        .form-check-label {
            margin-left: 8px;
        }
        
        .file-input-wrapper {
            position: relative;
            display: inline-block;
        }
        
        .file-input-label {
            display: inline-block;
            padding: 8px 15px;
            background-color: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .file-input-label:hover {
            background-color: #e9ecef;
        }
        
        input[type="file"] {
            display: none;
        }
        
        .file-name {
            color: #666;
            font-size: 13px;
            margin-left: 10px;
            display: inline-block;
        }
    </style>
</head>
<body class="bg-light">

<?php include '../includes/header.php'; ?>

<!-- Main Content -->
<main>

<div class="container mt-5 mb-5">
    <!-- Step Progress -->
    <div class="step-progress">
        <div class="step-item <?php echo $step >= 1 ? 'active' : ''; ?> <?php echo $step > 1 ? 'completed' : ''; ?>">
            <div class="step-circle">1</div>
            <div class="step-text">Step 1</div>
        </div>
        <div class="step-item <?php echo $step >= 2 ? 'active' : ''; ?> <?php echo $step > 2 ? 'completed' : ''; ?>">
            <div class="step-circle">2</div>
            <div class="step-text">Step 2</div>
        </div>
        <div class="step-item <?php echo $step >= 3 ? 'active' : ''; ?> <?php echo $step > 3 ? 'completed' : ''; ?>">
            <div class="step-circle">3</div>
            <div class="step-text">Step 3</div>
        </div>
    </div>

    <!-- Success/Error Messages -->
    <?php if ($success): ?>
        <div class="alert alert-success">
            <strong>Success!</strong> <?php echo $success; ?>
        </div>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="alert alert-danger">
            <strong>Error:</strong>
            <?php foreach ($errors as $err): ?>
                <div><?php echo htmlspecialchars($err); ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Form Section -->
    <div class="form-section">
        <form method="post" enctype="multipart/form-data">

            <!-- STEP 1: Author Details -->
            <?php if ($step == 1): ?>
                <div class="section-title">Author Details</div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Name <span class="required">*</span></label>
                        <input type="text" name="author_name" class="form-control" required placeholder="Your full name" value="<?php echo htmlspecialchars($_SESSION['contrib']['author_name'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Email <span class="required">*</span></label>
                        <input type="email" name="author_email" class="form-control" required placeholder="Your email address" value="<?php echo htmlspecialchars($_SESSION['contrib']['author_email'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Phone <span class="required">*</span></label>
                        <input type="text" name="author_phone" class="form-control" required placeholder="Your phone number" value="<?php echo htmlspecialchars($_SESSION['contrib']['author_phone'] ?? ''); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Bio / Message</label>
                    <textarea name="author_bio" class="form-control" placeholder="Tell us about yourself"><?php echo htmlspecialchars($_SESSION['contrib']['author_bio'] ?? ''); ?></textarea>
                </div>

                <div class="form-group">
                    <label>Upload Your Photo</label>
                    <div class="file-input-wrapper">
                        <label class="file-input-label" for="author_photo">Choose File</label>
                        <input type="file" name="author_photo" id="author_photo" accept="image/*" onchange="updateFileName(this, 'author_photo_name')">
                        <span class="file-name" id="author_photo_name">No file chosen</span>
                    </div>
                </div>

                <div class="btn-container">
                    <div></div>
                    <button type="submit" name="next" class="btn btn-primary">Next</button>
                </div>

            <!-- STEP 2: Article Details -->
            <?php elseif ($step == 2): ?>
                <div class="section-title">Article Details</div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Article Title <span class="required">*</span></label>
                        <input type="text" name="title" class="form-control" required placeholder="e.g., AI in Education" value="<?php echo htmlspecialchars($_SESSION['contrib']['title'] ?? ''); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Subtitle</label>
                    <input type="text" name="subtitle" class="form-control" placeholder="A brief subtitle for your article" value="<?php echo htmlspecialchars($_SESSION['contrib']['subtitle'] ?? ''); ?>">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Category</label>
                        <input type="text" name="category" class="form-control" placeholder="The category under which the article falls (e.g., Technology, Health, Travel)" value="<?php echo htmlspecialchars($_SESSION['contrib']['category'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Tags / Keywords</label>
                        <input type="text" name="tags" class="form-control" placeholder="e.g., AI, Education, Hallmarks" value="<?php echo htmlspecialchars($_SESSION['contrib']['tags'] ?? ''); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Article Body <span class="required">*</span></label>
                    <textarea name="body" class="form-control" required placeholder="Main content of the article"><?php echo htmlspecialchars($_SESSION['contrib']['body'] ?? ''); ?></textarea>
                </div>

                <div class="form-group">
                    <label>Summary / Abstract</label>
                    <textarea name="summary" class="form-control" placeholder="Brief summary of the article"><?php echo htmlspecialchars($_SESSION['contrib']['summary'] ?? ''); ?></textarea>
                    <div class="form-text">Brief summary of the article</div>
                </div>

                <div class="form-group">
                    <label>Image Captions</label>
                    <textarea name="image_captions" class="form-control" placeholder="Captions for uploaded images"><?php echo htmlspecialchars($_SESSION['contrib']['image_captions'] ?? ''); ?></textarea>
                    <div class="form-text">Captions for uploaded images</div>
                </div>

                <div class="form-group">
                    <label>References / Sources</label>
                    <textarea name="references" class="form-control" placeholder="Any references or sources cited in the article"><?php echo htmlspecialchars($_SESSION['contrib']['article_references'] ?? ''); ?></textarea>
                    <div class="form-text">Any references or sources cited in the article</div>
                </div>

                <div class="form-group">
                    <label>Author Notes</label>
                    <textarea name="author_notes" class="form-control" placeholder="Any additional notes or comments from the author"><?php echo htmlspecialchars($_SESSION['contrib']['author_notes'] ?? ''); ?></textarea>
                    <div class="form-text">Any additional notes or comments from the author</div>
                </div>

                <div class="form-group">
                    <label>Additional Comments</label>
                    <textarea name="additional_comments" class="form-control" placeholder="Any additional comments or information the author wants to provide"><?php echo htmlspecialchars($_SESSION['contrib']['additional_comments'] ?? ''); ?></textarea>
                    <div class="form-text">Any additional comments or information the author wants to provide</div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Featured Image</label>
                        <div class="file-input-wrapper">
                            <label class="file-input-label" for="featured_image">Choose File</label>
                            <input type="file" name="featured_image" id="featured_image" accept="image/*" onchange="updateFileName(this, 'featured_image_name')">
                            <span class="file-name" id="featured_image_name">No file chosen</span>
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Supporting Images / Media</label>
                        <div class="file-input-wrapper">
                            <label class="file-input-label" for="supporting_images">Choose File</label>
                            <input type="file" name="supporting_images" id="supporting_images" accept="image/*,video/*" onchange="updateFileName(this, 'supporting_images_name')">
                            <span class="file-name" id="supporting_images_name">No file chosen</span>
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Article File</label>
                        <div class="file-input-wrapper">
                            <label class="file-input-label" for="article_file">Choose File</label>
                            <input type="file" name="article_file" id="article_file" accept=".pdf,.doc,.docx,.txt" onchange="updateFileName(this, 'article_file_name')">
                            <span class="file-name" id="article_file_name">No file chosen</span>
                        </div>
                    </div>
                </div>

                <div class="btn-container">
                    <button type="submit" name="previous" class="btn btn-secondary">Previous</button>
                    <button type="submit" name="next" class="btn btn-primary">Next</button>
                </div>

            <!-- STEP 3: Other Fields (Optional) -->
            <?php elseif ($step == 3): ?>
                <div class="section-title">Other Fields (Optional)</div>
                
                <div class="form-group">
                    <label>Social Media Links</label>
                    <input type="text" name="social_media" class="form-control" placeholder="Your social media profiles" value="<?php echo htmlspecialchars($_SESSION['contrib']['social_media'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label>Author Website / Blog</label>
                    <input type="text" name="author_website" class="form-control" placeholder="Your website or blog" value="<?php echo htmlspecialchars($_SESSION['contrib']['author_website'] ?? ''); ?>">
                </div>

                <div class="form-check">
                    <input type="checkbox" name="privacy_policy" class="form-check-input" id="privacy" required>
                    <label class="form-check-label" for="privacy">
                        I agree to the privacy policy.
                    </label>
                </div>

                <div class="form-check">
                    <input type="checkbox" name="newsletter" class="form-check-input" id="newsletter" value="1">
                    <label class="form-check-label" for="newsletter">
                        Subscribe to our newsletter
                    </label>
                </div>

                <!-- reCAPTCHA -->
                <div class="g-recaptcha" data-sitekey="YOUR_RECAPTCHA_SITE_KEY" style="margin-bottom: 20px;"></div>
                <script src="https://www.google.com/recaptcha/api.js" async defer></script>

                <div class="btn-container">
                    <button type="submit" name="previous" class="btn btn-secondary">Previous</button>
                    <button type="submit" name="submit" class="btn btn-success">Submit</button>
                </div>
            <?php endif; ?>

        </form>
    </div>
</div>

</main>

<?php include '../includes/footer.php'; ?>

<script>
function updateFileName(input, spanId) {
    const fileName = input.files.length > 0 ? input.files[0].name : 'No file chosen';
    document.getElementById(spanId).textContent = fileName;
}
</script>

</body>
</html>