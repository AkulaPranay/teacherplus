<?php
session_start();
require '../includes/config.php';
require '../includes/functions.php';

if ($_SESSION['role'] !== 'admin') {
    header("Location: ../public/login.php");
    exit;
}

// Handle delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM subscription_plans WHERE id = $id");
    header("Location: subscription-plan.php?msg=deleted");
    exit;
}

// Handle add / edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id               = (int)($_POST['id'] ?? 0);
    $name             = trim($_POST['name'] ?? '');
    $price            = (float)($_POST['price'] ?? 0);
    $duration_months  = (int)($_POST['duration_months'] ?? 0);

    if (!empty($name) && $price > 0 && $duration_months > 0) {
        if ($id > 0) {
            $stmt = $conn->prepare("UPDATE subscription_plans SET name=?, price=?, duration_months=? WHERE id=?");
            $stmt->bind_param("sdii", $name, $price, $duration_months, $id);
        } else {
            $stmt = $conn->prepare("INSERT INTO subscription_plans (name, price, duration_months) VALUES (?, ?, ?)");
            $stmt->bind_param("sdi", $name, $price, $duration_months);
        }
        $stmt->execute();
        header("Location: subscription-plan.php?msg=saved");
        exit;
    }
}

// Fetch plans
$plans = $conn->query("
    SELECT id, name, price, duration_months
    FROM subscription_plans
    ORDER BY duration_months ASC, price ASC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscription Plans - TeacherPlus Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/admin.css">
    <style>
        body { background: #f5f7fa; margin: 0; }

        .main-content {
            margin-left: 260px;
            padding: 40px 30px;
            min-height: 100vh;
        }

        /* Plan cards */
        .plan-card {
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 6px 20px rgba(0,0,0,0.08);
            transition: transform 0.3s;
        }
        .plan-card:hover { transform: translateY(-8px); }

        /* Plan card header — orange */
        .plan-header {
            background: #3d348b;
            color: #fff;
            padding: 25px;
            text-align: center;
        }
        .plan-header h5 { color: #fff; margin: 0; font-size: 1.1rem; }
        .plan-header h3 { color: #fff; margin: 10px 0 4px; font-size: 2rem; font-weight: 700; }
        .plan-header small { color: rgba(255,255,255,0.88); }

        /* Modal heading keeps orange from admin.css */
        .modal-title { color: #f87407; }
    </style>
</head>
<body>

    <?php include '../includes/sidebar.php'; ?>

    <div class="main-content">

        <!-- Page heading — orange via admin.css .main-content h2 -->
        <h2 class="mb-4">Manage Subscription Plans</h2>

        <?php if (isset($_GET['msg'])): ?>
            <div class="alert alert-success">
                <?php echo $_GET['msg'] === 'deleted' ? 'Plan deleted successfully!' : 'Plan saved successfully!'; ?>
            </div>
        <?php endif; ?>

        <!-- Add New Plan card -->
        <div class="card mb-5" style="border-radius:12px; overflow:hidden; box-shadow:0 6px 20px rgba(0,0,0,0.08);">
            <div class="card-header" style="background:#3d348b; color:#fff;">
                <h5 class="mb-0" style="color:#fff;">Add New Plan</h5>
            </div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="id" value="0">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Plan Name</label>
                            <input type="text" name="name" class="form-control" required placeholder="e.g. 6-Month Access">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Price (₹)</label>
                            <input type="number" name="price" class="form-control" required min="1" step="0.01">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Duration (months)</label>
                            <input type="number" name="duration_months" class="form-control" required min="1" placeholder="e.g. 12 for 1 year">
                        </div>
                        <div class="col-12 text-end">
                            <button type="submit" class="btn btn-orange btn-lg">Add Plan</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Existing Plans -->
        <h4 class="mb-4">Current Plans</h4>

        <div class="row g-4">
            <?php while ($plan = $plans->fetch_assoc()): ?>
                <div class="col-lg-4 col-md-6">
                    <div class="plan-card">

                        <!-- Orange plan header -->
                        <div class="plan-header">
                            <h5><?php echo htmlspecialchars($plan['name']); ?></h5>
                            <h3>₹ <?php echo number_format($plan['price'], 2); ?></h3>
                            <small>
                                <?php echo $plan['duration_months']; ?> Month<?php echo $plan['duration_months'] > 1 ? 's' : ''; ?>
                            </small>
                        </div>

                        <div class="card-body text-center" style="background:#fff;">
                            <div class="mt-3 d-flex justify-content-center gap-2">
                                <button class="btn btn-orange btn-sm"
                                        data-bs-toggle="modal"
                                        data-bs-target="#editModal<?php echo $plan['id']; ?>">
                                    <i class="fas fa-edit me-1"></i> Edit
                                </button>
                                <a href="?delete=<?php echo $plan['id']; ?>"
                                   class="btn btn-danger btn-sm"
                                   onclick="return confirm('Delete this plan?');">
                                    <i class="fas fa-trash me-1"></i> Delete
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Edit Modal -->
                    <div class="modal fade" id="editModal<?php echo $plan['id']; ?>" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Edit Plan</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <form method="post">
                                    <div class="modal-body">
                                        <input type="hidden" name="id" value="<?php echo $plan['id']; ?>">
                                        <div class="mb-3">
                                            <label class="form-label">Plan Name</label>
                                            <input type="text" name="name" class="form-control"
                                                   value="<?php echo htmlspecialchars($plan['name']); ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Price (₹)</label>
                                            <input type="number" name="price" class="form-control"
                                                   value="<?php echo $plan['price']; ?>" required step="0.01">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Duration (months)</label>
                                            <input type="number" name="duration_months" class="form-control"
                                                   value="<?php echo $plan['duration_months']; ?>" required min="1">
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                        <button type="submit" class="btn btn-orange">Save Changes</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                </div>
            <?php endwhile; ?>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>