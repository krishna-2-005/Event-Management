<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/layout.php';
require_once __DIR__ . '/inc/workflow.php';

$user = app_require_login();
$role = app_normalize_role((string)$user['role'], $user['sub_role'] ?? null);

if (!app_is_main_approver_role($role)) {
    app_flash_set('error', 'This page is for approval roles only.');
    app_redirect(app_role_dashboard($role));
}

$proposalSubmitterColumn = app_column_exists('proposals', 'submitted_by') ? 'submitted_by' : 'user_id';

$proposalExpr = static function (string $column, string $fallbackExpression): string {
    if (app_column_exists('proposals', $column)) {
        return 'p.' . $column;
    }

    return $fallbackExpression . ' AS ' . $column;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $proposalId = (int)($_POST['proposal_id'] ?? 0);
    $action = (string)($_POST['action'] ?? '');
    $remarks = app_clean_text((string)($_POST['remarks'] ?? ''));

    try {
        if (app_table_exists('approval_workflow_steps')) {
            workflow_handle_review_action($proposalId, (int)$user['id'], $role, $action, $remarks);
            app_flash_set('success', 'Proposal updated successfully.');
        } else {
            $proposalStmt = $conn->prepare('SELECT submitted_by, club_id, school_id, overall_status FROM proposals WHERE id = ? LIMIT 1');
            $proposalStmt->bind_param('i', $proposalId);
            $proposalStmt->execute();
            $proposal = $proposalStmt->get_result()->fetch_assoc();
            $proposalStmt->close();

            if (!$proposal) {
                app_flash_set('error', 'Proposal not found.');
                app_redirect('approvals.php');
            }

            if ($action === 'approve') {
                workflow_mark_main_approved_and_progress($proposalId, (int)$user['id'], $role, $remarks ?: 'Approved.');
                app_flash_set('success', 'Proposal approved.');
            } elseif ($action === 'reject') {
                workflow_mark_main_rejected($proposalId, (int)$user['id'], $role, $remarks ?: 'Rejected.');
                app_flash_set('success', 'Proposal rejected.');
            } elseif ($action === 'query') {
                workflow_mark_main_query($proposalId, (int)$user['id'], $role, (int)$proposal['submitted_by'], $remarks ?: 'Please update the proposal.', null);
                app_flash_set('success', 'Query raised successfully.');
            }
        }
    } catch (Throwable $e) {
        app_flash_set('error', $e->getMessage());
    }

    app_redirect('approvals.php');
}

$pending = [];
$upcoming = [];

if (app_table_exists('approval_workflow_steps')) {
    $roleLevel = app_approval_level_for_role($role) ?? 0;
    $proposalFields = [
        'p.proposal_code',
        'p.event_name',
        'p.event_date',
        $proposalExpr('current_approval_level', '1'),
        $proposalExpr('budget_total', '0'),
        $proposalExpr('current_stage', '"legacy"'),
        $proposalExpr('rejection_count', '0'),
        $proposalExpr('is_locked', '0'),
        $proposalExpr('locked_reason', 'NULL'),
    ];

    $sql = 'SELECT aws.id AS step_id, aws.proposal_id, aws.step_order, aws.role_name, aws.status AS step_status, aws.remarks AS step_remarks, '
        . implode(', ', $proposalFields)
        . ', c.club_name, u.full_name AS submitted_by FROM approval_workflow_steps aws JOIN proposals p ON p.id = aws.proposal_id JOIN clubs c ON c.id = p.club_id JOIN users u ON u.id = p.'
        . $proposalSubmitterColumn
        . ' WHERE aws.role_name = ? AND p.current_approval_level = ? AND p.overall_status NOT IN ("query_raised", "rejected_pending_response", "locked") AND (aws.approver_user_id = ? OR aws.approver_user_id IS NULL OR aws.approver_user_id = 0) AND aws.status IN ("pending", "resubmitted") ORDER BY aws.step_order ASC, p.created_at DESC';

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('sii', $role, $roleLevel, $user['id']);
    $stmt->execute();
    $pending = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $upcomingSql = 'SELECT aws.id AS step_id, aws.proposal_id, aws.step_order, aws.role_name, aws.status AS step_status, aws.remarks AS step_remarks, '
        . implode(', ', $proposalFields)
        . ', c.club_name, u.full_name AS submitted_by FROM approval_workflow_steps aws JOIN proposals p ON p.id = aws.proposal_id JOIN clubs c ON c.id = p.club_id JOIN users u ON u.id = p.'
        . $proposalSubmitterColumn
        . ' WHERE aws.role_name = ? AND p.current_approval_level <> ? AND p.overall_status NOT IN ("query_raised", "rejected_pending_response", "locked") AND (aws.approver_user_id = ? OR aws.approver_user_id IS NULL OR aws.approver_user_id = 0) AND aws.status IN ("pending", "resubmitted") ORDER BY p.current_approval_level ASC, p.created_at DESC';

    $upcomingStmt = $conn->prepare($upcomingSql);
    $upcomingStmt->bind_param('sii', $role, $roleLevel, $user['id']);
    $upcomingStmt->execute();
    $upcoming = $upcomingStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $upcomingStmt->close();

    foreach ($pending as &$item) {
        $item['workflow_steps'] = workflow_fetch_proposal_steps((int) $item['proposal_id']);
    }
    unset($item);
} elseif (app_is_main_approver_role($role)) {
    $level = app_approval_level_for_role($role);
    $roleColumn = app_role_to_approval_column($role);
    if ($level !== null && $roleColumn) {
        $legacyFields = [
            'p.id',
            'p.proposal_code',
            'p.event_name',
            'p.event_date',
            $proposalExpr('budget_total', '0'),
            $proposalExpr('current_approval_level', '1'),
            $proposalExpr('faculty_mentor_status', '"Pending"'),
            $proposalExpr('president_status', '"Pending"'),
            $proposalExpr('gs_treasurer_status', '"Pending"'),
            $proposalExpr('school_head_status', '"Pending"'),
            $proposalExpr('admin_officer_status', '"Pending"'),
            $proposalExpr('it_team_status', '"Not Required"'),
            $proposalExpr('housekeeping_status', '"Not Required"'),
            $proposalExpr('security_status', '"Not Required"'),
            $proposalExpr('purchase_status', '"Not Required"'),
            $proposalExpr('accounts_status', '"Not Required"'),
            $proposalExpr('dy_registrar_status', '"Not Required"'),
            $proposalExpr('dy_director_status', '"Not Required"'),
            $proposalExpr('director_status', '"Not Required"'),
        ];

        $sql = 'SELECT '
            . implode(', ', $legacyFields)
            . ', c.club_name, u.full_name AS submitted_by FROM proposals p JOIN clubs c ON c.id = p.club_id JOIN users u ON u.id = p.'
            . $proposalSubmitterColumn
            . ' WHERE p.current_approval_level = ? AND p.'
            . $roleColumn
            . ' = "Pending" ORDER BY p.created_at DESC';

        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $level);
        $stmt->execute();
        $pending = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
} else {
    app_flash_set('info', 'Import schema_v2.sql to use the full approval workflow.');
}

