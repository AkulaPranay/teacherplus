<?php
// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$is_logged_in = isset($_SESSION['user_id']);
$display_name = $is_logged_in ? ($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User') : '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) : 'Teacher Plus'; ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        .top-header {
            background: #1f2a4a;
            padding: 20px 60px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .logo img {
    height: 140px;
    object-fit: contain;
}


        .btn-subscribe {
            background: #f87407;
            color: #fff;
            padding: 10px 18px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-subscribe:hover {
            background: #e06200;
        }

        .main-navbar {
            background: #f5f5f5;
            padding: 12px 60px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 15px;
        }

        .nav-links a {
            margin-right: 25px;
            text-decoration: none;
            color: #1f2a4a;
            font-weight: 500;
            font-size: 15px;
            transition: color 0.2s;
        }

        .nav-links a:hover {
            color: #f87407;
        }

        /* Sections Dropdown */
        .sections-dropdown {
            position: relative;
            display: inline-block;
            margin-right: 25px;
        }

        .sections-dropdown > a {
            color: #1f2a4a;
            font-weight: 500;
            font-size: 15px;
            text-decoration: none;
            cursor: pointer;
        }

        .sections-menu {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            background: #1f2a4a;
            min-width: 220px;
            box-shadow: 0 6px 20px rgba(0,0,0,0.25);
            border-radius: 4px;
            z-index: 9999;
            padding: 8px 0;
            max-height: 420px;
            overflow-y: auto;
        }

        .sections-dropdown:hover .sections-menu {
            display: block;
        }

        .sections-menu a {
            display: block;
            padding: 10px 20px;
            color: #fff;
            text-decoration: none;
            font-size: 14px;
        }

        .sections-menu a:hover {
            background: #f87407;
            color: #fff;
        }

        .social-icons a {
            margin-left: 10px;
            color: #fff;
            background: #1f2a4a;
            padding: 6px 10px;
            border-radius: 4px;
            font-size: 14px;
            transition: all 0.2s;
        }

        .social-icons a:hover {
            background: #f87407;
        }

        .search-box form {
            display: flex;
        }

        .search-box input {
            padding: 8px 12px;
            border: 1px solid #ccc;
            border-radius: 4px 0 0 4px;
            width: 220px;
            font-size: 14px;
        }

        .search-box button {
            background: #f87407;
            color: white;
            border: none;
            padding: 8px 14px;
            border-radius: 0 4px 4px 0;
            cursor: pointer;
        }


        @media (max-width: 992px) {
            .top-header, .main-navbar {
                padding: 15px 20px;
            }
        }
    </style>
</head>
<body>

<header>
    <!-- Top Header -->
    <div class="top-header">
        <div class="logo"><img src="Teacher-Plus-logo-black-2048x1590.png" alt=""></div>

        <div class="header-buttons" style="display:flex; align-items:center; gap:10px;">
            <?php if ($is_logged_in): ?>
                <a href="subscribe-new.php" class="btn-subscribe">
                    <i class="fa fa-plus-circle"></i> Subscribe
                </a>
                <div class="dropdown">
                    <a href="#" class="dropdown-toggle" data-bs-toggle="dropdown" style="color:#fff; background:#f87407; padding:10px 18px; border-radius:4px; text-decoration:none;">
                        Hello, <?php echo htmlspecialchars($display_name); ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="profile.php">View Profile</a></li>
                        <li><a class="dropdown-item" href="edit-profile.php">Edit Profile</a></li>
                        <li><a class="dropdown-item" href="invoices.php">Invoice</a></li>
                        <li><a class="dropdown-item" href="renewal.php">Renewal</a></li>
                        <li><a class="dropdown-item" href="upgrade.php">Upgrade / Downgrade</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="logout.php">Logout</a></li>
                    </ul>
                </div>
            <?php else: ?>
                <a href="login.php" class="btn btn-outline-light me-2">Login</a>
                <a href="subscribe-new.php" class="btn-subscribe">
                    <i class="fa fa-plus-circle"></i> Subscribe
                </a>
            <?php endif; ?>
        </div>
    </div>


    <!-- Main Navigation -->
    <div class="main-navbar">
        <div class="nav-links">
            <a href="index.php">Home</a>
            <a href="e-magazines.php">E-magazine</a>

            <!-- Sections Dropdown -->
            <div class="sections-dropdown">
                <a href="#" style="font-weight:500; font-size:15px; color:#1f2a4a; text-decoration:none;">
                    Sections <span style="font-size:10px;"></span>
                </a>
                <div class="sections-menu">
                    <?php
                    $cat_query = $conn->query("
                        SELECT DISTINCT category 
                        FROM articles 
                        WHERE category IS NOT NULL AND category != '' 
                        ORDER BY category ASC
                    ");
                    while ($cat = $cat_query->fetch_assoc()):
                    ?>
                        <!-- FIXED LINE: Correct link to sections.php -->
                        <a href="sections.php?category=<?php echo urlencode($cat['category']); ?>">
                            <?php echo htmlspecialchars($cat['category']); ?>
                        </a>
                    <?php endwhile; ?>
                </div>
            </div>

            <a href="archives.php">Archives</a>
            <a href="contact.php">Contact us</a>
            <a href="readers-blog.php">Readers' Blog</a>
            <a href="worksheets.php">Worksheets</a>
        </div>

        <div style="display:flex; align-items:center; gap:15px;">
            <div class="social-icons">
                <a href="https://www.facebook.com/teacherplusmagazine/"><i class="fab fa-facebook-f"></i></a>
                <a href="https://x.com/TPlus_tweets?s=03"><i class="fab fa-x-twitter"></i></a>
                <a href="https://www.instagram.com/teacher_plus/"><i class="fab fa-instagram"></i></a>
            </div>

            <!-- Search Box -->
            <div class="search-box">
                <form action="search.php" method="GET">
                    <input type="text" name="q" placeholder="Search articles..." 
                           value="<?php echo isset($_GET['q']) ? htmlspecialchars($_GET['q']) : ''; ?>">
                    <button type="submit"><i class="fas fa-search"></i></button>
                </form>
            </div>
        </div>
    </div>
</header>
