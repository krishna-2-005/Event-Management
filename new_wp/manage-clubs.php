<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/layout.php';

$user = app_require_login();
$role = app_normalize_role((string) $user['role'], $user['sub_role'] ?? null);

if ($role !== 'super_admin') {
    app_flash_set('error', 'This page is for super admin only.');
    app_redirect(app_role_dashboard($role));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $schoolId = (int) ($_POST['school_id'] ?? 0);
    $clubName = app_clean_text((string) ($_POST['club_name'] ?? ''));
    $clubCode = app_clean_text((string) ($_POST['club_code'] ?? ''));
    $description = app_clean_text((string) ($_POST['description'] ?? ''));
    $clubHeadUserId = !empty($_POST['club_head_user_id']) ? (int) $_POST['club_head_user_id'] : null;
    $facultyMentorUserId = !empty($_POST['faculty_mentor_user_id']) ? (int) $_POST['faculty_mentor_user_id'] : null;
    $clubLogo = null;

    if (!empty($_FILES['club_logo']['name']) && is_uploaded_file($_FILES['club_logo']['tmp_name'])) {
        $targetDir = __DIR__ . '/uploads/club_logos';
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }
        $fileName = uniqid('club_', true) . '_' . basename($_FILES['club_logo']['name']);
        $targetPath = $targetDir . '/' . $fileName;
        if (move_uploaded_file($_FILES['club_logo']['tmp_name'], $targetPath)) {
            $clubLogo = 'uploads/club_logos/' . $fileName;
        }
    }

    if ($schoolId > 0 && $clubName !== '') {
        $clubHeadUserId = ($clubHeadUserId !== null && $clubHeadUserId > 0) ? $clubHeadUserId : null;
        $facultyMentorUserId = ($facultyMentorUserId !== null && $facultyMentorUserId > 0) ? $facultyMentorUserId : null;

        if ($clubHeadUserId !== null && $facultyMentorUserId !== null) {
            $stmt = $conn->prepare('INSERT INTO clubs (school_id, club_name, club_code, club_logo, description, club_head_user_id, faculty_mentor_user_id) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->bind_param('issssii', $schoolId, $clubName, $clubCode, $clubLogo, $description, $clubHeadUserId, $facultyMentorUserId);
        } elseif ($clubHeadUserId !== null) {
            $stmt = $conn->prepare('INSERT INTO clubs (school_id, club_name, club_code, club_logo, description, club_head_user_id, faculty_mentor_user_id) VALUES (?, ?, ?, ?, ?, ?, NULL)');
            $stmt->bind_param('issssi', $schoolId, $clubName, $clubCode, $clubLogo, $description, $clubHeadUserId);
        } elseif ($facultyMentorUserId !== null) {
            $stmt = $conn->prepare('INSERT INTO clubs (school_id, club_name, club_code, club_logo, description, club_head_user_id, faculty_mentor_user_id) VALUES (?, ?, ?, ?, ?, NULL, ?)');
            $stmt->bind_param('issssi', $schoolId, $clubName, $clubCode, $clubLogo, $description, $facultyMentorUserId);
        } else {
            $stmt = $conn->prepare('INSERT INTO clubs (school_id, club_name, club_code, club_logo, description) VALUES (?, ?, ?, ?, ?)');
            $stmt->bind_param('issss', $schoolId, $clubName, $clubCode, $clubLogo, $description);
        }
        $stmt->execute();
        $newClubId = (int) $conn->insert_id;
        $stmt->close();

        if ($newClubId > 0 && app_column_exists('users', 'club_id')) {
            if ($clubHeadUserId !== null) {
                $updateHead = $conn->prepare('UPDATE users SET club_id = ? WHERE id = ? AND role = "club_head"');
                $updateHead->bind_param('ii', $newClubId, $clubHeadUserId);
                $updateHead->execute();
                $updateHead->close();
            }
            if ($facultyMentorUserId !== null) {
                $updateMentor = $conn->prepare('UPDATE users SET club_id = ? WHERE id = ? AND role = "faculty_mentor"');
                $updateMentor->bind_param('ii', $newClubId, $facultyMentorUserId);
                $updateMentor->execute();
                $updateMentor->close();
            }
        }

        app_flash_set('success', 'Club added successfully.');
    }

    app_redirect('manage-clubs.php');
}

