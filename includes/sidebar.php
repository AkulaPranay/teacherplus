<!-- admin/includes/sidebar.php -->
<div class="sidebar">
    <div class="brand">TEACHER PLUS</div>
    
    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
                <i class="fas fa-home me-2"></i> Dashboard
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'subscribers.php' ? 'active' : ''; ?>" href="subscribers.php">
                <i class="fas fa-users me-2"></i> Subscribers
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'subscription-plan.php' ? 'active' : ''; ?>" href="subscription-plan.php">
                <i class="fas fa-credit-card me-2"></i> Subscription Plans
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'advertisements.php' ? 'active' : ''; ?>" href="advertisements.php">
                <i class="fas fa-ad me-2"></i> Advertisements
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'e-magazines.php' ? 'active' : ''; ?>" href="add-e-magazine-admin.php">
                <i class="fas fa-book me-2"></i> E-Magazines
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'worksheets.php' ? 'active' : ''; ?>" href="add-worksheet-admin.php">
                <i class="fas fa-file-alt me-2"></i> Worksheets
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'articles.php' ? 'active' : ''; ?>" href="add-article-admin.php">
                <i class="fas fa-newspaper me-2"></i> Articles
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'users-activity.php' ? 'active' : ''; ?>" href="users-activity.php">
                <i class="fas fa-users-cog me-2"></i> Staff / Users
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="../logout.php">
                <i class="fas fa-sign-out-alt me-2"></i> Logout
            </a>
        </li>
    </ul>
</div>