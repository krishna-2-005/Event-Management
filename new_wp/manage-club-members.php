<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/layout.php';

$user = app_require_login();
$role = app_normalize_role((string) $user['role'], $user['sub_role'] ?? null);

if ($role !== 'super_admin') {
    app_flash_set('error', 'This page is for super admin only.');
    app_redirect(app_role_dashboard($role));
}

$allowedRoles = ['club_head', 'faculty_mentor'];
$hasUsers = app_table_exists('users');
$hasSchools = app_table_exists('schools');
$hasClubs = app_table_exists('clubs');
$hasStatus = app_column_exists('users', 'status');
$hasClubId = app_column_exists('users', 'club_id');
$hasProfileImage = app_column_exists('users', 'profile_image');
$hasEmployeeId = app_column_exists('users', 'employee_student_id');
$hasGender = app_column_exists('users', 'gender');
$hasDepartment = app_column_exists('users', 'department');

if (!$hasUsers || !$hasSchools) {
    app_flash_set('error', 'Required tables are missing. Please import schema_v2.sql first.');
    app_redirect('admin-center.php');
}

function member_upload_profile_image(): ?string
{
    if (empty($_FILES['profile_image']['name']) || !is_uploaded_file($_FILES['profile_image']['tmp_name'])) {
        return null;
    }

    $targetDir = __DIR__ . '/uploads/profile_images';
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    $fileName = uniqid('profile_', true) . '_' . basename((string) $_FILES['profile_image']['name']);
    $targetPath = $targetDir . '/' . $fileName;

    if (!move_uploaded_file($_FILES['profile_image']['tmp_name'], $targetPath)) {
        return null;
    }

    return 'uploads/profile_images/' . $fileName;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['form_action'] ?? 'create_member');

    if ($action === 'toggle_status') {
        $memberId = (int) ($_POST['member_id'] ?? 0);
        if ($memberId > 0 && $hasStatus) {
            $stmt = $conn->prepare('UPDATE users SET status = CASE WHEN status = "active" THEN "inactive" ELSE "active" END WHERE id = ? AND role IN ("club_head", "faculty_mentor")');
            $stmt->bind_param('i', $memberId);
            $stmt->execute();
            $stmt->close();
            app_flash_set('success', 'Member status updated.');
        }
        app_redirect('manage-club-members.php');
    }

    $memberId = (int) ($_POST['member_id'] ?? 0);
    $fullName = app_clean_text((string) ($_POST['full_name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $phone = app_clean_text((string) ($_POST['phone'] ?? ''));
    $memberRole = (string) ($_POST['role'] ?? '');
    $schoolId = (int) ($_POST['school_id'] ?? 0);
    $clubId = (int) ($_POST['club_id'] ?? 0);
    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');
    $status = (string) ($_POST['status'] ?? 'active');
    $employeeId = app_clean_text((string) ($_POST['employee_student_id'] ?? ''));
    $gender = (string) ($_POST['gender'] ?? '');
    $department = app_clean_text((string) ($_POST['department'] ?? ''));

    if (!in_array($memberRole, $allowedRoles, true)) {
        app_flash_set('error', 'Only Club Head and Faculty Mentor roles are allowed on this page.');
        app_redirect('manage-club-members.php');
    }

    if ($schoolId <= 0 || $fullName === '' || $email === '') {
        app_flash_set('error', 'Please fill all required fields.');
        app_redirect('manage-club-members.php');
    }

    if ($hasStatus && !in_array($status, ['active', 'inactive'], true)) {
        $status = 'active';
    }

    if ($hasGender && $gender !== '' && !in_array($gender, ['male', 'female', 'other'], true)) {
        $gender = '';
    }

    if ($action === 'create_member') {
        if ($password === '' || $password !== $confirmPassword) {
            app_flash_set('error', 'Passwords are required and must match.');
            app_redirect('manage-club-members.php');
        }

        $checkStmt = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $checkStmt->bind_param('s', $email);
        $checkStmt->execute();
        $exists = $checkStmt->get_result()->fetch_assoc();
        $checkStmt->close();
        if ($exists) {
            app_flash_set('error', 'Email already exists.');
            app_redirect('manage-club-members.php');
        }

        $profileImagePath = $hasProfileImage ? member_upload_profile_image() : null;
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $columns = ['full_name', 'email', 'password', 'phone', 'role', 'school_id'];
        $values = [$fullName, $email, $passwordHash, $phone, $memberRole, $schoolId];
        $types = 'sssssi';

        if ($hasClubId) {
            $columns[] = 'club_id';
            $values[] = $clubId > 0 ? $clubId : null;
            $types .= 'i';
        }
        if ($hasStatus) {
            $columns[] = 'status';
            $values[] = $status;
            $types .= 's';
        }
        if ($hasProfileImage) {
            $columns[] = 'profile_image';
            $values[] = $profileImagePath;
            $types .= 's';
        }
        if ($hasEmployeeId) {
            $columns[] = 'employee_student_id';
            $values[] = $employeeId !== '' ? $employeeId : null;
            $types .= 's';
        }
        if ($hasGender) {
            $columns[] = 'gender';
            $values[] = $gender !== '' ? $gender : null;
            $types .= 's';
        }
        if ($hasDepartment) {
            $columns[] = 'department';
            $values[] = $department !== '' ? $department : null;
            $types .= 's';
        }

        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $sql = 'INSERT INTO users (' . implode(', ', $columns) . ') VALUES (' . $placeholders . ')';
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$values);
        $stmt->execute();
        $stmt->close();

        app_flash_set('success', 'Club member created successfully.');
        app_redirect('manage-club-members.php');
    }

    if ($action === 'update_member' && $memberId > 0) {
        $checkStmt = $conn->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
        $checkStmt->bind_param('si', $email, $memberId);
        $checkStmt->execute();
        $exists = $checkStmt->get_result()->fetch_assoc();
        $checkStmt->close();
        if ($exists) {
            app_flash_set('error', 'Email already exists for another user.');
            app_redirect('manage-club-members.php?edit=' . $memberId);
        }

        $fields = [
            'full_name' => $fullName,
            'email' => $email,
            'phone' => $phone,
            'role' => $memberRole,
            'school_id' => $schoolId,
        ];

        if ($hasClubId) {
            $fields['club_id'] = $clubId > 0 ? $clubId : null;
        }
        if ($hasStatus) {
            $fields['status'] = $status;
        }
        if ($hasEmployeeId) {
            $fields['employee_student_id'] = $employeeId !== '' ? $employeeId : null;
        }
        if ($hasGender) {
            $fields['gender'] = $gender !== '' ? $gender : null;
        }
        if ($hasDepartment) {
            $fields['department'] = $department !== '' ? $department : null;
        }

        if ($hasProfileImage) {
            $newProfile = member_upload_profile_image();
            if ($newProfile !== null) {
                $fields['profile_image'] = $newProfile;
            }
        }

        if ($password !== '') {
            if ($password !== $confirmPassword) {
                app_flash_set('error', 'Passwords do not match.');
                app_redirect('manage-club-members.php?edit=' . $memberId);
            }
            $fields['password'] = password_hash($password, PASSWORD_DEFAULT);
        }

        $setParts = [];
        $types = '';
        $values = [];
        foreach ($fields as $column => $value) {
            $setParts[] = $column . ' = ?';
            $values[] = $value;
            if (is_int($value)) {
                $types .= 'i';
            } else {
                $types .= 's';
            }
        }
        $types .= 'i';
        $values[] = $memberId;

        $sql = 'UPDATE users SET ' . implode(', ', $setParts) . ' WHERE id = ? AND role IN ("club_head", "faculty_mentor")';
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$values);
        $stmt->execute();
        $stmt->close();

        app_flash_set('success', 'Member updated successfully.');
        app_redirect('manage-club-members.php');
    }
}

