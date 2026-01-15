<?php
/**
 * Community 3D Model Vault - Login/Register
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

// Redirect if already logged in
if (isLoggedIn()) {
    // Check if user is approved
    if (!isUserApproved($_SESSION['user_id']) && !($_SESSION['is_admin'] ?? false)) {
        // User is pending approval - show pending page
        $pendingApproval = true;
    } else {
        redirect('index.php');
    }
}

$showRegister = isset($_GET['register']);
$allowRegistration = setting('allow_registration', true);
$requireApproval = setting('require_admin_approval', false);
$pendingApproval = $pendingApproval ?? false;
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
                // Check if user is approved
                if (!isUserApproved($user['id']) && !($user['is_admin'] ?? false)) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['is_admin'] = false;
                    $pendingApproval = true;
                } else {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['is_admin'] = $user['is_admin'] ?? false;

                    $redirect = $_GET['redirect'] ?? 'index.php';
                    redirect($redirect);
                }
            } else {
                $error = 'Invalid username or password';
            }
        }
    } elseif ($action === 'register') {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $inviteCode = trim($_POST['invite_code'] ?? '');

        // Check if registration is allowed (either public or via invite)
        $hasValidInvite = false;
        $inviteError = '';

        if ($inviteCode) {
            $inviteResult = validateInviteCode($inviteCode);
            if ($inviteResult['valid']) {
                $hasValidInvite = true;
            } else {
                $inviteError = $inviteResult['error'];
            }
        }

        if (!$allowRegistration && !$hasValidInvite) {
            if ($inviteCode && $inviteError) {
                $error = $inviteError;
            } else {
                $error = 'Registration requires an invite code';
            }
        } elseif ($inviteCode && !$hasValidInvite) {
            $error = $inviteError;
        } else {
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
                // Determine if approval is needed (invite users bypass approval)
                $needsApproval = $requireApproval && !$hasValidInvite;

                $userId = createUser([
                    'username' => $username,
                    'email' => $email,
                    'password' => $password,
                    'needs_approval' => $needsApproval
                ]);

                if ($userId) {
                    // Mark invite as used
                    if ($hasValidInvite) {
                        useInviteCode($inviteCode);
                    }

                    $_SESSION['user_id'] = $userId;
                    $_SESSION['is_admin'] = false;

                    if ($needsApproval) {
                        $pendingApproval = true;
                    } else {
                        redirect('index.php');
                    }
                } else {
                    $error = 'Failed to create account. Please try again.';
                }
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
            <?php if ($pendingApproval): ?>
                <!-- Pending Approval Screen -->
                <div class="auth-card" style="text-align: center;">
                    <div style="margin-bottom: 24px;">
                        <i class="fas fa-user-clock" style="font-size: 4rem; color: var(--neon-yellow);"></i>
                    </div>
                    <h1 class="text-gradient">Pending Approval</h1>
                    <p style="color: var(--text-secondary); margin: 16px 0 24px;">
                        Your account has been created and is waiting for administrator approval.<br>
                        You'll be able to access the site once your account is approved.
                    </p>
                    <a href="logout.php" class="btn btn-secondary">
                        <i class="fas fa-sign-out-alt"></i> Sign Out
                    </a>
                </div>
            <?php else: ?>
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
                    <?php if ($allowRegistration): ?>
                    <div class="auth-tab <?= $showRegister ? 'active' : '' ?>" onclick="window.location='login.php?register=1'">
                        Register
                    </div>
                    <?php else: ?>
                    <div class="auth-tab <?= $showRegister ? 'active' : '' ?>" onclick="window.location='login.php?register=1'">
                        Invite
                    </div>
                    <?php endif; ?>
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
                    <!-- Register Form (with optional invite code) -->
                    <form method="POST" action="login.php?register=1">
                        <input type="hidden" name="form_action" value="register">

                        <?php if (!$allowRegistration): ?>
                            <div class="form-group">
                                <label class="form-label required">Invite Code</label>
                                <input type="text" name="invite_code" class="form-input"
                                       placeholder="Enter your invite code"
                                       value="<?= sanitize($_POST['invite_code'] ?? $_GET['code'] ?? '') ?>"
                                       style="text-transform: uppercase; letter-spacing: 2px; font-family: monospace;"
                                       required>
                                <div class="form-hint">Registration requires an invite code from an administrator</div>
                            </div>
                            <hr style="border: none; border-top: 1px solid var(--border-color); margin: 20px 0;">
                        <?php else: ?>
                            <div class="form-group">
                                <label class="form-label">Invite Code <span style="color: var(--text-muted);">(optional)</span></label>
                                <input type="text" name="invite_code" class="form-input"
                                       placeholder="Have an invite code?"
                                       value="<?= sanitize($_POST['invite_code'] ?? $_GET['code'] ?? '') ?>"
                                       style="text-transform: uppercase; letter-spacing: 2px; font-family: monospace;">
                                <div class="form-hint">Enter invite code if you have one (skip admin approval)</div>
                            </div>
                        <?php endif; ?>
                        
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
            </div>
            <?php endif; ?>
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
                    &copy; <?= date('Y') ?> <?= SITE_NAME ?>. A community-driven platform.
                </div>
            </div>
        </div>
    </footer>

    <script src="js/app.js"></script>
</body>
</html>
