<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config.php';

if (!defined('APP_TIMEZONE')) {
    date_default_timezone_set('Asia/Kolkata');
    define('APP_TIMEZONE', 'Asia/Kolkata');
}

const APP_ROLES = [
    'super_admin',
    'club_head',
    'faculty_mentor',
    'president_vc',
    'gs_treasurer',
    'school_head',
    'admin_office',
    'administration_officer',
    'administrative_officer',
    'rector',
    'deputy_registrar',
    'dy_director',
    'deputy_director',
    'director',
    'it_team',
    'housekeeping',
    'security_officer',
    'purchase_officer',
    'accounts_officer',
    'sports_dept',
    'food_admin',
    'student'
];

function app_role_labels(): array
{
    return [
        'super_admin' => 'Super Admin',
        'club_head' => 'Club Head',
        'faculty_mentor' => 'Faculty Mentor',
        'president_vc' => 'President / VC',
        'gs_treasurer' => 'GS / Treasurer',
        'school_head' => 'School Head',
        'admin_office' => 'Admin Office',
        'administration_officer' => 'Administration Officer',
        'administrative_officer' => 'Administration Officer',
        'rector' => 'Rector',
        'deputy_registrar' => 'Deputy Registrar',
        'dy_director' => 'Deputy Director',
        'deputy_director' => 'Deputy Director',
        'director' => 'Director',
        'it_team' => 'IT Team',
        'housekeeping' => 'Housekeeping',
        'security_officer' => 'Security Officer',
        'purchase_officer' => 'Purchase Officer',
        'accounts_officer' => 'Accounts Officer',
        'sports_dept' => 'Sports Department',
        'sports_department' => 'Sports Department',
        'food_admin' => 'Food / Admin Incharge',
        'student' => 'Student'
    ];
}

function app_role_label(string $role): string
{
    $labels = app_role_labels();
    return $labels[$role] ?? $role;
}

function app_normalize_role(string $role, ?string $subRole = null): string
{
    if ($role === 'head') {
        return 'club_head';
    }

    if ($role === 'admin') {
        if ($subRole === 'faculty-mentor') {
            return 'faculty_mentor';
        }

        if ($subRole === 'program-chair') {
            return 'school_head';
        }
    }

    if (in_array($role, ['administration_officer', 'administrative_officer'], true)) {
        return 'admin_office';
    }

    if ($role === 'sports_department') {
        return 'sports_dept';
    }

    if ($role === 'dy_director') {
        return 'deputy_director';
    }

    return $role;
}

function app_redirect(string $path): void
{
    header('Location: ' . $path);
    exit();
}

function app_flash_set(string $type, string $message): void
{
    if (!isset($_SESSION['flash'])) {
        $_SESSION['flash'] = [];
    }
    if (!isset($_SESSION['flash'][$type])) {
        $_SESSION['flash'][$type] = [];
    }
    $_SESSION['flash'][$type][] = $message;
}

function app_flash_take_all(): array
{
    $flash = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flash;
}

function app_session_timeout(int $seconds = 3600): void
{
    if (isset($_SESSION['last_activity']) && (time() - (int)$_SESSION['last_activity']) > $seconds) {
        $_SESSION = [];
        session_destroy();
        session_start();
        app_flash_set('error', 'Session expired. Please log in again.');
        app_redirect('login.php');
    }
    $_SESSION['last_activity'] = time();
}