$schools = [];
if ($hasSchools) {
    $result = $conn->query('SELECT id, school_name, school_code FROM schools ORDER BY school_name ASC');
    if ($result) {
        $schools = $result->fetch_all(MYSQLI_ASSOC);
    }
}

$clubs = [];
if ($hasClubs) {
    $result = $conn->query('SELECT id, school_id, club_name FROM clubs ORDER BY club_name ASC');
    if ($result) {
        $clubs = $result->fetch_all(MYSQLI_ASSOC);
    }
}

$filterSchoolId = (int) ($_GET['school_id'] ?? 0);
$filterRole = (string) ($_GET['role'] ?? '');
$filterStatus = (string) ($_GET['status'] ?? '');
$search = app_clean_text((string) ($_GET['q'] ?? ''));

$where = ['u.role IN ("club_head", "faculty_mentor")'];
$types = '';
$params = [];

if ($filterSchoolId > 0) {
    $where[] = 'u.school_id = ?';
    $types .= 'i';
    $params[] = $filterSchoolId;
}
if (in_array($filterRole, $allowedRoles, true)) {
    $where[] = 'u.role = ?';
    $types .= 's';
    $params[] = $filterRole;
}
if ($hasStatus && in_array($filterStatus, ['active', 'inactive'], true)) {
    $where[] = 'u.status = ?';
    $types .= 's';
    $params[] = $filterStatus;
}
if ($search !== '') {
    $where[] = '(u.full_name LIKE ? OR u.email LIKE ?)';
    $types .= 'ss';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

$selectProfile = $hasProfileImage ? 'u.profile_image' : 'NULL AS profile_image';
$selectEmployee = $hasEmployeeId ? 'u.employee_student_id' : 'NULL AS employee_student_id';
$selectGender = $hasGender ? 'u.gender' : 'NULL AS gender';
$selectDept = $hasDepartment ? 'u.department' : 'NULL AS department';
$selectStatus = $hasStatus ? 'u.status' : '"active" AS status';
$selectClub = $hasClubId ? 'u.club_id' : 'NULL AS club_id';

$sql = 'SELECT u.id, u.full_name, u.email, u.phone, u.role, u.school_id, ' . $selectClub . ', ' . $selectStatus . ', ' . $selectProfile . ', ' . $selectEmployee . ', ' . $selectGender . ', ' . $selectDept . ', s.school_name, c.club_name FROM users u LEFT JOIN schools s ON s.id = u.school_id LEFT JOIN clubs c ON c.id = u.club_id WHERE ' . implode(' AND ', $where) . ' ORDER BY u.created_at DESC, u.full_name ASC';

$members = [];
$stmt = $conn->prepare($sql);
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
if ($result) {
    $members = $result->fetch_all(MYSQLI_ASSOC);
}
$stmt->close();

$editMember = null;
$editId = (int) ($_GET['edit'] ?? 0);
if ($editId > 0) {
    $stmt = $conn->prepare('SELECT id, full_name, email, phone, role, school_id, ' . ($hasClubId ? 'club_id' : 'NULL AS club_id') . ', ' . ($hasStatus ? 'status' : '"active" AS status') . ', ' . ($hasEmployeeId ? 'employee_student_id' : 'NULL AS employee_student_id') . ', ' . ($hasGender ? 'gender' : 'NULL AS gender') . ', ' . ($hasDepartment ? 'department' : 'NULL AS department') . ' FROM users WHERE id = ? AND role IN ("club_head", "faculty_mentor") LIMIT 1');
    $stmt->bind_param('i', $editId);
    $stmt->execute();
    $editMember = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
}

$totalMembers = app_safe_count('SELECT COUNT(*) AS c FROM users WHERE role IN ("club_head", "faculty_mentor")');
$totalClubHeads = app_safe_count('SELECT COUNT(*) AS c FROM users WHERE role = "club_head"');
$totalMentors = app_safe_count('SELECT COUNT(*) AS c FROM users WHERE role = "faculty_mentor"');
$activeMembers = $hasStatus ? app_safe_count('SELECT COUNT(*) AS c FROM users WHERE role IN ("club_head", "faculty_mentor") AND status = "active"') : $totalMembers;
$unassignedMembers = $hasClubId ? app_safe_count('SELECT COUNT(*) AS c FROM users WHERE role IN ("club_head", "faculty_mentor") AND (club_id IS NULL OR club_id = 0)') : 0;

layout_render_header('Manage Club Members', $user, 'manage_club_members');
?>
<section class="card-grid" style="margin-bottom:18px;">
    <div class="card"><p>Total Club Members</p><h3><?php echo (int) $totalMembers; ?></h3></div>
    <div class="card"><p>Club Heads</p><h3><?php echo (int) $totalClubHeads; ?></h3></div>
    <div class="card"><p>Faculty Mentors</p><h3><?php echo (int) $totalMentors; ?></h3></div>
    <div class="card"><p>Active Members</p><h3><?php echo (int) $activeMembers; ?></h3></div>
</section>

<section class="panel">
    <div class="panel-header"><h3><?php echo $editMember ? 'Edit Club Member' : 'Create Club Head / Faculty Mentor'; ?></h3></div>
    <form method="post" enctype="multipart/form-data" class="form-grid">
        <input type="hidden" name="form_action" value="<?php echo $editMember ? 'update_member' : 'create_member'; ?>">
        <?php if ($editMember) { ?><input type="hidden" name="member_id" value="<?php echo (int) $editMember['id']; ?>"><?php } ?>

        <div class="field"><label>Full Name</label><input type="text" name="full_name" required value="<?php echo htmlspecialchars((string) ($editMember['full_name'] ?? '')); ?>"></div>
        <div class="field"><label>Email</label><input type="email" name="email" required value="<?php echo htmlspecialchars((string) ($editMember['email'] ?? '')); ?>"></div>
        <div class="field"><label>Phone Number</label><input type="text" name="phone" value="<?php echo htmlspecialchars((string) ($editMember['phone'] ?? '')); ?>"></div>
        <div class="field"><label>Role</label>
            <select name="role" required>
                <option value="club_head" <?php echo (($editMember['role'] ?? '') === 'club_head') ? 'selected' : ''; ?>>Club Head</option>
                <option value="faculty_mentor" <?php echo (($editMember['role'] ?? '') === 'faculty_mentor') ? 'selected' : ''; ?>>Faculty Mentor</option>
            </select>
        </div>

        <div class="field"><label>School</label>
            <select name="school_id" required id="member-school">
                <option value="">Select School</option>
                <?php foreach ($schools as $school) { ?>
                    <option value="<?php echo (int) $school['id']; ?>" <?php echo ((int) ($editMember['school_id'] ?? 0) === (int) $school['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars((string) $school['school_name']); ?>
                    </option>
                <?php } ?>
            </select>
        </div>

        <?php if ($hasClubId) { ?>
            <div class="field"><label>Assign Club (Optional)</label>
                <select name="club_id" id="member-club">
                    <option value="">Not Assigned</option>
                    <?php foreach ($clubs as $club) { ?>
                        <option data-school="<?php echo (int) $club['school_id']; ?>" value="<?php echo (int) $club['id']; ?>" <?php echo ((int) ($editMember['club_id'] ?? 0) === (int) $club['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars((string) $club['club_name']); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
        <?php } ?>

        <div class="field"><label><?php echo $editMember ? 'New Password (Optional)' : 'Password'; ?></label><input type="password" name="password" <?php echo $editMember ? '' : 'required'; ?>></div>
        <div class="field"><label>Confirm Password</label><input type="password" name="confirm_password" <?php echo $editMember ? '' : 'required'; ?>></div>

        <?php if ($hasStatus) { ?>
            <div class="field"><label>Status</label>
                <select name="status">
                    <option value="active" <?php echo (($editMember['status'] ?? 'active') === 'active') ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo (($editMember['status'] ?? 'active') === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>
        <?php } ?>

        <?php if ($hasEmployeeId) { ?>
            <div class="field"><label>Employee/Student ID (Optional)</label><input name="employee_student_id" value="<?php echo htmlspecialchars((string) ($editMember['employee_student_id'] ?? '')); ?>"></div>
        <?php } ?>

        <?php if ($hasGender) { ?>
            <div class="field"><label>Gender (Optional)</label>
                <select name="gender">
                    <option value="">Not Set</option>
                    <option value="male" <?php echo (($editMember['gender'] ?? '') === 'male') ? 'selected' : ''; ?>>Male</option>
                    <option value="female" <?php echo (($editMember['gender'] ?? '') === 'female') ? 'selected' : ''; ?>>Female</option>
                    <option value="other" <?php echo (($editMember['gender'] ?? '') === 'other') ? 'selected' : ''; ?>>Other</option>
                </select>
            </div>
        <?php } ?>

        <?php if ($hasDepartment) { ?>
            <div class="field"><label>Department (Optional)</label><input name="department" value="<?php echo htmlspecialchars((string) ($editMember['department'] ?? '')); ?>"></div>
        <?php } ?>

        <?php if ($hasProfileImage) { ?>
            <div class="field field-span"><label>Profile Image (Optional)</label><input type="file" name="profile_image" accept="image/*"></div>
        <?php } ?>

        <div class="field" style="justify-content:end;"><label>&nbsp;</label><button class="btn" type="submit"><?php echo $editMember ? 'Update Member' : 'Save Member'; ?></button></div>
        <?php if ($editMember) { ?><div class="field" style="justify-content:end;"><label>&nbsp;</label><a class="btn secondary" href="manage-club-members.php">Cancel Edit</a></div><?php } ?>
    </form>
</section>

<section class="panel" style="margin-top:18px;">
    <div class="panel-header"><h3>Club Members</h3><span class="badge">Unassigned: <?php echo (int) $unassignedMembers; ?></span></div>

    <form method="get" class="form-grid" style="margin-bottom:16px;">
        <div class="field"><label>Search</label><input name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Name or email"></div>
        <div class="field"><label>School</label>
            <select name="school_id">
                <option value="">All Schools</option>
                <?php foreach ($schools as $school) { ?>
                    <option value="<?php echo (int) $school['id']; ?>" <?php echo $filterSchoolId === (int) $school['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars((string) $school['school_name']); ?></option>
                <?php } ?>
            </select>
        </div>
        <div class="field"><label>Role</label>
            <select name="role">
                <option value="">All Roles</option>
                <option value="club_head" <?php echo $filterRole === 'club_head' ? 'selected' : ''; ?>>Club Head</option>
                <option value="faculty_mentor" <?php echo $filterRole === 'faculty_mentor' ? 'selected' : ''; ?>>Faculty Mentor</option>
            </select>
        </div>
        <?php if ($hasStatus) { ?>
            <div class="field"><label>Status</label>
                <select name="status">
                    <option value="">All</option>
                    <option value="active" <?php echo $filterStatus === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $filterStatus === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>
        <?php } ?>
        <div class="field" style="justify-content:end;"><label>&nbsp;</label><button class="btn" type="submit">Apply Filters</button></div>
    </form>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Role</th>
                    <th>School</th>
                    <th>Club Assigned</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($members)) { ?>
                    <tr><td colspan="8">No members found.</td></tr>
                <?php } else { foreach ($members as $member) { ?>
                    <tr>
                        <td><?php echo htmlspecialchars((string) $member['full_name']); ?></td>
                        <td><?php echo htmlspecialchars((string) $member['email']); ?></td>
                        <td><?php echo htmlspecialchars((string) ($member['phone'] ?? '-')); ?></td>
                        <td><span class="badge"><?php echo htmlspecialchars(app_role_label((string) $member['role'])); ?></span></td>
                        <td><?php echo htmlspecialchars((string) ($member['school_name'] ?? 'Not Set')); ?></td>
                        <td><?php echo htmlspecialchars((string) ($member['club_name'] ?? 'Unassigned')); ?></td>
                        <td><span class="badge <?php echo (($member['status'] ?? 'active') === 'active') ? 'approved' : 'rejected'; ?>"><?php echo htmlspecialchars((string) ($member['status'] ?? 'active')); ?></span></td>
                        <td class="inline-actions">
                            <a class="btn secondary" href="manage-club-members.php?edit=<?php echo (int) $member['id']; ?>">Edit</a>
                            <?php if ($hasStatus) { ?>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="form_action" value="toggle_status">
                                <input type="hidden" name="member_id" value="<?php echo (int) $member['id']; ?>">
                                <button type="submit" class="btn <?php echo (($member['status'] ?? 'active') === 'active') ? 'warn' : 'success'; ?>"><?php echo (($member['status'] ?? 'active') === 'active') ? 'Deactivate' : 'Activate'; ?></button>
                            </form>
                            <?php } ?>
                        </td>
                    </tr>
                <?php } } ?>
            </tbody>
        </table>
    </div>
</section>

<script>
(function () {
    var schoolSelect = document.getElementById('member-school');
    var clubSelect = document.getElementById('member-club');
    if (!schoolSelect || !clubSelect) {
        return;
    }

    function filterClubs() {
        var schoolId = schoolSelect.value;
        var options = clubSelect.querySelectorAll('option[data-school]');
        options.forEach(function (option) {
            var visible = !schoolId || option.getAttribute('data-school') === schoolId;
            option.hidden = !visible;
            if (!visible && option.selected) {
                option.selected = false;
            }
        });
    }

    schoolSelect.addEventListener('change', filterClubs);
    filterClubs();
})();
</script>
<?php layout_render_footer(); ?>
