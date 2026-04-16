<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/layout.php';
require_once __DIR__ . '/inc/workflow.php';

$user = app_require_login();
$role = app_normalize_role((string) $user['role'], $user['sub_role'] ?? null);
$proposalId = (int)($_GET['id'] ?? 0);

if ($proposalId <= 0 || !app_table_exists('proposals')) {
    app_flash_set('error', 'Proposal not found.');
    app_redirect(app_role_dashboard($role));
}

$proposal = null;
$workflow = [];
$queries = [];
$attachments = [];
$budgetItems = [];
$services = [];
$reports = [];
$eventImages = [];
$responses = [];
$rejections = [];

$proposalSubmitterColumn = app_column_exists('proposals', 'submitted_by') ? 'submitted_by' : 'user_id';
$hasSchoolJoin = app_table_exists('schools') && app_column_exists('proposals', 'school_id');
$schoolJoin = $hasSchoolJoin ? ' JOIN schools s ON s.id = p.school_id' : '';
$schoolSelect = $hasSchoolJoin ? ', s.school_name' : ', NULL AS school_name';
$hasVenueJoin = app_table_exists('venues') && app_column_exists('proposals', 'venue_id');
$venueJoin = $hasVenueJoin ? ' LEFT JOIN venues v ON v.id = p.venue_id' : '';
$venueSelect = $hasVenueJoin ? ', v.venue_name' : ', NULL AS venue_name';

