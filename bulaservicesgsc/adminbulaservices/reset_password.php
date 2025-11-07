<?php
declare(strict_types=1);

require_once __DIR__ . '/server/config.php';

$token = $_GET['token'] ?? '';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($token) || empty($password) || empty($confirm_password)) {
        $error = 'All fields are required.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } else {
        try {
            $db = getDBConnection();
            $tokenHash = hash('sha256', $token);
            $now = date('Y-m-d H:i:s');
            
            // Find valid token
            $stmt = $db->prepare("SELECT id FROM admins WHERE reset_token_hash = ? AND reset_token_expires > ? AND reset_token_used = 0");
            $stmt->execute([$tokenHash, $now]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$admin) {
                $error = 'Invalid or expired reset token.';
            } else {
                // Update password and invalidate token
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $updateStmt = $db->prepare("UPDATE admins SET password = ?, reset_token_used = 1, reset_token_hash = NULL, reset_token_expires = NULL WHERE id = ?");
                $updateStmt->execute([$passwordHash, $admin['id']]);
                
                $success = 'Password reset successfully! You can now login with your new password.';
            }
        } catch (Throwable $e) {
            error_log("Admin password reset error: " . $e->getMessage());
            $error = 'An error occurred. Please try again.';
        }
    }
} elseif (!empty($token)) {
    // Validate token on page load
    try {
        $db = getDBConnection();
        $tokenHash = hash('sha256', $token);
        $now = date('Y-m-d H:i:s');
        
        $stmt = $db->prepare("SELECT id FROM admins WHERE reset_token_hash = ? AND reset_token_expires > ? AND reset_token_used = 0");
        $stmt->execute([$tokenHash, $now]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$admin) {
            $error = 'Invalid or expired reset token. Please request a new reset link.';
        }
    } catch (Throwable $e) {
        error_log("Admin token validation error: " . $e->getMessage());
        $error = 'An error occurred while validating your token.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Admin Password - Barangay Bula</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="./css/login.css">
    <style>
        .reset-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
        }
        .reset-card {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
        }
        .alert {
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 1rem;
        }
        .alert-error {
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="reset-card">
            <div class="login-logo" style="text-align: center; margin-bottom: 1.5rem;">
                <img src="./images/bula_logo.png" alt="Barangay Bula Logo" class="logo-image" style="width: 60px; height: auto;">
                <h1 style="font-size: 1.5rem; margin: 0.5rem 0 0 0; color: #333;">Admin Password Reset</h1>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
                </div>
                <div style="text-align: center; margin-top: 1rem;">
                    <a href="index.php" class="btn-primary" style="display: inline-block; padding: 10px 20px; background: #2563eb; color: white; text-decoration: none; border-radius: 5px;">Go to Login</a>
                </div>
            <?php elseif (empty($error) || !empty($token)): ?>
                <form method="POST">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                    
                    <div class="form-group">
                        <label for="password">New Password</label>
                        <input type="password" id="password" name="password" class="form-control" 
                               placeholder="Enter new password" required minlength="8">
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" 
                               class="form-control" placeholder="Confirm new password" required>
                    </div>
                    
                    <button type="submit" class="btn-primary" style="width: 100%;">
                        <i class="fas fa-key"></i> Reset Password
                    </button>
                </form>
                
                <div style="text-align: center; margin-top: 1rem;">
                    <a href="index.php">Back to Login</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>