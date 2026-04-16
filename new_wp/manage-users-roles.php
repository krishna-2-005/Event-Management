<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/layout.php';

$user = app_require_login();
$role = app_normalize_role((string) $user['role'], $user['sub_role'] ?? null);

if ($role !== 'super_admin') {
    app_flash_set('error', 'This page is for super admin only.');
    app_redirect(app_role_dashboard($role));
}

$schoolRoles = ['school_head', 'president_vc', 'gs_treasurer'];
$clubRoles = ['club_head', 'faculty_mentor'];
$campusWideRoles = ['admin_office', 'rector', 'purchase_officer', 'accounts_officer', 'it_team', 'housekeeping', 'security_officer', 'food_admin'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    
    if ($action === 'add_user') {
        $fullName = app_clean_text((string) ($_POST['full_name'] ?? ''));
        $email = app_clean_text((string) ($_POST['email'] ?? ''));
        $phone = app_clean_text((string) ($_POST['phone'] ?? ''));
        $collegeId = app_clean_text((string) ($_POST['college_id'] ?? ''));
        $userRole = (string) ($_POST['user_role'] ?? '');
        $schoolId = in_array($userRole, $schoolRoles, true) ? (int) ($_POST['school_id'] ?? 0) : 0;
        $clubId = in_array($userRole, $clubRoles, true) ? (int) ($_POST['club_id'] ?? 0) : 0;
        $assignmentType = (string) ($_POST['assignment_type'] ?? '');
        
        // Validate inputs
        if (empty($fullName) || empty($email) || empty($userRole)) {
            app_flash_set('error', 'Please fill in all required fields.');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            app_flash_set('error', 'Invalid email address.');
        } else {
            // Check if email already exists
            $stmt = $conn->prepare('SELECT id FROM users WHERE email = ?');
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                app_flash_set('error', 'Email already exists in the system.');
                $stmt->close();
            } else {
                $stmt->close();
                
                // Generate temporary password
                $tempPassword = bin2hex(random_bytes(8));
                $hashedPassword = password_hash($tempPassword, PASSWORD_DEFAULT);
                
                // Insert user
                $insertStmt = $conn->prepare('INSERT INTO users (full_name, email, password, phone, employee_student_id, role, school_id, club_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, "active")');
                $insertStmt->bind_param('ssssssii', $fullName, $email, $hashedPassword, $phone, $collegeId, $userRole, $schoolId, $clubId);
                
                if ($insertStmt->execute()) {
                    $newUserId = $insertStmt->insert_id;
                    $insertStmt->close();
                    
                        // Handle school-level role assignments
                        if ($assignmentType === 'school' && $schoolId > 0 && in_array($userRole, $schoolRoles, true)) {
                            $assignStmt = $conn->prepare('INSERT INTO school_role_assignments (school_id, user_id, role_type) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), assigned_at = CURRENT_TIMESTAMP');
                            $assignStmt->bind_param('iis', $schoolId, $newUserId, $userRole);
                            $assignStmt->execute();
                            $assignStmt->close();
                        }
                    
                    // Success message with temporary password
                    app_flash_set('success', "User <strong>$fullName</strong> created successfully! Temporary password: <code>$tempPassword</code> (Share this securely with the user)");
                } else {
                    app_flash_set('error', 'Failed to create user. Please try again.');
                }
            }
        }
        app_redirect('manage-users-roles.php');
    }
}

// Fetch data
$schools = [];
$clubs = [];
$users = [];

if (app_table_exists('schools')) {
    $result = $conn->query('SELECT id, school_name, school_code FROM schools ORDER BY school_name ASC');
    if ($result) {
        $schools = $result->fetch_all(MYSQLI_ASSOC);
    }
}

if (app_table_exists('clubs')) {
    $result = $conn->query('SELECT id, club_name, school_id FROM clubs ORDER BY club_name ASC');
    if ($result) {
        $clubs = $result->fetch_all(MYSQLI_ASSOC);
    }
}

if (app_table_exists('users')) {
    $result = $conn->query('SELECT id, full_name, email, phone, employee_student_id, role, school_id, club_id FROM users ORDER BY full_name ASC');
    if ($result) {
        $users = $result->fetch_all(MYSQLI_ASSOC);
    }
}

