<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/layout.php';
require_once __DIR__ . '/inc/workflow.php';

$user = app_require_login();
$role = app_normalize_role((string) $user['role'], $user['sub_role'] ?? null);

if ($role !== 'club_head') {
    app_flash_set('error', 'This page is for club heads only.');
    app_redirect(app_role_dashboard($role));
}

$proposals = [];
if (app_table_exists('proposals')) {
    if (app_table_exists('approval_workflow_steps')) {
        $submitterColumn = app_column_exists('proposals', 'submitted_by') ? 'submitted_by' : 'user_id';
        $currentStageExpr = app_column_exists('proposals', 'current_stage') ? 'p.current_stage' : '"legacy" AS current_stage';
        $overallStatusExpr = app_column_exists('proposals', 'overall_status') ? 'p.overall_status' : '"pending" AS overall_status';
        $priorityExpr = app_column_exists('proposals', 'priority_level') ? 'p.priority_level' : '"normal" AS priority_level';
        $rejectionsExpr = app_column_exists('proposals', 'rejection_count') ? 'p.rejection_count' : '0 AS rejection_count';
        $lockedExpr = app_column_exists('proposals', 'is_locked') ? 'p.is_locked' : '0 AS is_locked';
        $lockedReasonExpr = app_column_exists('proposals', 'locked_reason') ? 'p.locked_reason' : 'NULL AS locked_reason';

        $sql = 'SELECT p.id, p.proposal_code, p.event_name, p.event_date, '
            . $currentStageExpr . ', '
            . $overallStatusExpr . ', '
            . $priorityExpr . ', '
            . $rejectionsExpr . ', '
            . $lockedExpr . ', '
            . $lockedReasonExpr . ', '
            . 'c.club_name FROM proposals p JOIN clubs c ON c.id = p.club_id WHERE p.' . $submitterColumn . ' = ? ORDER BY p.created_at DESC';

        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $user['id']);
        $stmt->execute();
        $proposals = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        foreach ($proposals as &$proposal) {
            $proposal['workflow_steps'] = workflow_fetch_proposal_steps((int) $proposal['id']);
        }
        unset($proposal);
    } else {
        $stmt = $conn->prepare('SELECT p.id, p.event_name, p.event_date, p.faculty_mentor_status, p.program_chair_status, c.club_name FROM proposals p JOIN clubs c ON c.id = p.club_id WHERE p.user_id = ? ORDER BY p.created_at DESC');
        $stmt->bind_param('i', $user['id']);
        $stmt->execute();
        $proposals = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}

layout_render_header('My Proposals', $user, 'submit_proposal');
?>
<section class="panel">
    <div class="panel-header">
        <h3>My Proposal List</h3>
        <a class="btn" href="submit-proposal.php">New White Paper</a>
    </div>

    <?php if (empty($proposals)) { ?>
        <p>No proposals submitted yet.</p>
    <?php } else { ?>
        <div class="timeline">
            <?php foreach ($proposals as $proposal) { ?>
                <div class="timeline-item">
                    <div class="panel-header" style="margin-bottom:8px;">
                        <div>
                            <strong><a href="proposal-details.php?id=<?php echo (int)$proposal['id']; ?>"><?php echo htmlspecialchars($proposal['event_name']); ?></a></strong><br>
                            <small><?php echo htmlspecialchars($proposal['proposal_code'] ?? ''); ?> · <?php echo htmlspecialchars($proposal['club_name']); ?></small>
                        </div>
                        <span class="badge <?php echo htmlspecialchars($proposal['overall_status'] ?? ($proposal['faculty_mentor_status'] ?? 'pending')); ?>"><?php echo htmlspecialchars(app_workflow_step_badge_label((string) ($proposal['overall_status'] ?? ($proposal['faculty_mentor_status'] ?? 'pending')))); ?></span>
                    </div>
                    <p><strong>Date:</strong> <?php echo htmlspecialchars($proposal['event_date']); ?></p>
                    <?php if (isset($proposal['current_stage'])) { ?><p><strong>Stage:</strong> <?php echo htmlspecialchars($proposal['current_stage']); ?></p><?php } ?>
                    <?php if (isset($proposal['priority_level'])) { ?><p><strong>Priority:</strong> <?php echo htmlspecialchars($proposal['priority_level']); ?></p><?php } ?>
                    <p><strong>Rejections:</strong> <?php echo (int) ($proposal['rejection_count'] ?? 0); ?><?php if (!empty($proposal['is_locked'])) { ?> · <span class="badge rejected">Locked</span><?php } ?></p>
                    <?php if (!empty($proposal['locked_reason'])) { ?><p><?php echo htmlspecialchars((string) $proposal['locked_reason']); ?></p><?php } ?>
                    <?php if (app_has_explicit_approval_flow()) { ?>
                        <div class="workflow-chips" style="margin-top:12px;">
                            <?php foreach (app_get_proposal_role_statuses($proposal) as $label => $status) { ?>
                                <span class="workflow-chip"><?php echo htmlspecialchars($label); ?>: <span class="badge <?php echo strtolower(str_replace(' ', '_', (string)$status)); ?>"><?php echo htmlspecialchars($status); ?></span></span>
                            <?php } ?>
                        </div>
                    <?php } ?>
                    <?php if (in_array(($proposal['overall_status'] ?? ''), ['query_raised', 'rejected_pending_response'], true)) { ?>
                        <div class="inline-actions" style="margin-top:12px;">
                            <a class="btn" href="proposal-details.php?id=<?php echo (int) $proposal['id']; ?>">Respond / Review Reason</a>
                        </div>
                    <?php } ?>
                </div>
            <?php } ?>
        </div>
    <?php } ?>
</section>
<?php layout_render_footer(); ?>
