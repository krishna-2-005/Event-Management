<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/layout.php';

$user = app_require_login();
$role = app_normalize_role((string) $user['role'], $user['sub_role'] ?? null);

if ($role !== 'super_admin') {
    app_flash_set('error', 'This page is for super admin only.');
    app_redirect(app_role_dashboard($role));
}

// Handle role assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    
    if ($action === 'assign') {
        $schoolId = (int) ($_POST['school_id'] ?? 0);
        $roleType = (string) ($_POST['role_type'] ?? '');
        $userId = (int) ($_POST['user_id'] ?? 0);

        if ($schoolId > 0 && $userId > 0 && in_array($roleType, ['school_head', 'president_vc', 'gs_treasurer'], true)) {
            $stmt = $conn->prepare('INSERT INTO school_role_assignments (school_id, user_id, role_type) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), assigned_at = CURRENT_TIMESTAMP');
            $stmt->bind_param('iis', $schoolId, $userId, $roleType);
            $stmt->execute();
            $stmt->close();
            app_flash_set('success', 'Role assigned successfully!');
        } else {
            app_flash_set('error', 'Invalid data provided.');
        }
    } elseif ($action === 'unassign') {
        $schoolId = (int) ($_POST['school_id'] ?? 0);
        $roleType = (string) ($_POST['role_type'] ?? '');
        
        if ($schoolId > 0 && in_array($roleType, ['school_head', 'president_vc', 'gs_treasurer'], true)) {
            $stmt = $conn->prepare('DELETE FROM school_role_assignments WHERE school_id = ? AND role_type = ?');
            $stmt->bind_param('is', $schoolId, $roleType);
            $stmt->execute();
            $stmt->close();
            app_flash_set('success', 'Role unassigned successfully!');
        }
    }

    app_redirect('manage-school-roles.php');
}

$schools = [];
$users = [];
$assignments = [];

// Fetch all schools with their role assignments
if (app_table_exists('schools')) {
    $query = 'SELECT s.*, 
                (SELECT u.full_name FROM school_role_assignments sra LEFT JOIN users u ON u.id = sra.user_id WHERE sra.school_id = s.id AND sra.role_type = "school_head") AS school_head_name,
                (SELECT u.full_name FROM school_role_assignments sra LEFT JOIN users u ON u.id = sra.user_id WHERE sra.school_id = s.id AND sra.role_type = "president_vc") AS president_vc_name,
                (SELECT u.full_name FROM school_role_assignments sra LEFT JOIN users u ON u.id = sra.user_id WHERE sra.school_id = s.id AND sra.role_type = "gs_treasurer") AS gs_treasurer_name
              FROM schools s 
              ORDER BY s.school_name ASC';
    $result = $conn->query($query);
    if ($result) {
        $schools = $result->fetch_all(MYSQLI_ASSOC);
    }
}

// Fetch users who can be assigned these roles
if (app_table_exists('users')) {
    $result = $conn->query('SELECT id, full_name, role, email FROM users WHERE role IN ("school_head", "president_vc", "gs_treasurer") AND status = "active" ORDER BY full_name ASC');
    if ($result) {
        $users = $result->fetch_all(MYSQLI_ASSOC);
    }
}

layout_render_header('Assign School Leadership Roles', $user, 'manage_school_roles');
?>
<style>
.role-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; margin-top: 12px; }
.role-slot { background: var(--card-bg); border: 1px solid var(--line); border-radius: 8px; padding: 14px; }
.role-label { font-size: 0.85rem; color: var(--muted); text-transform: uppercase; font-weight: 600; margin-bottom: 6px; display: block; }
.role-name { font-weight: 700; color: var(--ink); margin-bottom: 8px; min-height: 20px; }
.role-actions { display: flex; gap: 6px; }
.role-actions button { flex: 1; padding: 6px 8px; font-size: 0.8rem; }
@media (max-width: 768px) { .role-grid { grid-template-columns: 1fr; } }
</style>