function app_current_user(): ?array
{
    global $conn;

    $sessionUserId = (int) ($_SESSION['user']['id'] ?? $_SESSION['user_id'] ?? 0);
    if ($sessionUserId <= 0) {
        return null;
    }

    $userId = $sessionUserId;
    $hasSubRole = app_column_exists('users', 'sub_role');
    $hasStatus = app_column_exists('users', 'status');
    if ($hasSubRole && $hasStatus) {
        $stmt = $conn->prepare('SELECT id, full_name, email, role, sub_role, school_id, club_id, status FROM users WHERE id = ? LIMIT 1');
    } elseif ($hasSubRole) {
        $stmt = $conn->prepare('SELECT id, full_name, email, role, sub_role, school_id, club_id, "active" AS status FROM users WHERE id = ? LIMIT 1');
    } elseif ($hasStatus) {
        $stmt = $conn->prepare('SELECT id, full_name, email, role, NULL AS sub_role, school_id, club_id, status FROM users WHERE id = ? LIMIT 1');
    } else {
        $stmt = $conn->prepare('SELECT id, full_name, email, role, NULL AS sub_role, school_id, club_id, "active" AS status FROM users WHERE id = ? LIMIT 1');
    }
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user) {
        return null;
    }

    if ((int) ($user['school_id'] ?? 0) <= 0 && app_table_exists('school_role_assignments')) {
        $stmt = $conn->prepare('SELECT school_id FROM school_role_assignments WHERE user_id = ? ORDER BY id ASC LIMIT 1');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $schoolRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!empty($schoolRow['school_id'])) {
            $user['school_id'] = (int) $schoolRow['school_id'];
        }
    }

    if ((int) ($user['club_id'] ?? 0) <= 0 && app_table_exists('clubs')) {
        if (($user['role'] ?? '') === 'club_head' && app_column_exists('clubs', 'club_head_user_id')) {
            $stmt = $conn->prepare('SELECT id FROM clubs WHERE club_head_user_id = ? ORDER BY id ASC LIMIT 1');
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $clubRow = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!empty($clubRow['id'])) {
                $user['club_id'] = (int) $clubRow['id'];
            }
        } elseif (($user['role'] ?? '') === 'faculty_mentor' && app_column_exists('clubs', 'faculty_mentor_user_id')) {
            $stmt = $conn->prepare('SELECT id FROM clubs WHERE faculty_mentor_user_id = ? ORDER BY id ASC LIMIT 1');
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $clubRow = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!empty($clubRow['id'])) {
                $user['club_id'] = (int) $clubRow['id'];
            }
        }
    }

    $_SESSION['user_id'] = (int) $user['id'];

    $_SESSION['user'] = $user;
    return $user;
}

function app_effective_school_id(array $user): ?int
{
    $schoolId = (int) ($user['school_id'] ?? 0);
    if ($schoolId > 0) {
        return $schoolId;
    }

    if (!app_table_exists('school_role_assignments')) {
        return null;
    }

    global $conn;
    $userId = (int) ($user['id'] ?? 0);
    if ($userId <= 0) {
        return null;
    }

    $stmt = $conn->prepare('SELECT school_id FROM school_role_assignments WHERE user_id = ? ORDER BY id ASC LIMIT 1');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return !empty($row['school_id']) ? (int) $row['school_id'] : null;
}

function app_effective_club_id(array $user): ?int
{
    $clubId = (int) ($user['club_id'] ?? 0);
    return $clubId > 0 ? $clubId : null;
}

function app_require_login(): array
{
    app_session_timeout();

    $user = app_current_user();
    if (!$user) {
        app_flash_set('error', 'Please log in to continue.');
        app_redirect('login.php');
    }

    if (($user['status'] ?? 'active') !== 'active') {
        $_SESSION = [];
        session_destroy();
        session_start();
        app_flash_set('error', 'Your account is inactive. Please contact admin.');
        app_redirect('login.php');
    }

    return $user;
}

function app_require_roles(array $roles): array
{
    $user = app_require_login();
    if (!in_array($user['role'], $roles, true)) {
        app_flash_set('error', 'You do not have access to this page.');
        app_redirect('dashboard.php');
    }
    return $user;
}