layout_render_header('Manage Users & Roles', $user, 'manage_users_roles');
?>
<style>
.form-section { background: var(--card-bg); border: 1px solid var(--line); border-radius: 8px; padding: 20px; margin-bottom: 24px; }
.form-title { font-size: 1.2rem; font-weight: 700; margin-bottom: 16px; color: var(--ink); }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 14px; }
.form-row.full { grid-template-columns: 1fr; }
.form-group { display: flex; flex-direction: column; }
.form-group label { font-size: 0.9rem; font-weight: 600; margin-bottom: 6px; color: var(--ink); }
.form-group label .required { color: #e74c3c; }
.form-group input,
.form-group select { padding: 10px; border: 1px solid var(--line); border-radius: 6px; font-size: 0.95rem; font-family: inherit; }
.form-group input:focus,
.form-group select:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(194, 24, 52, 0.1); }
.role-info { background: #f0f4f8; border-left: 4px solid var(--primary); padding: 12px; border-radius: 4px; margin-top: 12px; font-size: 0.85rem; color: var(--muted); }
.btn-submit { padding: 12px 28px; background: var(--primary); color: white; border: none; border-radius: 6px; font-size: 1rem; font-weight: 600; cursor: pointer; transition: 0.2s; }
.btn-submit:hover { background: #a01429; }
.users-table { width: 100%; border-collapse: collapse; }
.users-table th { background: var(--bg); padding: 12px; text-align: left; font-weight: 600; border-bottom: 2px solid var(--line); font-size: 0.85rem; text-transform: uppercase; }
.users-table td { padding: 12px; border-bottom: 1px solid var(--line); }
.users-table tr:hover { background: var(--bg); }
.role-badge { display: inline-block; background: var(--primary); color: white; padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; }
.status-active { color: #27ae60; font-weight: 600; }
.status-inactive { color: #e74c3c; font-weight: 600; }
@media (max-width: 768px) {
    .form-row { grid-template-columns: 1fr; }
}
</style>

<section class="panel">
    <div class="panel-header">
        <h3>➕ Add New User & Assign Role</h3>
    </div>
    
    <form method="post" class="form-section">
        <input type="hidden" name="action" value="add_user">
        
        <div class="form-title">👤 User Information</div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Full Name <span class="required">*</span></label>
                <input type="text" name="full_name" required placeholder="e.g., John Doe">
            </div>
            <div class="form-group">
                <label>Email Address <span class="required">*</span></label>
                <input type="email" name="email" required placeholder="e.g., john@nmims.edu">
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Phone Number</label>
                <input type="tel" name="phone" placeholder="e.g., +91-9876543210">
            </div>
            <div class="form-group">
                <label>College ID</label>
                <input type="text" name="college_id" placeholder="e.g., STME2026001">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Assign Role <span class="required">*</span></label>
                <select name="user_role" id="userRole" required onchange="updateRoleInfo()">
                    <option value="">--- Select Role ---</option>
                    <optgroup label="School Leadership">
                        <option value="school_head">🎓 School Head</option>
                        <option value="president_vc">👔 President / VC</option>
                        <option value="gs_treasurer">💰 GS / Treasurer</option>
                    </optgroup>
                    <optgroup label="Club Leadership">
                        <option value="club_head">🎯 Club Head</option>
                        <option value="faculty_mentor">👨‍🏫 Faculty Mentor</option>
                    </optgroup>
                    <optgroup label="Administrative">
                        <option value="admin_office">📋 Admin Office</option>
                        <option value="rector">👑 Rector</option>
                        <option value="purchase_officer">🛒 Purchase Officer</option>
                        <option value="accounts_officer">💼 Accounts Officer</option>
                    </optgroup>
                    <optgroup label="Support Services">
                        <option value="it_team">💻 IT Team</option>
                        <option value="housekeeping">🧹 Housekeeping</option>
                        <option value="security_officer">🔒 Security Officer</option>
                        <option value="food_admin">🍽️ Food Admin</option>
                    </optgroup>
                </select>
            </div>
        </div>

        <!-- School Assignment -->
        <div id="schoolSection" style="display:none;">
            <div class="form-row full">
                <div class="form-group">
                    <label>Assign to School <span class="required">*</span></label>
                    <select name="school_id" id="schoolSelect">
                        <option value="">--- Select School ---</option>
                        <?php foreach ($schools as $s) { ?>
                            <option value="<?php echo (int)$s['id']; ?>"><?php echo htmlspecialchars($s['school_name']); ?> (<?php echo htmlspecialchars($s['school_code']); ?>)</option>
                        <?php } ?>
                    </select>
                    <div class="role-info">
                        ✓ This user will be assigned as a school-level role holder and appear in school leadership dashboard.
                    </div>
                </div>
            </div>
            <input type="hidden" name="assignment_type" id="assignmentType" value="">
        </div>

        <!-- Club Assignment -->
        <div id="clubSection" style="display:none;">
            <div class="form-row full">
                <div class="form-group">
                    <label>Assign to Club <span class="required">*</span></label>
                    <select name="club_id" id="clubSelect">
                        <option value="">--- Select Club ---</option>
                        <?php foreach ($clubs as $c) { ?>
                            <option value="<?php echo (int)$c['id']; ?>"><?php echo htmlspecialchars($c['club_name']); ?></option>
                        <?php } ?>
                    </select>
                    <div class="role-info">
                        ✓ This user will be assigned to the selected club as <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', 'club_role'))); ?>.
                    </div>
                </div>
            </div>
            <input type="hidden" name="assignment_type" id="clubAssignmentType" value="">
        </div>

        <div style="margin-top: 20px; display: flex; gap: 12px;">
            <button type="submit" class="btn-submit">✓ Create User & Assign Role</button>
            <button type="reset" class="btn-submit" style="background: #95a5a6;">↻ Clear Form</button>
        </div>
    </form>
</section>

<!-- All Users List -->
<section class="panel">
    <div class="panel-header">
        <h3>👥 All Users & Their Roles</h3>
    </div>
    
    <div style="overflow-x: auto;">
        <table class="users-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>College ID</th>
                    <th>Role</th>
                    <th>Assigned To</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u) {
                            $assignedTo = '';
                            if (in_array($u['role'], $schoolRoles, true) && $u['school_id']) {
                        $schoolStmt = $conn->prepare('SELECT school_name FROM schools WHERE id = ?');
                        $schoolStmt->bind_param('i', $u['school_id']);
                        $schoolStmt->execute();
                        $schoolResult = $schoolStmt->get_result();
                        if ($schoolRow = $schoolResult->fetch_assoc()) {
                            $assignedTo = '🏫 ' . htmlspecialchars($schoolRow['school_name']);
                        }
                        $schoolStmt->close();
                    }
                            if (in_array($u['role'], $clubRoles, true) && $u['club_id']) {
                        $clubStmt = $conn->prepare('SELECT club_name FROM clubs WHERE id = ?');
                        $clubStmt->bind_param('i', $u['club_id']);
                        $clubStmt->execute();
                        $clubResult = $clubStmt->get_result();
                        if ($clubRow = $clubResult->fetch_assoc()) {
                            $assignedTo = ($assignedTo ? $assignedTo . ' • ' : '') . '🎯 ' . htmlspecialchars($clubRow['club_name']);
                        }
                        $clubStmt->close();
                    }
                    if (empty($assignedTo)) {
                                $assignedTo = '🌐 All Schools';
                    }
                ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($u['full_name']); ?></strong></td>
                        <td><?php echo htmlspecialchars($u['email']); ?></td>
                        <td><?php echo htmlspecialchars($u['phone']); ?></td>
                        <td><?php echo htmlspecialchars($u['employee_student_id'] ?? '—'); ?></td>
                        <td><span class="role-badge"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $u['role']))); ?></span></td>
                        <td><?php echo $assignedTo; ?></td>
                        <td><span class="status-active">✓ Active</span></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</section>

<script>
function updateRoleInfo() {
    const roleSelect = document.getElementById('userRole');
    const schoolSection = document.getElementById('schoolSection');
    const clubSection = document.getElementById('clubSection');
    const assignmentType = document.getElementById('assignmentType');
    const clubAssignmentType = document.getElementById('clubAssignmentType');
    
    const selectedRole = roleSelect.value;
    const schoolRoles = ['school_head', 'president_vc', 'gs_treasurer'];
    const clubRoles = ['club_head', 'faculty_mentor'];
    const campusWideRoles = ['admin_office', 'rector', 'purchase_officer', 'accounts_officer', 'it_team', 'housekeeping', 'security_officer', 'food_admin'];
    
    // Hide both sections first
    schoolSection.style.display = 'none';
    clubSection.style.display = 'none';
    assignmentType.value = '';
    clubAssignmentType.value = '';
    
    // Show appropriate section
    if (schoolRoles.includes(selectedRole)) {
        schoolSection.style.display = 'block';
        assignmentType.value = 'school';
    } else if (clubRoles.includes(selectedRole)) {
        clubSection.style.display = 'block';
        clubAssignmentType.value = 'club';
    } else if (campusWideRoles.includes(selectedRole)) {
        assignmentType.value = 'campus_wide';
    }
}
</script>

<?php layout_render_footer(); ?>