<section class="panel">
    <div class="panel-header"><h3>📋 School Leadership Roles Overview</h3></div>
    <div style="overflow-x:auto;">
        <?php if (!empty($schools)) { ?>
            <?php foreach ($schools as $school) { ?>
                <div style="margin-bottom: 28px; padding: 16px; background: var(--bg); border-radius: 8px; border-left: 4px solid var(--primary);">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
                        <div>
                            <h4 style="margin:0;color:var(--ink);"><?php echo htmlspecialchars($school['school_name']); ?></h4>
                            <p style="margin:0;font-size:0.85rem;color:var(--muted);"><?php echo htmlspecialchars($school['school_code']); ?></p>
                        </div>
                    </div>

                    <div class="role-grid">
                        <!-- School Head -->
                        <div class="role-slot">
                            <span class="role-label">🎓 School Head</span>
                            <div class="role-name"><?php echo $school['school_head_name'] ? htmlspecialchars($school['school_head_name']) : '<em style="color:var(--muted);">Unassigned</em>'; ?></div>
                            <div class="role-actions">
                                <form method="post" style="flex:1;">
                                    <input type="hidden" name="school_id" value="<?php echo (int)$school['id']; ?>">
                                    <input type="hidden" name="role_type" value="school_head">
                                    <input type="hidden" name="action" value="assign">
                                    <select name="user_id" style="width:100%;padding:6px;font-size:0.8rem;margin-bottom:4px;" required>
                                        <option value="">Select...</option>
                                        <?php foreach ($users as $item) {
                                            if (in_array($item['role'], ['school_head', 'president_vc', 'gs_treasurer'])) {
                                                echo '<option value="' . (int)$item['id'] . '">' . htmlspecialchars($item['full_name']) . '</option>';
                                            }
                                        } ?>
                                    </select>
                                    <button type="submit" class="btn" style="width:100%;padding:6px;font-size:0.8rem;">Assign</button>
                                </form>
                            </div>
                        </div>

                        <!-- President / VC -->
                        <div class="role-slot">
                            <span class="role-label">👔 President / VC</span>
                            <div class="role-name"><?php echo $school['president_vc_name'] ? htmlspecialchars($school['president_vc_name']) : '<em style="color:var(--muted);">Unassigned</em>'; ?></div>
                            <div class="role-actions">
                                <form method="post" style="flex:1;">
                                    <input type="hidden" name="school_id" value="<?php echo (int)$school['id']; ?>">
                                    <input type="hidden" name="role_type" value="president_vc">
                                    <input type="hidden" name="action" value="assign">
                                    <select name="user_id" style="width:100%;padding:6px;font-size:0.8rem;margin-bottom:4px;" required>
                                        <option value="">Select...</option>
                                        <?php foreach ($users as $item) {
                                            if (in_array($item['role'], ['president_vc', 'school_head', 'gs_treasurer'])) {
                                                echo '<option value="' . (int)$item['id'] . '">' . htmlspecialchars($item['full_name']) . '</option>';
                                            }
                                        } ?>
                                    </select>
                                    <button type="submit" class="btn" style="width:100%;padding:6px;font-size:0.8rem;">Assign</button>
                                </form>
                            </div>
                        </div>

                        <!-- GS / Treasurer -->
                        <div class="role-slot">
                            <span class="role-label">💰 GS / Treasurer</span>
                            <div class="role-name"><?php echo $school['gs_treasurer_name'] ? htmlspecialchars($school['gs_treasurer_name']) : '<em style="color:var(--muted);">Unassigned</em>'; ?></div>
                            <div class="role-actions">
                                <form method="post" style="flex:1;">
                                    <input type="hidden" name="school_id" value="<?php echo (int)$school['id']; ?>">
                                    <input type="hidden" name="role_type" value="gs_treasurer">
                                    <input type="hidden" name="action" value="assign">
                                    <select name="user_id" style="width:100%;padding:6px;font-size:0.8rem;margin-bottom:4px;" required>
                                        <option value="">Select...</option>
                                        <?php foreach ($users as $item) {
                                            if (in_array($item['role'], ['gs_treasurer', 'president_vc', 'school_head'])) {
                                                echo '<option value="' . (int)$item['id'] . '">' . htmlspecialchars($item['full_name']) . '</option>';
                                            }
                                        } ?>
                                    </select>
                                    <button type="submit" class="btn" style="width:100%;padding:6px;font-size:0.8rem;">Assign</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php } ?>
        <?php } else { ?>
            <div style="padding:20px;text-align:center;color:var(--muted);">
                <p>No schools found. <a href="manage-schools.php">Create a school first.</a></p>
            </div>
        <?php } ?>
    </div>
</section>

<?php layout_render_footer(); ?>
