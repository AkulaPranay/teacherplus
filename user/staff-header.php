<?php
// user/staff-header.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Area - TeacherPlus</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../admin/assets/css/style.css">

    <style>
        body { 
            background: #f5f7fa; 
            margin: 0; 
        }

        /* === FORCED SIDEBAR STYLING (never breaks) === */
        .sidebar {
            position: fixed !important;
            top: 0;
            left: 0;
            bottom: 0;
            width: 260px !important;
            background: #ffffff !important;
            border-right: 1px solid #e0e0e0 !important;
            padding: 25px 20px !important;
            overflow-y: auto;
            box-shadow: 2px 0 15px rgba(0,0,0,0.05) !important;
            z-index: 1000;
        }

        .sidebar .brand {
            font-size: 1.8rem;
            font-weight: 700;
            color: #3d348b;
            text-align: center;
            margin-bottom: 40px;
        }

        .sidebar .nav-link {
            color: #495057;
            padding: 12px 20px;
            border-radius: 10px;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            font-weight: 500;
        }

        .sidebar .nav-link:hover {
            background: #e7f1ff;
            color: #3d348b;
        }

        .sidebar .nav-link.active {
            background: #3d348b;
            color: #fff;
        }

        .sidebar .nav-link i {
            width: 24px;
            margin-right: 12px;
            text-align: center;
        }

        /* Main content - always pushed correctly */
        .main-content {
            margin-left: 260px !important;
            padding: 40px 30px;
            min-height: 100vh;
        }
    </style>
</head>
<body>

    <?php include 'staff-sidebar.php'; ?>