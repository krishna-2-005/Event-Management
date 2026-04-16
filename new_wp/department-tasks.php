<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/layout.php';
require_once __DIR__ . '/inc/workflow.php';

$user = app_require_login();
$role = app_normalize_role((string) $user['role'], $user['sub_role'] ?? null);

if (!app_is_department_role($role)) {
    app_flash_set('error', 'This page is for service department roles only.');
    app_redirect(app_role_dashboard($role));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $taskId = (int)($_POST['task_id'] ?? 0);
    $action = (string)($_POST['action'] ?? '');
    $remarks = app_clean_text((string)($_POST['remarks'] ?? ''));

    try {
        if ($action === 'approve') {
            workflow_department_action($taskId, (int)$user['id'], $role, 'approved', $remarks ?: 'Approved by department.');
            app_flash_set('success', 'Task approved.');
        } elseif ($action === 'reject') {
            workflow_department_action($taskId, (int)$user['id'], $role, 'rejected', $remarks ?: 'Rejected by department.');
            app_flash_set('success', 'Task rejected.');
        } elseif ($action === 'complete') {
            workflow_department_action($taskId, (int)$user['id'], $role, 'completed', $remarks ?: 'Completed by department.');
            app_flash_set('success', 'Task completed.');
        }
    } catch (Throwable $e) {
        app_flash_set('error', $e->getMessage());
    }

    app_redirect('department-tasks.php');
}

$tasks = [];
$upcomingTasks = [];
if (app_table_exists('department_tasks')) {
    $roleLevel = app_approval_level_for_role($role) ?? 0;
    $stmt = $conn->prepare('SELECT dt.id, dt.proposal_id, dt.department_role, dt.status, dt.remarks, dt.assigned_user_id, p.event_name, p.event_date, c.club_name FROM department_tasks dt JOIN proposals p ON p.id = dt.proposal_id JOIN clubs c ON c.id = p.club_id WHERE dt.department_role = ? AND p.current_approval_level = ? AND p.overall_status NOT IN ("query_raised", "rejected_pending_response", "locked") AND dt.status = "pending" AND (dt.assigned_user_id = ? OR dt.assigned_user_id IS NULL OR dt.assigned_user_id = 0) ORDER BY dt.created_at DESC');
    $stmt->bind_param('sii', $role, $roleLevel, $user['id']);
    $stmt->execute();
    $tasks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $upcomingStmt = $conn->prepare('SELECT dt.proposal_id, p.current_approval_level, p.event_name, p.event_date, c.club_name FROM department_tasks dt JOIN proposals p ON p.id = dt.proposal_id JOIN clubs c ON c.id = p.club_id WHERE dt.department_role = ? AND p.current_approval_level <> ? AND p.overall_status NOT IN ("query_raised", "rejected_pending_response", "locked") AND dt.status = "pending" AND (dt.assigned_user_id = ? OR dt.assigned_user_id IS NULL OR dt.assigned_user_id = 0) ORDER BY p.current_approval_level ASC, dt.created_at DESC');
    $upcomingStmt->bind_param('sii', $role, $roleLevel, $user['id']);
    $upcomingStmt->execute();
    $upcomingTasks = $upcomingStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $upcomingStmt->close();
}

layout_render_header('Department Tasks', $user, 'department_tasks');
?>
<section class="panel">
    <div class="panel-header">
        <h3>Actionable Service Tasks</h3>
        <span class="badge pending"><?php echo count($tasks); ?> tasks</span>
    </div>

    <?php if (empty($tasks)) { ?>
        <p>No department tasks are currently waiting for your action.</p>
        <?php if (!empty($upcomingTasks)) { ?>
            <p><small><?php echo count($upcomingTasks); ?> task(s) are queued for your role and will unlock after earlier approval levels are completed.</small></p>
        <?php } ?>
    <?php } else { ?>
        <div class="timeline">
            <?php foreach ($tasks as $task) { ?>
                <div class="timeline-item">
                    <strong><?php echo htmlspecialchars($task['event_name']); ?></strong>
                    <p><?php echo htmlspecialchars($task['club_name']); ?> · <?php echo htmlspecialchars($task['event_date']); ?></p>
                    <p><strong>Status:</strong> <span class="badge <?php echo htmlspecialchars($task['status']); ?>"><?php echo htmlspecialchars($task['status']); ?></span></p>
                    <?php if (empty($task['assigned_user_id'])) { ?><p><small>Unassigned task. It will be auto-assigned when you take action.</small></p><?php } ?>
                    <p><a class="btn" href="view-white-paper.php?proposal_id=<?php echo (int)$task['proposal_id']; ?>">View White Paper</a></p>
                    <form method="post" class="form-grid" style="margin-top:12px;">
                        <input type="hidden" name="task_id" value="<?php echo (int)$task['id']; ?>">
                        <div class="field" style="grid-column: 1 / -1;">
                            <label>Remarks</label>
                            <textarea name="remarks" placeholder="Add remarks for this service clearance"></textarea>
                        </div>
                        <div class="field" style="grid-column: 1 / -1;">
                            <div class="inline-actions">
                                <button class="btn success" type="submit" name="action" value="approve">Approve</button>
                                <button class="btn bad" type="submit" name="action" value="reject">Reject</button>
                                <button class="btn" type="submit" name="action" value="complete">Mark Completed</button>
                            </div>
                        </div>
                    </form>
                </div>
            <?php } ?>
        </div>
    <?php } ?>

    <?php if (!empty($upcomingTasks)) { ?>
        <div class="panel-header" style="margin-top:18px;">
            <h3>Upcoming Tasks</h3>
            <span class="badge"><?php echo count($upcomingTasks); ?> queued</span>
        </div>
        <div class="timeline">
            <?php foreach ($upcomingTasks as $task) { ?>
                <div class="timeline-item">
                    <strong><?php echo htmlspecialchars($task['event_name']); ?></strong>
                    <p><?php echo htmlspecialchars($task['club_name']); ?> · <?php echo htmlspecialchars($task['event_date']); ?></p>
                    <p><strong>Current Level:</strong> <?php echo (int) ($task['current_approval_level'] ?? 0); ?> · <strong>Status:</strong> Waiting for earlier approvals</p>
                    <p><a class="btn" href="view-white-paper.php?proposal_id=<?php echo (int)$task['proposal_id']; ?>">View White Paper</a></p>
                </div>
            <?php } ?>
        </div>
    <?php } ?>
</section>
<?php layout_render_footer(); ?>