$schools = [];
if (app_table_exists('schools')) {
    $result = $conn->query('SELECT id, school_name FROM schools ORDER BY school_name ASC');
    if ($result) {
        $schools = $result->fetch_all(MYSQLI_ASSOC);
    }
}

$users = [];
if (app_table_exists('users')) {
    $statusFilter = app_column_exists('users', 'status') ? ' AND status = "active"' : '';
    $result = $conn->query('SELECT id, full_name, role, school_id FROM users WHERE role IN ("club_head", "faculty_mentor")' . $statusFilter . ' ORDER BY full_name ASC');
    if ($result) {
        $users = $result->fetch_all(MYSQLI_ASSOC);
    }
}

$clubs = [];
if (app_table_exists('clubs')) {
    $result = $conn->query('SELECT c.*, s.school_name FROM clubs c JOIN schools s ON s.id = c.school_id ORDER BY c.club_name ASC');
    if ($result) {
        $clubs = $result->fetch_all(MYSQLI_ASSOC);
    }
}

layout_render_header('Manage Clubs', $user, 'manage_clubs');
?>
<section class="panel">
    <div class="panel-header"><h3>Add Club</h3></div>
    <form method="post" enctype="multipart/form-data" class="form-grid">
        <div class="field"><label>School</label><select id="club-school" name="school_id" required><?php foreach ($schools as $school) { ?><option value="<?php echo (int)$school['id']; ?>"><?php echo htmlspecialchars($school['school_name']); ?></option><?php } ?></select></div>
        <div class="field"><label>Club Name</label><input name="club_name" required></div>
        <div class="field"><label>Club Code</label><input name="club_code"></div>
        <div class="field"><label>Club Logo</label><input type="file" name="club_logo" accept="image/*"></div>
        <div class="field"><label>Club Head</label><select id="club-head-select" name="club_head_user_id"><option value="">Select</option><?php foreach ($users as $item) { if ($item['role'] !== 'club_head') { continue; } ?><option data-school="<?php echo (int)$item['school_id']; ?>" value="<?php echo (int)$item['id']; ?>"><?php echo htmlspecialchars($item['full_name']); ?></option><?php } ?></select></div>
        <div class="field"><label>Faculty Mentor</label><select id="mentor-select" name="faculty_mentor_user_id"><option value="">Select</option><?php foreach ($users as $item) { if ($item['role'] !== 'faculty_mentor') { continue; } ?><option data-school="<?php echo (int)$item['school_id']; ?>" value="<?php echo (int)$item['id']; ?>"><?php echo htmlspecialchars($item['full_name']); ?></option><?php } ?></select></div>
        <div class="field field-span"><label>Description</label><textarea name="description"></textarea></div>
        <div class="field" style="justify-content:end;"><label>&nbsp;</label><button class="btn" type="submit">Save Club</button></div>
    </form>
</section>

<section class="panel">
    <div class="panel-header"><h3>Clubs</h3></div>
    <div class="card-grid">
        <?php foreach ($clubs as $club) { ?>
            <div class="card">
                <p><?php echo htmlspecialchars($club['school_name']); ?></p>
                <h3><?php echo htmlspecialchars($club['club_name']); ?></h3>
                <p><?php echo htmlspecialchars($club['club_code'] ?? ''); ?></p>
            </div>
        <?php } ?>
    </div>
</section>
<script>
(function () {
    var schoolSelect = document.getElementById('club-school');
    var headSelect = document.getElementById('club-head-select');
    var mentorSelect = document.getElementById('mentor-select');
    if (!schoolSelect || !headSelect || !mentorSelect) {
        return;
    }

    function filterOptions(select, schoolId) {
        var options = select.querySelectorAll('option[data-school]');
        options.forEach(function (option) {
            var visible = option.getAttribute('data-school') === schoolId;
            option.hidden = !visible;
            if (!visible && option.selected) {
                option.selected = false;
            }
        });
    }

    function applySchoolFilter() {
        var schoolId = schoolSelect.value;
        filterOptions(headSelect, schoolId);
        filterOptions(mentorSelect, schoolId);
    }

    schoolSelect.addEventListener('change', applySchoolFilter);
    applySchoolFilter();
})();
</script>
<?php layout_render_footer(); ?>
