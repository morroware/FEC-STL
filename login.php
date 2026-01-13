<?php
/**
 * FEC STL Vault - Login/Register
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect('index.php');
}

$showRegister = isset($_GET['register']);
$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['form_action'] ?? '';
    
    if ($action === 'login') {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        
        if (!$username || !$password) {
            $error = 'Please fill in all fields';
        } else {
            $user = authenticateUser($username, $password);
            if ($user) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['is_admin'] = $user['is_admin'] ?? false;
                
                $redirect = $_GET['redirect'] ?? 'index.php';
                redirect($redirect);
            } else {
                $error = 'Invalid username or password';
            }
        }
    } elseif ($action === 'register') {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        // Validation
        if (!$username || !$email || !$password) {
            $error = 'Please fill in all fields';
        } elseif (strlen($username) < 3 || strlen($username) > 20) {
            $error = 'Username must be 3-20 characters';
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            $error = 'Username can only contain letters, numbers, and underscores';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email address';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters';
        } elseif ($password !== $confirmPassword) {
            $error = 'Passwords do not match';
        } elseif (getUserByUsername($username)) {
            $error = 'Username already taken';
        } elseif (getUserByEmail($email)) {
            $error = 'Email already registered';
        } else {
            $userId = createUser([
                'username' => $username,
                'email' => $email,
                'password' => $password
            ]);
            
            if ($userId) {
                $_SESSION['user_id'] = $userId;
                $_SESSION['is_admin'] = false;
                redirect('index.php');
            } else {
                $error = 'Failed to create account. Please try again.';
            }
        }
        
        $showRegister = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $showRegister ? 'Create Account' : 'Sign In' ?> - <?= SITE_NAME ?></title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;500;600;700;800&family=Exo+2:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="container">
            <a href="index.php" class="logo">
                <div class="logo-icon"><i class="fas fa-cube"></i></div>
                <span><?= SITE_NAME ?></span>
            </a>
            
            <div class="nav-links">
                <a href="index.php" class="nav-link">Home</a>
                <a href="browse.php" class="nav-link">Browse</a>
                <a href="browse.php?sort=popular" class="nav-link">Popular</a>
            </div>
            
            <div class="nav-actions">
                <a href="login.php" class="btn btn-secondary btn-sm <?= !$showRegister ? 'active' : '' ?>">Sign In</a>
                <a href="login.php?register=1" class="btn btn-primary btn-sm">Join Now</a>
            </div>
        </div>
    </nav>

    <div class="page-content">
        <div class="auth-container">
            <div class="auth-card">
                <div class="auth-header">
                    <h1 class="text-gradient"><?= $showRegister ? 'Join the Community' : 'Welcome Back' ?></h1>
                    <p><?= $showRegister ? 'Create your account to start sharing' : 'Sign in to your account' ?></p>
                </div>

                <!-- Tabs -->
                <div class="auth-tabs">
                    <div class="auth-tab <?= !$showRegister ? 'active' : '' ?>" onclick="window.location='login.php'">
                        Sign In
                    </div>
                    <div class="auth-tab <?= $showRegister ? 'active' : '' ?>" onclick="window.location='login.php?register=1'">
                        Register
                    </div>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?= sanitize($error) ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?= sanitize($success) ?>
                    </div>
                <?php endif; ?>

                <?php if ($showRegister): ?>
                    <!-- Register Form -->
                    <form method="POST" action="login.php?register=1">
                        <input type="hidden" name="form_action" value="register">
                        
                        <div class="form-group">
                            <label class="form-label required">Username</label>
                            <input type="text" name="username" class="form-input" 
                                   placeholder="Choose a username" 
                                   value="<?= sanitize($_POST['username'] ?? '') ?>"
                                   pattern="[a-zA-Z0-9_]+" 
                                   minlength="3" maxlength="20" required>
                            <div class="form-hint">3-20 characters, letters, numbers, underscores only</div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label required">Email</label>
                            <input type="email" name="email" class="form-input" 
                                   placeholder="your@email.com"
                                   value="<?= sanitize($_POST['email'] ?? '') ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label required">Password</label>
                            <input type="password" name="password" class="form-input" 
                                   placeholder="Create a password" minlength="6" required>
                            <div class="form-hint">At least 6 characters</div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label required">Confirm Password</label>
                            <input type="password" name="confirm_password" class="form-input" 
                                   placeholder="Confirm your password" required>
                        </div>
                        
                        <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 8px;">
                            <i class="fas fa-user-plus"></i> Create Account
                        </button>
                    </form>
                    
                    <div class="auth-footer">
                        Already have an account? <a href="login.php">Sign in</a>
                    </div>
                <?php else: ?>
                    <!-- Login Form -->
                    <form method="POST" action="login.php<?= isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : '' ?>">
                        <input type="hidden" name="form_action" value="login">
                        
                        <div class="form-group">
                            <label class="form-label required">Username or Email</label>
                            <input type="text" name="username" class="form-input" 
                                   placeholder="Enter your username or email"
                                   value="<?= sanitize($_POST['username'] ?? '') ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label required">Password</label>
                            <input type="password" name="password" class="form-input" 
                                   placeholder="Enter your password" required>
                        </div>
                        
                        <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 8px;">
                            <i class="fas fa-sign-in-alt"></i> Sign In
                        </button>
                    </form>
                    
                    <div class="auth-footer">
                        Don't have an account? <a href="login.php?register=1">Create one</a>
                    </div>
                <?php endif; ?>

                <!-- Demo Credentials -->
                <div style="margin-top: 24px; padding: 16px; background: var(--bg-elevated); border-radius: var(--radius-md); text-align: center;">
                    <div style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 8px;">
                        <i class="fas fa-info-circle"></i> Demo Admin Login
                    </div>
                    <code style="color: var(--neon-cyan);">admin</code> / <code style="color: var(--neon-cyan);">admin123</code>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-links">
                    <a href="index.php">Home</a>
                    <a href="browse.php">Browse</a>
                </div>
                <div class="footer-copyright">
                    &copy; <?= date('Y') ?> <?= SITE_NAME ?>. Made for the FEC community.
                </div>
            </div>
        </div>
    </footer>

    <script src="js/app.js"></script>
</body>
</html>
