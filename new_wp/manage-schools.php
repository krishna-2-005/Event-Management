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
    $schoolName = app_clean_text((string) ($_POST['school_name'] ?? ''));
    $schoolCode = app_clean_text((string) ($_POST['school_code'] ?? ''));
    $logoPath = null;

    if (!empty($_FILES['logo']['name']) && is_uploaded_file($_FILES['logo']['tmp_name'])) {
        $targetDir = __DIR__ . '/uploads/school_logos';
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }
        $fileName = uniqid('school_', true) . '_' . basename($_FILES['logo']['name']);
        $targetPath = $targetDir . '/' . $fileName;
        if (move_uploaded_file($_FILES['logo']['tmp_name'], $targetPath)) {
            $logoPath = 'uploads/school_logos/' . $fileName;
        }
    }

    if ($schoolName !== '' && $schoolCode !== '') {
        $stmt = $conn->prepare('INSERT INTO schools (school_name, school_code, logo_path) VALUES (?, ?, ?)');
        $stmt->bind_param('sss', $schoolName, $schoolCode, $logoPath);
        $stmt->execute();
        $stmt->close();
        app_flash_set('success', 'School added successfully.');
    }

    app_redirect('manage-schools.php');
}

$schools = [];
if (app_table_exists('schools')) {
    $result = $conn->query('
        SELECT s.*, 
               COUNT(c.id) AS clubs_count,
               (SELECT u.full_name FROM school_role_assignments sra LEFT JOIN users u ON u.id = sra.user_id WHERE sra.school_id = s.id AND sra.role_type = "school_head" LIMIT 1) AS school_head_name,
               (SELECT u.full_name FROM school_role_assignments sra LEFT JOIN users u ON u.id = sra.user_id WHERE sra.school_id = s.id AND sra.role_type = "president_vc" LIMIT 1) AS president_vc_name,
               (SELECT u.full_name FROM school_role_assignments sra LEFT JOIN users u ON u.id = sra.user_id WHERE sra.school_id = s.id AND sra.role_type = "gs_treasurer" LIMIT 1) AS gs_treasurer_name
        FROM schools s 
        LEFT JOIN clubs c ON c.school_id = s.id 
        GROUP BY s.id 
        ORDER BY s.school_name ASC
    ');
    if ($result) {
        $schools = $result->fetch_all(MYSQLI_ASSOC);
    }
}

layout_render_header('Manage Schools', $user, 'manage_schools');
?>
<section class="panel">
    <div class="panel-header"><h3>Add School</h3></div>
    <form method="post" enctype="multipart/form-data" class="form-grid">
        <div class="field"><label>School Name</label><input name="school_name" required></div>
        <div class="field"><label>School Code</label><input name="school_code" required></div>
        <div class="field"><label>School Logo</label><input type="file" name="logo" accept="image/*"></div>
        <div class="field" style="justify-content:end;"><label>&nbsp;</label><button class="btn" type="submit">Save School</button></div>
    </form>
</section>

<section class="panel">
    <div class="panel-header"><h3>Schools Overview</h3></div>
    <div class="card-grid">
        <?php foreach ($schools as $school) { ?>
            <div class="card" style="position:relative;">
                <p style="color:var(--muted);font-size:0.85rem;margin-bottom:4px;"><?php echo htmlspecialchars($school['school_code']); ?></p>
                <h3><?php echo htmlspecialchars($school['school_name']); ?></h3>
                <p style="margin:8px 0;">👥 <strong><?php echo (int) $school['clubs_count']; ?></strong> clubs</p>
                
                <!-- Role assignments -->
                <div style="margin-top:12px;padding-top:12px;border-top:1px solid var(--line);font-size:0.85rem;">
                    <div style="margin-bottom:6px;">
                        <span style="color:var(--muted);">🎓 Head:</span> 
                        <strong><?php echo $school['school_head_name'] ? htmlspecialchars($school['school_head_name']) : '<em style="color:#999;">Unassigned</em>'; ?></strong>
                    </div>
                    <div style="margin-bottom:6px;">
                        <span style="color:var(--muted);">👔 President:</span> 
                        <strong><?php echo $school['president_vc_name'] ? htmlspecialchars($school['president_vc_name']) : '<em style="color:#999;">Unassigned</em>'; ?></strong>
                    </div>
                    <div>
                        <span style="color:var(--muted);">💰 Treasurer:</span> 
                        <strong><?php echo $school['gs_treasurer_name'] ? htmlspecialchars($school['gs_treasurer_name']) : '<em style="color:#999;">Unassigned</em>'; ?></strong>
                    </div>
                </div>
                
                <a href="manage-school-roles.php" style="display:inline-block;margin-top:12px;font-size:0.8rem;color:var(--primary);text-decoration:underline;">📋 Manage Roles</a>
            </div>
        <?php } ?>
    </div>
</section>
<?php layout_render_footer(); ?>