function app_role_dashboard(string $role): string
{
    $role = app_normalize_role($role);

    $map = [
        'super_admin' => 'admin-center.php',
        'club_head' => 'dashboard.php',
        'faculty_mentor' => 'dashboard.php',
        'president_vc' => 'dashboard.php',
        'gs_treasurer' => 'dashboard.php',
        'school_head' => 'dashboard.php',
        'admin_office' => 'dashboard.php',
        'rector' => 'dashboard.php',
        'deputy_registrar' => 'dashboard.php',
        'dy_director' => 'dashboard.php',
        'deputy_director' => 'dashboard.php',
        'director' => 'dashboard.php',
        'it_team' => 'dashboard.php',
        'housekeeping' => 'dashboard.php',
        'security_officer' => 'dashboard.php',
        'purchase_officer' => 'dashboard.php',
        'accounts_officer' => 'dashboard.php',
        'sports_dept' => 'dashboard.php',
        'food_admin' => 'dashboard.php',
        'student' => 'student-events.php'
    ];

    return $map[$role] ?? 'dashboard.php';
}

function app_school_labels(): array
{
    return [
        1 => 'School of Business Management',
        2 => 'School of Technology Management & Engineering',
        3 => 'School of Pharmacy & Technology Management',
        4 => 'School of Law',
        5 => 'School of Commerce'
    ];
}

function app_school_label(?int $schoolId): string
{
    if (!$schoolId) {
        return 'All Schools';
    }

    $labels = app_school_labels();
    return $labels[$schoolId] ?? 'School #' . $schoolId;
}

