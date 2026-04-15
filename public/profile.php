<?php
require '../includes/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'subscriber') {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$upload_dir = '../assets/uploads/profiles/';

// Create upload dir if it doesn't exist
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

$msg = '';
$msg_type = '';

// Handle photo uploads — before any output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    // Profile photo
    if (!empty($_FILES['profile_photo']['name'])) {
        $file = $_FILES['profile_photo'];
        if (!in_array($file['type'], $allowed)) {
            $msg = "Profile photo must be JPG, PNG, WEBP or GIF."; $msg_type = 'error';
        } elseif ($file['size'] > 2 * 1024 * 1024) {
            $msg = "Profile photo must be under 2MB."; $msg_type = 'error';
        } else {
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'avatar_' . $user_id . '.' . $ext;
            if (move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
                $s = $conn->prepare("UPDATE users SET profile_photo = ? WHERE id = ?");
                $s->bind_param('si', $filename, $user_id); $s->execute();
                $msg = "Profile photo updated!"; $msg_type = 'success';
            } else { $msg = "Failed to upload profile photo."; $msg_type = 'error'; }
        }
    }

    // Cover photo
    if (!empty($_FILES['cover_photo']['name'])) {
        $file = $_FILES['cover_photo'];
        if (!in_array($file['type'], $allowed)) {
            $msg = "Cover photo must be JPG, PNG, WEBP or GIF."; $msg_type = 'error';
        } elseif ($file['size'] > 5 * 1024 * 1024) {
            $msg = "Cover photo must be under 5MB."; $msg_type = 'error';
        } else {
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'cover_' . $user_id . '.' . $ext;
            if (move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
                $s = $conn->prepare("UPDATE users SET cover_photo = ? WHERE id = ?");
                $s->bind_param('si', $filename, $user_id); $s->execute();
                $msg = "Cover photo updated!"; $msg_type = 'success';
            } else { $msg = "Failed to upload cover photo."; $msg_type = 'error'; }
        }
    }
}

