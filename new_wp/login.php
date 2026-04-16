<?php
session_start();
require_once __DIR__ . '/inc/app.php';

$hasStatusColumn = app_column_exists('users', 'status');
$statusSql = $hasStatusColumn ? ' WHERE status = "active"' : '';

$credentialUsers = [];
$credStmt = $conn->prepare('SELECT id, full_name, email, role FROM users' . $statusSql . ' ORDER BY id ASC');
if ($credStmt) {
    $credStmt->execute();
    $credentialUsers = $credStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $credStmt->close();
}

$usersByRole = [];
$usersByEmail = [];
foreach ($credentialUsers as $credentialUser) {
    $emailKey = strtolower(trim((string) ($credentialUser['email'] ?? '')));
    $roleKey = trim((string) ($credentialUser['role'] ?? ''));

    if ($emailKey !== '') {
        $usersByEmail[$emailKey] = $credentialUser;
    }

    if ($roleKey !== '' && !isset($usersByRole[$roleKey])) {
        $usersByRole[$roleKey] = $credentialUser;
    }
}

$credentialConfig = [
    ['label' => 'Super Admin', 'roles' => ['super_admin'], 'preferred_email' => 'superadmin@college.com'],
    ['label' => 'Club Head (ELGE)', 'roles' => ['club_head'], 'preferred_email' => 'elgehead@college.com'],
    ['label' => 'Faculty Mentor (ELGE)', 'roles' => ['faculty_mentor'], 'preferred_email' => 'elgementor@college.com'],
    ['label' => 'School Head (STME)', 'roles' => ['school_head'], 'preferred_email' => 'stmehead@college.com'],
    ['label' => 'President VC', 'roles' => ['president_vc'], 'preferred_email' => 'president@college.com'],
    ['label' => 'GS Treasurer', 'roles' => ['gs_treasurer'], 'preferred_email' => 'treasurer@college.com'],
    ['label' => 'Admin Office', 'roles' => ['admin_office', 'administration_officer', 'administrative_officer'], 'preferred_email' => 'adminoffice@college.com'],
    ['label' => 'IT Team', 'roles' => ['it_team'], 'preferred_email' => 'it@college.com'],
    ['label' => 'Housekeeping', 'roles' => ['housekeeping'], 'preferred_email' => 'housekeeping@college.com'],
    ['label' => 'Security Officer', 'roles' => ['security_officer'], 'preferred_email' => 'security@college.com'],
    ['label' => 'Purchase Officer', 'roles' => ['purchase_officer'], 'preferred_email' => 'purchase@college.com'],
    ['label' => 'Accounts Officer', 'roles' => ['accounts_officer'], 'preferred_email' => 'accounts@college.com'],
    ['label' => 'Sports Department', 'roles' => ['sports_dept', 'sports_department'], 'preferred_email' => 'sports@college.com'],
    ['label' => 'Food Admin', 'roles' => ['food_admin'], 'preferred_email' => 'foodadmin@college.com'],
    ['label' => 'Deputy Registrar', 'roles' => ['deputy_registrar'], 'preferred_email' => 'dyregistrar@college.com'],
    ['label' => 'Director', 'roles' => ['director'], 'preferred_email' => 'director@college.com'],
    ['label' => 'Student', 'roles' => ['student'], 'preferred_email' => 'student@college.com'],
    ['label' => 'Rector', 'roles' => ['rector'], 'preferred_email' => null],
    ['label' => 'Deputy Director', 'roles' => ['deputy_director', 'dy_director'], 'preferred_email' => null],
];

$loginCredentials = [];
foreach ($credentialConfig as $config) {
    $selectedUser = null;
    $preferredEmail = strtolower(trim((string) ($config['preferred_email'] ?? '')));
    $allowedRoles = $config['roles'];

    if ($preferredEmail !== '' && isset($usersByEmail[$preferredEmail])) {
        $candidate = $usersByEmail[$preferredEmail];
        if (in_array((string) ($candidate['role'] ?? ''), $allowedRoles, true)) {
            $selectedUser = $candidate;
        }
    }

    if ($selectedUser === null) {
        foreach ($allowedRoles as $allowedRole) {
            if (isset($usersByRole[$allowedRole])) {
                $selectedUser = $usersByRole[$allowedRole];
                break;
            }
        }
    }

    $loginCredentials[] = [
        'label' => (string) $config['label'],
        'email' => $selectedUser['email'] ?? 'Not assigned',
        'name' => $selectedUser['full_name'] ?? 'No active user found',
    ];
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
<body>
    <div class="login-shell">
        <div class="auth-card">
            <section class="auth-art">
                <h3 class="cred-title"><i class="fa-solid fa-key"></i> Demo Login Credentials</h3>
                <div class="cred-grid">
                    <?php foreach ($loginCredentials as $credential) { ?>
                        <div class="cred-card">
                            <p class="cred-role"><?php echo htmlspecialchars($credential['label']); ?></p>
                            <p class="cred-email"><?php echo htmlspecialchars((string) $credential['email']); ?></p>
                            <p class="cred-name"><?php echo htmlspecialchars((string) $credential['name']); ?></p>
                        </div>
                    <?php } ?>
                </div>
                <div class="cred-password-note">
                    <strong>Password for all roles:</strong> <span>123456</span>
                </div>
            </section>

            <section class="auth-form">
                <div class="brand" style="margin-bottom:18px;">
                    <div class="brand-mark">NM</div>
                    <div>
                        <h2>NMIMS Event Workflow</h2>
                        <p>Sign in with your assigned role</p>
                    </div>
                </div>
                <h2>Login</h2>
                <?php if (isset($_SESSION['error'])) { echo "<div class='flash error'>" . htmlspecialchars($_SESSION['error']) . "</div>"; unset($_SESSION['error']); } ?>
                <?php if (isset($_SESSION['success'])) { echo "<div class='flash success'>" . htmlspecialchars($_SESSION['success']) . "</div>"; unset($_SESSION['success']); } ?>
                <form method="POST" class="proposal-form">
                    <div class="field">
                        <label>Email</label>
                        <input type="email" name="email" required>
                    </div>
                    <div class="field">
                        <label>Password</label>
                        <input type="password" name="password" required>
                    </div>
                    <button type="submit" class="btn">Login</button>
                </form>
                <div class="auth-links">
                    Don't have an account? <a href="signup.php">Sign up</a>
                </div>
            </section>
        </div>
    </div>
</body>
</html>
<?php $conn->close(); ?>