if (app_table_exists('approval_workflow_steps')) {
    $stmt = $conn->prepare('SELECT p.*, c.club_name' . $schoolSelect . $venueSelect . ', u.full_name AS submitted_by_name FROM proposals p JOIN clubs c ON c.id = p.club_id' . $schoolJoin . $venueJoin . ' JOIN users u ON u.id = p.' . $proposalSubmitterColumn . ' WHERE p.id = ? LIMIT 1');
    $stmt->bind_param('i', $proposalId);
    $stmt->execute();
    $proposal = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($proposal) {
        $stmt = $conn->prepare('SELECT * FROM approval_workflow_steps WHERE proposal_id = ? ORDER BY step_order ASC');
        $stmt->bind_param('i', $proposalId);
        $stmt->execute();
        $workflow = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $stmt = $conn->prepare('SELECT * FROM queries WHERE proposal_id = ? ORDER BY created_at DESC');
        $stmt->bind_param('i', $proposalId);
        $stmt->execute();
        $queries = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $stmt = $conn->prepare('SELECT * FROM proposal_attachments WHERE proposal_id = ? ORDER BY uploaded_at DESC');
        $stmt->bind_param('i', $proposalId);
        $stmt->execute();
        $attachments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $stmt = $conn->prepare('SELECT * FROM proposal_budget_items WHERE proposal_id = ? ORDER BY id ASC');
        $stmt->bind_param('i', $proposalId);
        $stmt->execute();
        $budgetItems = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $stmt = $conn->prepare('SELECT * FROM proposal_service_requirements WHERE proposal_id = ? ORDER BY id ASC');
        $stmt->bind_param('i', $proposalId);
        $stmt->execute();
        $services = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        if (app_table_exists('event_reports')) {
            $stmt = $conn->prepare('SELECT er.* FROM event_reports er JOIN events e ON e.id = er.event_id WHERE e.proposal_id = ? ORDER BY er.submitted_at DESC');
            $stmt->bind_param('i', $proposalId);
            $stmt->execute();
            $reports = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        }

        if (app_table_exists('event_images')) {
            $stmt = $conn->prepare('SELECT ei.* FROM event_images ei JOIN events e ON e.id = ei.event_id WHERE e.proposal_id = ? ORDER BY ei.uploaded_at DESC');
            $stmt->bind_param('i', $proposalId);
            $stmt->execute();
            $eventImages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        }
    }
} else {
    $stmt = $conn->prepare('SELECT p.*, c.club_name' . $schoolSelect . $venueSelect . ', u.full_name AS submitted_by_name FROM proposals p JOIN clubs c ON c.id = p.club_id' . $schoolJoin . $venueJoin . ' JOIN users u ON u.id = p.' . $proposalSubmitterColumn . ' WHERE p.id = ? LIMIT 1');
    $stmt->bind_param('i', $proposalId);
    $stmt->execute();
    $proposal = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if ($proposal && !isset($proposal['submitted_by']) && isset($proposal['user_id'])) {
    $proposal['submitted_by'] = $proposal['user_id'];
}

if (!$proposal) {
    app_flash_set('error', 'Proposal not found.');
    app_redirect(app_role_dashboard($role));
}

if (app_table_exists('approval_workflow_steps')) {
    $proposal['workflow_steps'] = workflow_fetch_proposal_steps($proposalId);
}

if (app_table_exists('proposal_responses')) {
    $stmt = $conn->prepare('SELECT * FROM proposal_responses WHERE proposal_id = ? ORDER BY created_at DESC');
    $stmt->bind_param('i', $proposalId);
    $stmt->execute();
    $responses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

if (app_table_exists('proposal_rejections')) {
    $stmt = $conn->prepare('SELECT * FROM proposal_rejections WHERE proposal_id = ? ORDER BY created_at DESC');
    $stmt->bind_param('i', $proposalId);
    $stmt->execute();
    $rejections = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['club_head_response'])) {
    try {
        if ($role !== 'club_head' || (int) ($proposal['submitted_by'] ?? 0) !== (int) $user['id']) {
            throw new RuntimeException('Only the club head can resubmit this proposal.');
        }

        $responseText = app_clean_text((string) ($_POST['response_text'] ?? ''));
        if ($responseText === '') {
            throw new RuntimeException('Response text is required.');
        }

        workflow_handle_club_head_response($proposalId, (int) $user['id'], $responseText, null);
        app_flash_set('success', 'Your response has been saved and the proposal was resubmitted.');
        app_redirect('proposal-details.php?id=' . $proposalId);
    } catch (Throwable $e) {
        app_flash_set('error', $e->getMessage());
        app_redirect('proposal-details.php?id=' . $proposalId);
    }
}

layout_render_header('Proposal Details', $user, 'dashboard');
?>
<section class="panel">
    <div class="panel-header">
        <div>
            <h3><?php echo htmlspecialchars($proposal['event_name']); ?></h3>
            <p><?php echo htmlspecialchars($proposal['club_name']); ?> · <?php echo htmlspecialchars($proposal['event_date']); ?></p>
        </div>
        <span class="badge <?php echo htmlspecialchars($proposal['overall_status'] ?? ($proposal['faculty_mentor_status'] ?? 'pending')); ?>"><?php echo htmlspecialchars($proposal['overall_status'] ?? (($proposal['faculty_mentor_status'] ?? '') . ' / ' . ($proposal['program_chair_status'] ?? ''))); ?></span>
    </div>

    <div class="card-grid">
        <div class="card"><p>Submitted By</p><h3><?php echo htmlspecialchars($proposal['submitted_by_name']); ?></h3></div>
        <div class="card"><p>Venue</p><h3><?php echo htmlspecialchars((string) ($proposal['venue_name'] ?? $proposal['event_location'] ?? 'N/A')); ?></h3></div>
        <div class="card"><p>Stage</p><h3><?php echo htmlspecialchars($proposal['current_stage'] ?? 'legacy'); ?></h3></div>
        <div class="card"><p>Priority</p><h3><?php echo htmlspecialchars($proposal['priority_level'] ?? 'normal'); ?></h3></div>
        <div class="card"><p>Budget</p><h3>₹<?php echo number_format((float)($proposal['budget_total'] ?? ($proposal['event_budget'] ?? 0)), 2); ?></h3></div>
        <div class="card"><p>Rejections</p><h3><?php echo (int) ($proposal['rejection_count'] ?? 0); ?></h3></div>
    </div>

    <?php if (!empty($proposal['is_locked'])) { ?>
        <div class="timeline-item" style="margin-top:16px;border-left-color:#8b1e1e;">
            <strong>Locked</strong>
            <p><?php echo htmlspecialchars((string) ($proposal['locked_reason'] ?? 'Maximum rejection attempts reached')); ?></p>
            <p>This white paper is locked. Please create a new white paper.</p>
        </div>
    <?php } elseif ($role === 'club_head' && (int) ($proposal['submitted_by'] ?? 0) === (int) $user['id'] && in_array(($proposal['overall_status'] ?? ''), ['query_raised', 'rejected_pending_response'], true)) { ?>
        <div class="timeline-item" style="margin-top:16px;">
            <strong>Response Required</strong>
            <p>Please address the latest reviewer feedback and resubmit.</p>
            <form method="post" class="form-grid" style="margin-top:12px;">
                <input type="hidden" name="club_head_response" value="1">
                <div class="field field-span"><label>Response Text</label><textarea name="response_text" required placeholder="Explain your changes or clarification"></textarea></div>
                <div class="field" style="justify-content:end;"><label>&nbsp;</label><button class="btn" type="submit">Respond and Resubmit</button></div>
            </form>
        </div>
    <?php } ?>

    <?php if (app_has_explicit_approval_flow()) { ?>
        <div class="workflow-chips" style="margin-top:14px;">
            <?php foreach (app_get_proposal_role_statuses($proposal) as $label => $status) { ?>
                <span class="workflow-chip"><?php echo htmlspecialchars($label); ?>: <span class="badge <?php echo strtolower(str_replace(' ', '_', (string) $status)); ?>"><?php echo htmlspecialchars($status); ?></span></span>
            <?php } ?>
        </div>
    <?php } ?>
</section>

<div class="split">
    <section class="panel">
        <div class="panel-header"><h3>Workflow</h3></div>
        <?php if (empty($workflow)) { ?>
            <p>Workflow details are available after importing schema_v2.sql.</p>
        <?php } else { ?>
            <div class="timeline">
                <?php foreach ($workflow as $step) { ?>
                    <div class="timeline-item">
                        <strong><?php echo htmlspecialchars(app_role_label((string)$step['role_name'])); ?></strong>
                        <p>Status: <span class="badge <?php echo htmlspecialchars($step['status']); ?>"><?php echo htmlspecialchars($step['status']); ?></span></p>
                        <p><?php echo htmlspecialchars((string)($step['remarks'] ?? '')); ?></p>
                    </div>
                <?php } ?>
            </div>
        <?php } ?>
    </section>

    <section class="panel">
        <div class="panel-header"><h3>Attachments and Notes</h3></div>
        <h4>Queries</h4>
        <?php if (empty($queries)) { ?><p>No queries yet.</p><?php } else { ?>
            <div class="timeline">
                <?php foreach ($queries as $query) { ?>
                    <div class="timeline-item">
                        <strong><?php echo htmlspecialchars($query['role_name'] ?? 'Query'); ?></strong>
                        <p><?php echo htmlspecialchars($query['query_text']); ?></p>
                    </div>
                <?php } ?>
            </div>
        <?php } ?>

        <h4 style="margin-top:16px;">Rejection History</h4>
        <?php if (empty($rejections)) { ?><p>No rejections logged.</p><?php } else { ?>
            <div class="timeline">
                <?php foreach ($rejections as $rejection) { ?>
                    <div class="timeline-item">
                        <strong><?php echo htmlspecialchars($rejection['role_name'] ?? 'Reviewer'); ?></strong>
                        <p><?php echo htmlspecialchars($rejection['rejection_reason'] ?? ''); ?></p>
                        <p>Attempt: <?php echo (int) ($rejection['rejection_count'] ?? 0); ?></p>
                    </div>
                <?php } ?>
            </div>
        <?php } ?>

        <h4 style="margin-top:16px;">Club Head Responses</h4>
        <?php if (empty($responses)) { ?><p>No response submitted yet.</p><?php } else { ?>
            <div class="timeline">
                <?php foreach ($responses as $response) { ?>
                    <div class="timeline-item">
                        <strong>Response</strong>
                        <p><?php echo htmlspecialchars($response['response_text']); ?></p>
                        <small><?php echo htmlspecialchars($response['created_at']); ?></small>
                    </div>
                <?php } ?>
            </div>
        <?php } ?>

        <h4 style="margin-top:16px;">Attachments</h4>
        <?php if (empty($attachments)) { ?><p>No attachments uploaded.</p><?php } else { ?>
            <div class="timeline">
                <?php foreach ($attachments as $attachment) { ?>
                    <div class="timeline-item">
                        <strong><?php echo htmlspecialchars($attachment['file_type'] ?? 'Attachment'); ?></strong>
                        <p><?php echo htmlspecialchars($attachment['file_name'] ?? ''); ?></p>
                    </div>
                <?php } ?>
            </div>
        <?php } ?>

        <h4 style="margin-top:16px;">Service Requirements</h4>
        <?php if (empty($services)) { ?><p>No service requirements found.</p><?php } else { ?>
            <div class="chip-grid">
                <?php foreach ($services as $service) { ?>
                    <span class="chip"><?php echo htmlspecialchars($service['service_name']); ?> · <?php echo (int)$service['required'] ? 'Required' : 'Optional'; ?></span>
                <?php } ?>
            </div>
        <?php } ?>
    </section>
</div>

<section class="panel">
    <div class="panel-header"><h3>Post-Event Reports</h3></div>
    <?php if (empty($reports)) { ?><p>No post-event report submitted yet.</p><?php } else { ?>
        <div class="timeline">
            <?php foreach ($reports as $report) { ?>
                <div class="timeline-item">
                    <strong><?php echo htmlspecialchars($report['report_title'] ?? 'Report'); ?></strong>
                    <p><?php echo htmlspecialchars($report['report_description']); ?></p>
                    <p>Participants: <?php echo (int) ($report['participants_count'] ?? 0); ?></p>
                    <p>Status: <span class="badge approved"><?php echo htmlspecialchars($report['status'] ?? 'submitted'); ?></span></p>
                </div>
            <?php } ?>
        </div>
    <?php } ?>
</section>

<section class="panel">
    <div class="panel-header"><h3>Event Images</h3></div>
    <?php if (empty($eventImages)) { ?><p>No event images uploaded.</p><?php } else { ?>
        <div class="card-grid">
            <?php foreach ($eventImages as $image) { ?>
                <div class="card">
                    <p><?php echo htmlspecialchars($image['caption'] ?? ''); ?></p>
                    <h3><?php echo htmlspecialchars(basename((string)$image['image_path'])); ?></h3>
                </div>
            <?php } ?>
        </div>
    <?php } ?>
</section>
<?php layout_render_footer(); ?>