layout_render_header('Approvals', $user, 'approvals');
?>
<section class="panel">
    <div class="panel-header">
        <h3>Pending Approvals</h3>
        <span class="badge pending"><?php echo count($pending); ?> items</span>
    </div>

    <?php if (empty($pending)) { ?>
        <p>No proposals are currently waiting for your action.</p>
    <?php } else { ?>
        <div class="timeline">
            <?php foreach ($pending as $item) { ?>
                <div class="timeline-item">
                    <div class="panel-header" style="margin-bottom:8px;">
                        <div>
                            <strong><?php echo htmlspecialchars($item['event_name']); ?></strong><br>
                            <small><?php echo htmlspecialchars($item['club_name']); ?> · <?php echo htmlspecialchars($item['event_date']); ?></small>
                        </div>
                        <span class="badge pending">Level <?php echo (int)($item['current_approval_level'] ?? ($item['step_order'] ?? 0)); ?></span>
                    </div>
                    <p><strong>Submitted by:</strong> <?php echo htmlspecialchars($item['submitted_by']); ?></p>
                    <p><strong>Budget:</strong> ₹<?php echo number_format((float)$item['budget_total'], 2); ?></p>

                    <?php if (app_has_explicit_approval_flow()) { ?>
                        <div class="workflow-chips" style="margin: 12px 0;">
                            <?php foreach (app_get_proposal_role_statuses($item) as $label => $status) { ?>
                                <span class="workflow-chip"><?php echo htmlspecialchars($label); ?>: <span class="badge <?php echo strtolower(str_replace(' ', '_', (string)$status)); ?>"><?php echo htmlspecialchars($status); ?></span></span>
                            <?php } ?>
                        </div>
                    <?php } ?>

                    <?php $formProposalId = (int) ($item['proposal_id'] ?? $item['id'] ?? 0); ?>
                    <form method="post" class="form-grid" style="margin-top:12px;">
                        <input type="hidden" name="proposal_id" value="<?php echo $formProposalId; ?>">
                        <div class="field" style="grid-column: 1 / -1;">
                            <label>Reason / Remarks</label>
                            <textarea name="remarks" placeholder="Required for query or rejection"></textarea>
                        </div>
                        <div class="field" style="justify-content:end;">
                            <label>&nbsp;</label>
                            <div class="inline-actions">
                                <a class="btn" href="view-white-paper.php?proposal_id=<?php echo $formProposalId; ?>">View White Paper</a>
                                <button class="btn success" type="submit" name="action" value="approve">Approve</button>
                                <button class="btn bad" type="submit" name="action" value="reject">Reject</button>
                                <button class="btn warn" type="submit" name="action" value="query">Raise Query</button>
                            </div>
                        </div>
                    </form>
                </div>
            <?php } ?>
        </div>
    <?php } ?>
</section>

<?php if (!empty($upcoming)) { ?>
<section class="panel">
    <div class="panel-header">
        <h3>Upcoming Approvals</h3>
        <span class="badge"><?php echo count($upcoming); ?> queued</span>
    </div>
    <div class="timeline">
        <?php foreach ($upcoming as $item) { ?>
            <div class="timeline-item">
                <strong><?php echo htmlspecialchars($item['event_name']); ?></strong>
                <p><?php echo htmlspecialchars($item['club_name']); ?> · <?php echo htmlspecialchars($item['event_date']); ?></p>
                <p><strong>Current Level:</strong> <?php echo (int) ($item['current_approval_level'] ?? 0); ?> · <strong>Status:</strong> Waiting for earlier approvals</p>
                <p><a class="btn" href="view-white-paper.php?proposal_id=<?php echo (int) ($item['proposal_id'] ?? 0); ?>">View White Paper</a></p>
            </div>
        <?php } ?>
    </div>
</section>
<?php } ?>
<?php layout_render_footer(); ?>
