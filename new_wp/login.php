<?php
session_start();
require_once __DIR__ . '/inc/app.php';

$nmimsLogo = 'assets/images/nmimslogo.png';
if (!file_exists(__DIR__ . '/assets/images/nmimslogo.png')) {
    $nmimsLogo = 'assets/images/nmimsvertical.jpg';
    if (!file_exists(__DIR__ . '/assets/images/nmimsvertical.jpg')) {
        $nmimsLogo = app_nmims_logo_path();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];

    $hasSubRole = app_column_exists('users', 'sub_role');
    $hasStatus = app_column_exists('users', 'status');

    if ($hasSubRole && $hasStatus) {
        $stmt = $conn->prepare("SELECT id, full_name, password, role, sub_role, status FROM users WHERE email = ?");
    } elseif ($hasSubRole) {
        $stmt = $conn->prepare("SELECT id, full_name, password, role, sub_role, 'active' AS status FROM users WHERE email = ?");
    } elseif ($hasStatus) {
        $stmt = $conn->prepare("SELECT id, full_name, password, role, NULL AS sub_role, status FROM users WHERE email = ?");
    } else {
        $stmt = $conn->prepare("SELECT id, full_name, password, role, NULL AS sub_role, 'active' AS status FROM users WHERE email = ?");
    }
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 1) {
        $stmt->bind_result($id, $full_name, $hashed_password, $role, $sub_role, $status);
        $stmt->fetch();

        if (!$hasSubRole) {
            $legacySubRoleMap = [
                'vidya@gmail.com' => 'faculty-mentor',
                'naresh@gmail.com' => 'faculty-mentor',
                'somu@gmail.com' => 'faculty-mentor',
                'vinayak@gmail.com' => 'faculty-mentor',
                'wani@gmail.com' => 'program-chair'
            ];
            $sub_role = $legacySubRoleMap[$email] ?? null;
        }

        if ($status !== 'active') {
            $_SESSION['error'] = "Your account is inactive.";
        } elseif (password_verify($password, $hashed_password)) {
            // Store user info in session
            $_SESSION['user_id'] = $id;
            $_SESSION['full_name'] = $full_name;
            $_SESSION['role'] = $role;
            $_SESSION['sub_role'] = $sub_role;
            $_SESSION['last_activity'] = time();
            $_SESSION['user'] = [
                'id' => $id,
                'full_name' => $full_name,
                'role' => $role,
                'sub_role' => $sub_role,
                'status' => $status
            ];

            // Redirect based on role and sub_role
            header("Location: " . app_role_dashboard(app_normalize_role($role, $sub_role)));
            exit();
        } else {
            $_SESSION['error'] = "Invalid email or password.";
        }
    } else {
        $_SESSION['error'] = "Invalid email or password.";
    }
    $stmt->close();
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NMIMS EventHub - Login</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Source+Sans+3:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/app.css">
</head>
<body class="login-page">
    <div class="login-shell">
        <div class="auth-card">
            <section class="auth-art">
                <span class="auth-pill">NMIMS Event Portal</span>
                <h1 class="auth-hero-title">Welcome to NMIMS University</h1>
                <p class="auth-hero-copy">A single place to manage white paper submissions, approvals, and event publishing with clarity.</p>

                <div class="left-panels">
                    <div class="auth-quick-list" role="list" aria-label="Event management highlights">
                        <p class="auth-quick-item" role="listitem">Submit white papers with event details, budget, and service requirements.</p>
                        <p class="auth-quick-item" role="listitem">Track multi-level approvals with query, reject, and resubmission support.</p>
                        <p class="auth-quick-item" role="listitem">Publish approved events, manage registrations, and post-event reports.</p>
                    </div>

                    <div class="auth-art-actions">
                        <a href="mailto:adminoffice@college.com" class="auth-art-btn">Contact</a>
                        <a href="index.php" class="auth-art-btn">Portal Home</a>
                    </div>

                    <p class="auth-art-support">For support, contact your department coordinator or the portal administrator.</p>
                </div>
            </section>

            <section class="auth-form">
                <div class="auth-logo-wrap">
                    <img src="<?php echo htmlspecialchars($nmimsLogo); ?>" alt="NMIMS Logo">
                </div>

                <h2>Sign In to Continue</h2>
                <p class="auth-form-copy">Use your assigned role email and password to access your dashboard.</p>

                <?php if (isset($_SESSION['error'])) { echo "<div class='flash error'>" . htmlspecialchars($_SESSION['error']) . "</div>"; unset($_SESSION['error']); } ?>
                <?php if (isset($_SESSION['success'])) { echo "<div class='flash success'>" . htmlspecialchars($_SESSION['success']) . "</div>"; unset($_SESSION['success']); } ?>

                <form method="POST" class="proposal-form">
                    <div class="field">
                        <label>Email (Role ID)</label>
                        <input type="email" name="email" required>
                    </div>
                    <div class="field">
                        <label>Password</label>
                        <input type="password" name="password" required>
                    </div>
                    <button type="submit" class="btn">Login</button>
                </form>

                <p class="auth-support">Secure access for students, club heads, approvers, and administrators.</p>
                <a class="auth-forgot" href="#" onclick="return false;">Forgot Password?</a>
            </section>
        </div>
    </div>
    <footer class="login-footer">&copy; <?php echo date('Y'); ?> Kuchuru Sai Krishna Reddy - STME. All rights reserved.</footer>
</body>
</html>
<?php $conn->close(); ?>