// Fetch user + subscription info
$stmt = $conn->prepare("
    SELECT u.id, u.username, u.full_name, u.email, u.phone, u.created_at,
           u.profile_photo, u.cover_photo,
           so.plan_name,
           DATE_ADD(so.created_at, INTERVAL COALESCE(sp.duration_months,1) MONTH) AS expire_date
    FROM users u
    LEFT JOIN subscription_orders so ON so.user_id = u.id AND so.status IN ('active','approved','pending')
    LEFT JOIN subscription_plans sp ON sp.id = so.plan_id
    WHERE u.id = ?
    ORDER BY so.created_at DESC
    LIMIT 1
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

$name_parts   = explode(' ', trim($user['full_name'] ?? ''), 2);
$first_name   = $name_parts[0] ?? '';
$last_name    = $name_parts[1] ?? '';
$member_since = !empty($user['created_at']) ? date('F j, Y', strtotime($user['created_at'])) : '';
$expire_date  = !empty($user['expire_date']) ? date('F j, Y', strtotime($user['expire_date'])) : '';

$profile_photo_url = !empty($user['profile_photo'])
    ? '../assets/uploads/profiles/' . htmlspecialchars($user['profile_photo']) : null;
$cover_photo_url = !empty($user['cover_photo'])
    ? '../assets/uploads/profiles/' . htmlspecialchars($user['cover_photo']) : null;

$page_title = "My Profile - TeacherPlus";
include '../includes/header.php';
?>

<style>
    body { background: #f4f4f4; }

    .profile-wrapper { max-width: 870px; margin: 40px auto 60px; padding: 0 20px; }

    .flash { padding: 10px 16px; border-radius: 6px; margin-bottom: 16px; font-size: 0.875rem; }
    .flash.success { background: #d1fae5; border: 1px solid #6ee7b7; color: #065f46; }
    .flash.error   { background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; }

    /* Banner */
    .banner-wrap {
        width: 100%;
        height: 180px;
        border-radius: 12px 12px 0 0;
        position: relative;
        background-color: #b0b8c1;
        background-size: cover;
        background-position: center;
        background-repeat: no-repeat;
        cursor: pointer;
        overflow: hidden;
    }

    .banner-wrap img { display: none; }

    .banner-overlay {
        position: absolute;
        inset: 0;
        background: rgba(0,0,0,0);
        display: flex;
        align-items: center;
        justify-content: center;
        transition: background 0.2s;
    }

    .banner-wrap:hover .banner-overlay { background: rgba(0,0,0,0.4); }

    .banner-overlay span {
        color: #fff;
        font-size: 0.85rem;
        font-weight: 600;
        background: rgba(0,0,0,0.5);
        padding: 7px 16px;
        border-radius: 20px;
        opacity: 0;
        transition: opacity 0.2s;
    }

    .banner-wrap:hover .banner-overlay span { opacity: 1; }

    /* Identity row */
    .profile-identity {
        display: flex;
        align-items: flex-end;
        gap: 20px;
        margin-top: -48px;
        padding: 0 10px 18px;
        background: #fff;
    }

    /* Avatar */
    .avatar-wrap {
        width: 90px;
        height: 90px;
        border-radius: 50%;
        border: 3px solid #2563eb;
        background: #d1d5db;
        flex-shrink: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        margin-bottom: 6px;
        position: relative;
        cursor: pointer;
    }

    .avatar-wrap img { width: 100%; height: 100%; object-fit: cover; }
    .avatar-wrap > svg { width: 56px; height: 56px; color: #9ca3af; }

    .avatar-overlay {
        position: absolute;
        inset: 0;
        background: rgba(0,0,0,0);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: background 0.2s;
    }

    .avatar-wrap:hover .avatar-overlay { background: rgba(0,0,0,0.45); }

    .avatar-overlay svg { width: 22px; height: 22px; color: #fff; opacity: 0; transition: opacity 0.2s; }
    .avatar-wrap:hover .avatar-overlay svg { opacity: 1; }

    .identity-text { padding-bottom: 8px; }
    .identity-text .user-name { font-size: 1.1rem; font-weight: 600; color: #1a1a2e; margin: 0 0 4px; }
    .identity-text .member-since { font-size: 0.85rem; color: #666; margin: 0; }

    /* Details card */
    .details-card {
        background: #fff;
        border: 1px solid #e0e0e0;
        border-radius: 10px;
        margin-top: 28px;
        position: relative;
        padding-top: 10px;
    }

    .details-card-label {
        position: absolute;
        top: -18px;
        left: 50%;
        transform: translateX(-50%);
        background: #e8edf5;
        color: #1a1a2e;
        font-weight: 700;
        font-size: 0.95rem;
        padding: 7px 24px;
        border-radius: 20px;
        white-space: nowrap;
    }

    .details-row { display: flex; align-items: center; padding: 16px 30px; border-bottom: 1px solid #ececec; }
    .details-row:last-child { border-bottom: none; }
    .details-label { flex: 0 0 45%; font-size: 0.9rem; color: #444; }
    .details-value { flex: 1; font-size: 0.9rem; color: #222; }

    input[type="file"].hidden-input { display: none; }
</style>

<div class="profile-wrapper">

    <?php if ($msg): ?>
        <div class="flash <?php echo $msg_type; ?>"><?php echo htmlspecialchars($msg); ?></div>
    <?php endif; ?>

    <!-- Cover photo form -->
    <form method="POST" enctype="multipart/form-data" id="cover-form">
        <input type="file" name="cover_photo" id="cover-input" class="hidden-input" accept="image/*"
               onchange="document.getElementById('cover-form').submit()">
    </form>

    <!-- Profile photo form -->
    <form method="POST" enctype="multipart/form-data" id="avatar-form">
        <input type="file" name="profile_photo" id="avatar-input" class="hidden-input" accept="image/*"
               onchange="document.getElementById('avatar-form').submit()">
    </form>

    <!-- Banner — click to change cover -->
    <div class="banner-wrap"
         onclick="document.getElementById('cover-input').click()"
         title="Click to change cover photo"
         <?php if ($cover_photo_url): ?>
         style="background-image: url('<?php echo $cover_photo_url; ?>');"
         <?php endif; ?>>
        <div class="banner-overlay">
            <span>&#128247; Change Cover Photo</span>
        </div>
    </div>

    <!-- Avatar + Name -->
    <div class="profile-identity">
        <div class="avatar-wrap" onclick="document.getElementById('avatar-input').click()" title="Click to change profile photo">
            <?php if ($profile_photo_url): ?>
                <img src="<?php echo $profile_photo_url; ?>" alt="Profile Photo">
            <?php else: ?>
                <svg viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z"/>
                </svg>
            <?php endif; ?>
            <div class="avatar-overlay">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
                    <circle cx="12" cy="13" r="4"/>
                </svg>
            </div>
        </div>
        <div class="identity-text">
            <p class="user-name"><?php echo htmlspecialchars($user['full_name'] ?? ''); ?></p>
            <p class="member-since">Member Since <?php echo $member_since; ?></p>
        </div>
    </div>

    <!-- Personal Details -->
    <div class="details-card">
        <div class="details-card-label">Personal Details</div>

        <div class="details-row">
            <div class="details-label">Username</div>
            <div class="details-value"><?php echo htmlspecialchars($user['username'] ?? ''); ?></div>
        </div>
        <div class="details-row">
            <div class="details-label">Email Address</div>
            <div class="details-value"><?php echo htmlspecialchars($user['email'] ?? ''); ?></div>
        </div>
        <div class="details-row">
            <div class="details-label">First Name</div>
            <div class="details-value"><?php echo htmlspecialchars($first_name); ?></div>
        </div>
        <div class="details-row">
            <div class="details-label">Last Name</div>
            <div class="details-value"><?php echo htmlspecialchars($last_name); ?></div>
        </div>
        <div class="details-row">
            <div class="details-label">Phone Number</div>
            <div class="details-value"><?php echo htmlspecialchars($user['phone'] ?? ''); ?></div>
        </div>
        <div class="details-row">
            <div class="details-label">Membership Plan</div>
            <div class="details-value"><?php echo htmlspecialchars($user['plan_name'] ?? ''); ?></div>
        </div>
        <div class="details-row">
            <div class="details-label">Membership Plan Expire/Due Date</div>
            <div class="details-value"><?php echo $expire_date; ?></div>
        </div>
    </div>

</div>

<?php include '../includes/footer.php'; ?>