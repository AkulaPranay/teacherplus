<?php
require '../includes/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'subscriber') {
    header("Location: login.php");
    exit;
}


$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username   = trim($_POST['username'] ?? '');
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name'] ?? '');
    $phone      = trim($_POST['phone'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $status     = trim($_POST['status'] ?? '');

    if (empty($username) || empty($email)) {
        $error = "Username and Email are required.";
    } else {
        $full_name = $first_name . ' ' . $last_name;
        $stmt = $conn->prepare("UPDATE users SET username=?, full_name=?, email=?, phone=? WHERE id=?");
        $stmt->bind_param("ssssi", $username, $full_name, $email, $phone, $user_id);

        if ($stmt->execute()) {
            $_SESSION['username'] = $username;
            $_SESSION['full_name'] = $full_name;
            $success = "Profile updated successfully!";
        } else {
            $error = "Failed to update profile.";
        }
    }
}

// Fetch current data
$stmt = $conn->prepare("SELECT username, full_name, email, phone FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

$name_parts = explode(' ', trim($user['full_name'] ?? ''), 2);
$first_name = $name_parts[0] ?? '';
$last_name  = $name_parts[1] ?? '';

$page_title = "Edit Profile - TeacherPlus";
include '../includes/header.php';
?>

<style>
    body { background: #f0f2f5; }

    .edit-profile-wrapper {
        max-width: 500px;
        margin: 50px auto 60px;
        padding: 0 16px;
    }

    .edit-profile-card {
        background: #fff;
        border: 1px solid #e0e0e0;
        border-radius: 16px;
        padding: 36px 40px 40px;
    }

    .edit-profile-card h2 {
        text-align: center;
        font-size: 1.4rem;
        font-weight: 700;
        color: #1a2a4a;
        margin-bottom: 28px;
    }

    /* Floating label field */
    .float-field {
        position: relative;
        margin-bottom: 18px;
    }

    .float-field input {
        width: 100%;
        padding: 22px 14px 8px;
        border: 1px solid #d0d5e0;
        border-radius: 10px;
        font-size: 0.95rem;
        color: #222;
        background: #fff;
        outline: none;
        box-sizing: border-box;
        transition: border-color 0.2s;
    }

    .float-field input:focus {
        border-color: #2563eb;
    }

    .float-field label {
        position: absolute;
        top: 8px;
        left: 14px;
        font-size: 0.72rem;
        color: #888;
        pointer-events: none;
        font-weight: 500;
    }

    /* Plain placeholder field (no floating label — Phone, Status) */
    .plain-field {
        margin-bottom: 18px;
    }

    .plain-field input {
        width: 100%;
        padding: 14px 16px;
        border: 1px solid #d0d5e0;
        border-radius: 10px;
        font-size: 0.95rem;
        color: #222;
        background: #fff;
        outline: none;
        box-sizing: border-box;
        transition: border-color 0.2s;
    }

    .plain-field input::placeholder {
        color: #aaa;
    }

    .plain-field input:focus {
        border-color: #2563eb;
    }

    /* Password field with eye icon */
    .password-field {
        position: relative;
        margin-bottom: 18px;
    }

    .password-field input {
        width: 100%;
        padding: 14px 44px 14px 16px;
        border: 1px solid #d0d5e0;
        border-radius: 10px;
        font-size: 0.95rem;
        color: #222;
        background: #fff;
        outline: none;
        box-sizing: border-box;
        transition: border-color 0.2s;
    }

    .password-field input::placeholder { color: #aaa; }
    .password-field input:focus { border-color: #2563eb; }

    .password-field .eye-icon {
        position: absolute;
        right: 14px;
        top: 50%;
        transform: translateY(-50%);
        cursor: pointer;
        color: #aaa;
        font-size: 1rem;
        user-select: none;
    }

    /* Submit button */
    .btn-submit {
        width: 100%;
        padding: 15px;
        background: #2563eb;
        color: #fff;
        border: none;
        border-radius: 10px;
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        margin-top: 10px;
        transition: background 0.2s;
    }

    .btn-submit:hover { background: #1d4ed8; }

    .alert {
        padding: 12px 16px;
        border-radius: 8px;
        margin-bottom: 18px;
        font-size: 0.9rem;
    }

    .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
    .alert-danger  { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
</style>

<div class="edit-profile-wrapper">
    <div class="edit-profile-card">
        <h2>Edit Profile</h2>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST">
            <!-- Username -->
            <div class="float-field">
                <label>* Username</label>
                <input type="text" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
            </div>

            <!-- First Name -->
            <div class="float-field">
                <label>* First Name</label>
                <input type="text" name="first_name" value="<?php echo htmlspecialchars($first_name); ?>" required>
            </div>

            <!-- Last Name -->
            <div class="float-field">
                <label>* Last Name</label>
                <input type="text" name="last_name" value="<?php echo htmlspecialchars($last_name); ?>" required>
            </div>

            <!-- Phone Number (floating label, with value) -->
            <div class="float-field">
                <label>Phone Number</label>
                <input type="text" name="phone_number" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
            </div>

            <!-- Email Address -->
            <div class="float-field">
                <label>* Email Address</label>
                <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
            </div>

            <!-- Password -->
            <div class="password-field">
                <input type="password" name="password" id="password" placeholder="Password">
                <span class="eye-icon" onclick="togglePw()">&#128065;</span>
            </div>

            <!-- Phone (plain placeholder) -->
            <div class="plain-field">
                <input type="text" name="phone" placeholder="Phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
            </div>

            <!-- Status (plain placeholder) -->
            <div class="plain-field">
                <input type="text" name="status" placeholder="Status">
            </div>

            <button type="submit" class="btn-submit">Submit</button>
        </form>
    </div>
</div>

<script>
function togglePw() {
    const pw = document.getElementById('password');
    pw.type = pw.type === 'password' ? 'text' : 'password';
}
</script>

<?php include '../includes/footer.php'; ?>