function app_get_school_role_assignment(int $schoolId, string $roleType): ?array
{
    global $conn;

    if (!app_table_exists('school_role_assignments')) {
        return null;
    }

    $stmt = $conn->prepare('SELECT sra.user_id, u.full_name, u.email, u.role FROM school_role_assignments sra JOIN users u ON u.id = sra.user_id WHERE sra.school_id = ? AND sra.role_type = ? LIMIT 1');
    $stmt->bind_param('is', $schoolId, $roleType);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

function app_school_role_user_id(int $schoolId, string $roleType): ?int
{
    $assignment = app_get_school_role_assignment($schoolId, $roleType);
    return $assignment ? (int) $assignment['user_id'] : null;
}

function app_school_logo_path(?int $schoolId): ?string
{
    if (!$schoolId || !app_table_exists('schools') || !app_column_exists('schools', 'logo_path')) {
        return null;
    }

    global $conn;

    $stmt = $conn->prepare('SELECT logo_path FROM schools WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $schoolId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $path = trim((string)($row['logo_path'] ?? ''));
    return $path !== '' ? $path : null;
}

function app_club_logo_path(?int $clubId): ?string
{
    if (!$clubId || !app_table_exists('clubs') || !app_column_exists('clubs', 'club_logo')) {
        return null;
    }

    global $conn;

    $stmt = $conn->prepare('SELECT club_logo FROM clubs WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $clubId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $path = trim((string)($row['club_logo'] ?? ''));
    return $path !== '' ? $path : null;
}

function app_nmims_logo_path(): string
{
    return 'assets/images/nmims-crest.svg';
}

function app_brand_identity(array $user): array
{
    $role = app_normalize_role((string) ($user['role'] ?? ''));
    $schoolId = isset($user['school_id']) ? (int) $user['school_id'] : null;
    $clubId = isset($user['club_id']) ? (int) $user['club_id'] : null;

    $nmimsLogo = app_nmims_logo_path();
    $clubLogo = $clubId ? app_club_logo_path($clubId) : null;
    $schoolLogo = $schoolId ? app_school_logo_path($schoolId) : null;

    $schoolCode = 'NMIMS';
    $schoolName = app_school_label($schoolId);
    if ($schoolId && app_table_exists('schools')) {
        global $conn;
        $stmt = $conn->prepare('SELECT school_name, school_code FROM schools WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $schoolId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!empty($row['school_code'])) {
            $schoolCode = (string) $row['school_code'];
        }
        if (!empty($row['school_name'])) {
            $schoolName = (string) $row['school_name'];
        }
    }

    $clubName = 'Club';
    if ($clubId && app_table_exists('clubs')) {
        global $conn;
        $stmt = $conn->prepare('SELECT club_name FROM clubs WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $clubId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!empty($row['club_name'])) {
            $clubName = (string) $row['club_name'];
        }
    }

    if (in_array($role, ['club_head', 'faculty_mentor'], true)) {
        return [
            'logo' => $clubLogo ?: $nmimsLogo,
            'title' => $clubName,
            'subtitle' => $schoolCode,
        ];
    }

    if (in_array($role, ['school_head', 'president_vc', 'gs_treasurer'], true)) {
        return [
            'logo' => $schoolLogo ?: $nmimsLogo,
            'title' => $schoolCode . ' Event Workflow',
            'subtitle' => $schoolName,
        ];
    }

    return [
        'logo' => $nmimsLogo,
        'title' => 'Smart Event',
        'subtitle' => 'White Paper Workflow',
    ];
}

function app_fetch_recent_activity(int $limit = 8): array
{
    global $conn;

    if (!app_table_exists('activity_logs')) {
        return [];
    }

    $stmt = $conn->prepare('SELECT al.*, u.full_name FROM activity_logs al JOIN users u ON u.id = al.actor_user_id ORDER BY al.created_at DESC LIMIT ?');
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $rows;
}

function app_fetch_school_clubs(int $schoolId): array
{
    global $conn;

    if ($schoolId <= 0 || !app_table_exists('clubs')) {
        return [];
    }

    $stmt = $conn->prepare('SELECT c.*, COUNT(DISTINCT p.id) AS proposal_count, COUNT(DISTINCT CASE WHEN p.overall_status = "approved" THEN p.id END) AS approved_count FROM clubs c LEFT JOIN proposals p ON p.club_id = c.id WHERE c.school_id = ? GROUP BY c.id ORDER BY c.club_name ASC');
    $stmt->bind_param('i', $schoolId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $rows;
}

function app_is_main_approver_role(string $role): bool
{
    $role = app_normalize_role($role);

    return in_array($role, [
        'faculty_mentor',
        'president_vc',
        'gs_treasurer',
        'school_head',
        'admin_office',
        'rector',
        'deputy_registrar',
        'dy_director',
        'deputy_director',
        'director'
    ], true);
}

function app_is_department_role(string $role): bool
{
    $role = app_normalize_role($role);

    return in_array($role, [
        'it_team',
        'housekeeping',
        'security_officer',
        'purchase_officer',
        'accounts_officer',
        'sports_dept',
        'food_admin'
    ], true);
}

function app_service_to_department_role(): array
{
    return [
        'Lights and Sound System' => 'it_team',
        'Lights & Sound System' => 'it_team',
        'Sound System' => 'it_team',
        'Projector' => 'it_team',
        'IT Support' => 'it_team',
        'Projector / IT Support' => 'it_team',
        'Camera' => 'purchase_officer',
        'Inauguration Lamp' => 'purchase_officer',
        'Executive lunch/dinner' => 'food_admin',
        'Executive Lunch/Dinner' => 'food_admin',
        'Food / Admin Side' => 'food_admin',
        'Housekeeping' => 'housekeeping',
        'Housekeeping Staff' => 'housekeeping',
        'Security Staff' => 'security_officer',
        'Security' => 'security_officer',
        'Other Resources' => 'admin_office',
        'Bouquet' => 'purchase_officer',
        'Gift/Memento' => 'purchase_officer',
        'Gift / Memento' => 'purchase_officer',
        'Certificates' => 'purchase_officer',
        'Medals' => 'purchase_officer',
        'Transport' => 'purchase_officer',
        'Water Bottles' => 'food_admin',
        'Tea / Snacks' => 'food_admin'
    ];
}

function app_main_workflow_chain(): array
{
    return [
        'faculty_mentor',
        'gs_treasurer',
        'president_vc',
        'school_head',
        'it_team',
        'housekeeping',
        'food_admin',
        'sports_dept',
        'security_officer',
        'rector',
        'purchase_officer',
        'accounts_officer',
        'admin_office',
        'deputy_registrar',
        'deputy_director',
        'director'
    ];
}

function app_stage_map(): array
{
    return [
        'faculty_mentor' => 'under_faculty_mentor_review',
        'gs_treasurer' => 'under_gs_treasurer_review',
        'president_vc' => 'under_president_vc_review',
        'school_head' => 'under_school_head_review',
        'it_team' => 'under_service_clearance',
        'housekeeping' => 'under_service_clearance',
        'food_admin' => 'under_service_clearance',
        'security_officer' => 'under_service_clearance',
        'rector' => 'under_service_clearance',
        'purchase_officer' => 'under_service_clearance',
        'accounts_officer' => 'under_service_clearance',
        'admin_office' => 'under_admin_office_review',
        'sports_dept' => 'under_service_clearance',
        'deputy_registrar' => 'under_deputy_registrar_review',
        'dy_director' => 'under_dy_director_review',
        'deputy_director' => 'under_dy_director_review',
        'director' => 'under_director_review'
    ];
}

function app_make_proposal_code(int $proposalId): string
{
    return 'WP-' . date('Y') . '-' . str_pad((string)$proposalId, 5, '0', STR_PAD_LEFT);
}

function app_create_notification(int $userId, string $title, string $message, ?int $proposalId = null, ?int $eventId = null, string $type = 'info'): void
{
    global $conn;

    if (!app_table_exists('notifications')) {
        return;
    }

    $stmt = $conn->prepare('INSERT INTO notifications (user_id, title, message, type, related_proposal_id, related_event_id) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->bind_param('isssii', $userId, $title, $message, $type, $proposalId, $eventId);
    $stmt->execute();
    $stmt->close();
}

function app_mark_notifications_read(int $userId): void
{
    global $conn;

    $stmt = $conn->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->close();
}

function app_get_unread_count(int $userId): int
{
    global $conn;

    $stmt = $conn->prepare('SELECT COUNT(*) AS c FROM notifications WHERE user_id = ? AND is_read = 0');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $count = (int) (($result->fetch_assoc()['c'] ?? 0));
    $stmt->close();

    return $count;
}

function app_fetch_notifications(int $userId, int $limit = 10): array
{
    global $conn;

    $stmt = $conn->prepare('SELECT id, title, message, type, related_proposal_id, created_at, is_read FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?');
    $stmt->bind_param('ii', $userId, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $rows;
}

function app_fetch_unread_notifications(int $userId, int $limit = 5): array
{
    global $conn;

    if (!app_table_exists('notifications')) {
        return [];
    }

    $stmt = $conn->prepare('SELECT id, title, message, type, related_proposal_id, created_at FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT ?');
    $stmt->bind_param('ii', $userId, $limit);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $rows;
}

function app_parse_checkbox(string $name): int
{
    return isset($_POST[$name]) ? 1 : 0;
}

function app_clean_text(string $value): string
{
    return trim(filter_var($value, FILTER_SANITIZE_FULL_SPECIAL_CHARS));
}

function app_column_exists(string $tableName, string $columnName): bool
{
    global $conn;

    $stmt = $conn->prepare('SELECT COUNT(*) AS c FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?');
    $stmt->bind_param('ss', $tableName, $columnName);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = (int) (($result->fetch_assoc()['c'] ?? 0)) > 0;
    $stmt->close();

    return $exists;
}

function app_proposal_approval_columns(): array
{
    return [
        'current_approval_level',
        'faculty_mentor_status',
        'president_status',
        'gs_treasurer_status',
        'school_head_status',
        'admin_officer_status',
        'it_team_status',
        'housekeeping_status',
        'security_status',
        'rector_status',
        'purchase_status',
        'accounts_status',
        'sports_dept_status',
        'dy_registrar_status',
        'dy_director_status',
        'deputy_director_status',
        'director_status'
    ];
}

function app_has_explicit_approval_flow(): bool
{
    return app_table_exists('proposals') && app_column_exists('proposals', 'current_approval_level') && app_column_exists('proposals', 'faculty_mentor_status');
}

function app_role_to_approval_column(string $role): ?string
{
    $role = app_normalize_role($role);

    return [
        'faculty_mentor' => 'faculty_mentor_status',
        'president_vc' => 'president_status',
        'gs_treasurer' => 'gs_treasurer_status',
        'school_head' => 'school_head_status',
        'admin_office' => 'admin_officer_status',
        'it_team' => 'it_team_status',
        'housekeeping' => 'housekeeping_status',
        'security_officer' => 'security_status',
        'rector' => 'rector_status',
        'purchase_officer' => 'purchase_status',
        'accounts_officer' => 'accounts_status',
        'sports_dept' => 'sports_dept_status',
        'deputy_registrar' => 'dy_registrar_status',
        'deputy_director' => 'deputy_director_status',
        'dy_director' => 'dy_director_status',
        'director' => 'director_status'
    ][$role] ?? null;
}

function app_all_approval_roles(): array
{
    return [
        'faculty_mentor',
        'gs_treasurer',
        'president_vc',
        'school_head',
        'admin_office',
        'it_team',
        'housekeeping',
        'security_officer',
        'rector',
        'purchase_officer',
        'accounts_officer',
        'sports_dept',
        'deputy_registrar',
        'dy_director',
        'deputy_director',
        'director'
    ];
}

function app_status_options(): array
{
    return ['Pending', 'Approved', 'Rejected', 'Query Raised', 'Skipped', 'Not Required'];
}

function app_workflow_next_level(int $currentLevel): int
{
    return $currentLevel + 1;
}

function app_approval_level_for_role(string $role): ?int
{
    $role = app_normalize_role($role);

    return [
        'faculty_mentor' => 1,
        'gs_treasurer' => 2,
        'president_vc' => 3,
        'school_head' => 4,
        'it_team' => 5,
        'housekeeping' => 5,
        'food_admin' => 5,
        'sports_dept' => 5,
        'security_officer' => 6,
        'rector' => 6,
        'purchase_officer' => 6,
        'accounts_officer' => 6,
        'admin_office' => 6,
        'deputy_registrar' => 7,
        'dy_director' => 8,
        'deputy_director' => 8,
        'director' => 9
    ][$role] ?? null;
}

function app_update_proposal_status_fields(int $proposalId, array $fields): void
{
    global $conn;

    if (empty($fields)) {
        return;
    }

    $assignments = [];
    $values = [];
    $types = '';
    foreach ($fields as $column => $value) {
        $assignments[] = $column . ' = ?';
        $values[] = $value;
        $types .= is_int($value) ? 'i' : (is_float($value) ? 'd' : 's');
    }

    $sql = 'UPDATE proposals SET ' . implode(', ', $assignments) . ' WHERE id = ?';
    $types .= 'i';
    $values[] = $proposalId;

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$values);
    $stmt->execute();
    $stmt->close();
}

function app_initialize_explicit_proposal_flow(int $proposalId): void
{
    if (!app_has_explicit_approval_flow()) {
        return;
    }

    global $conn;

    $stmt = $conn->prepare('UPDATE proposals SET current_approval_level = 1, faculty_mentor_status = "Pending", gs_treasurer_status = "Pending", president_status = "Pending", school_head_status = "Pending", admin_officer_status = "Pending", it_team_status = "Not Required", housekeeping_status = "Not Required", security_status = "Not Required", rector_status = "Pending", purchase_status = "Pending", accounts_status = "Pending", sports_dept_status = "Not Required", dy_registrar_status = "Pending", dy_director_status = "Pending", deputy_director_status = "Pending", director_status = "Pending" WHERE id = ?');
    $stmt->bind_param('i', $proposalId);
    $stmt->execute();
    $stmt->close();
}

function app_workflow_role_sequence(): array
{
    return [
        'faculty_mentor' => 'Faculty Mentor',
        'gs_treasurer' => 'GS / Treasurer',
        'president_vc' => 'President / VC',
        'school_head' => 'School Head',
        'it_team' => 'IT Team',
        'housekeeping' => 'Housekeeping',
        'food_admin' => 'Food / Admin Incharge',
        'sports_dept' => 'Sports Department',
        'security_officer' => 'Security Officer',
        'rector' => 'Rector',
        'purchase_officer' => 'Purchase Officer',
        'accounts_officer' => 'Accounts Officer',
        'admin_office' => 'Administration Officer',
        'deputy_registrar' => 'Deputy Registrar',
        'deputy_director' => 'Deputy Director',
        'dy_director' => 'Deputy Director',
        'director' => 'Director'
    ];
}

function app_workflow_step_badge_label(string $status): string
{
    $normalized = strtolower(trim($status));

    return match ($normalized) {
        'approved' => 'Approved',
        'pending' => 'Pending',
        'query_raised' => 'Query Raised',
        'rejected' => 'Rejected',
        'not_required' => 'Not Required',
        'resubmitted' => 'Resubmitted',
        'locked' => 'Locked',
        default => ucwords(str_replace('_', ' ', $normalized)),
    };
}

function app_get_proposal_role_statuses(array $proposal): array
{
    if (!empty($proposal['workflow_steps']) && is_array($proposal['workflow_steps'])) {
        $labels = app_workflow_role_sequence();
        $statusMap = [];

        foreach ($proposal['workflow_steps'] as $step) {
            $roleName = (string) ($step['role_name'] ?? '');
            if ($roleName === '') {
                continue;
            }

            $label = $labels[$roleName] ?? app_role_label($roleName);
            $statusMap[$label] = app_workflow_step_badge_label((string) ($step['status'] ?? 'pending'));
        }

        return $statusMap;
    }

    return [
        'Faculty Mentor' => app_workflow_step_badge_label((string) ($proposal['faculty_mentor_status'] ?? 'Pending')),
        'GS / Treasurer' => app_workflow_step_badge_label((string) ($proposal['gs_treasurer_status'] ?? 'Pending')),
        'President / VC' => app_workflow_step_badge_label((string) ($proposal['president_status'] ?? 'Pending')),
        'School Head' => app_workflow_step_badge_label((string) ($proposal['school_head_status'] ?? 'Pending')),
        'IT Team' => app_workflow_step_badge_label((string) ($proposal['it_team_status'] ?? 'Not Required')),
        'Housekeeping' => app_workflow_step_badge_label((string) ($proposal['housekeeping_status'] ?? 'Not Required')),
        'Security Officer' => app_workflow_step_badge_label((string) ($proposal['security_status'] ?? 'Not Required')),
        'Rector' => app_workflow_step_badge_label((string) ($proposal['rector_status'] ?? 'Not Required')),
        'Purchase Officer' => app_workflow_step_badge_label((string) ($proposal['purchase_status'] ?? 'Not Required')),
        'Accounts Officer' => app_workflow_step_badge_label((string) ($proposal['accounts_status'] ?? 'Not Required')),
        'Administration Officer' => app_workflow_step_badge_label((string) ($proposal['admin_officer_status'] ?? 'Pending')),
        'Sports Department' => app_workflow_step_badge_label((string) ($proposal['sports_dept_status'] ?? 'Not Required')),
        'Deputy Registrar' => app_workflow_step_badge_label((string) ($proposal['dy_registrar_status'] ?? 'Not Required')),
        'Deputy Director' => app_workflow_step_badge_label((string) ($proposal['deputy_director_status'] ?? ($proposal['dy_director_status'] ?? 'Not Required'))),
        'Director' => app_workflow_step_badge_label((string) ($proposal['director_status'] ?? 'Not Required'))
    ];
}

function app_table_exists(string $tableName): bool
{
    global $conn;

    $stmt = $conn->prepare('SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
    $stmt->bind_param('s', $tableName);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = (int) (($result->fetch_assoc()['c'] ?? 0)) > 0;
    $stmt->close();

    return $exists;
}

function app_safe_count(string $sql, string $types = '', array $params = []): int
{
    global $conn;

    try {
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return 0;
        }

        if ($types !== '' && !empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        if (!$row) {
            return 0;
        }

        return (int) array_values($row)[0];
    } catch (Throwable $e) {
        return 0;
    }
}
