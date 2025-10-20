<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Error | Barangay Bula</title>
    <style>
        body { font-family: 'Poppins', sans-serif; background: #f8f9fa; }
        .error-container { max-width: 600px; margin: 100px auto; text-align: center; }
        .error-icon { font-size: 4rem; color: #dc3545; }
        .error-title { color: #dc3545; margin: 1rem 0; }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon"><i class="fas fa-database"></i></div>
        <h1 class="error-title">Database Connection Error</h1>
        <p>We're experiencing technical difficulties. Please try again later.</p>
        <p>If the problem persists, please contact the barangay administration.</p>
        <a href="<?php echo USER_BASE_URL; ?>home.php">Return to Home Page</a>
    </div>
</body>
</html>