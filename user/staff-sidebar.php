<!-- user/staff-sidebar.php -->
<div class="sidebar">
    <div class="brand">TEACHER PLUS</div>
    
    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
                <i class="fas fa-home me-2"></i> Dashboard
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'add-article.php' ? 'active' : ''; ?>" href="add-article.php">
                <i class="fas fa-newspaper me-2"></i> Add New Article
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'add-worksheet.php' ? 'active' : ''; ?>" href="add-worksheet.php">
                <i class="fas fa-file-alt me-2"></i> Add New Worksheet
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'add-e-magazine.php' ? 'active' : ''; ?>" href="add-e-magazine.php">
                <i class="fas fa-book-open me-2"></i> Add New E-Magazine
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'my-content.php' ? 'active' : ''; ?>" href="my-content.php">
                <i class="fas fa-list me-2"></i> View My Activity
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="../public/index.php">
                <i class="fas fa-globe me-2"></i> View Website
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="../logout.php">
                <i class="fas fa-sign-out-alt me-2"></i> Logout
            </a>
        </li>
    </ul>
</div>