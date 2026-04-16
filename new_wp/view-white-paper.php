<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/layout.php';
require_once __DIR__ . '/inc/workflow.php';

$user = app_require_login();
$role = app_normalize_role((string) $user['role'], $user['sub_role'] ?? null);
$proposalId = (int) ($_GET['proposal_id'] ?? $_GET['id'] ?? 0);

$canView = app_is_main_approver_role($role)
    || app_is_department_role($role)
    || $role === 'club_head'
    || $role === 'super_admin';

if ($proposalId <= 0 || !$canView) {
    app_flash_set('error', 'White paper not found or access denied.');
    app_redirect(app_role_dashboard($role));
}

function vwp_safe_text(?string $value, string $fallback = 'N/A'): string
{
    $trimmed = trim((string) $value);
    return $trimmed !== '' ? $trimmed : $fallback;
}

function vwp_format_date(?string $date): string
{
    $raw = trim((string) $date);
    if ($raw === '') {
        return 'N/A';
    }

    $ts = strtotime($raw);
    return $ts ? date('d M Y', $ts) : $raw;
}

function vwp_format_time(?string $time): string
{
    $raw = trim((string) $time);
    if ($raw === '') {
        return 'N/A';
    }

    $ts = strtotime($raw);
    return $ts ? date('h:i A', $ts) : $raw;
}

function vwp_bool_label(bool $flag): string
{
    return $flag ? 'Yes' : 'No';
}

$proposalSubmitterColumn = app_column_exists('proposals', 'submitted_by') ? 'submitted_by' : 'user_id';
$hasSchoolJoin = app_table_exists('schools') && app_column_exists('proposals', 'school_id');
$schoolJoin = $hasSchoolJoin ? ' LEFT JOIN schools s ON s.id = p.school_id' : '';
$schoolSelect = $hasSchoolJoin ? ', s.school_name' : ', NULL AS school_name';
$hasVenueJoin = app_table_exists('venues') && app_column_exists('proposals', 'venue_id');
$venueJoin = $hasVenueJoin ? ' LEFT JOIN venues v ON v.id = p.venue_id' : '';
$venueSelect = $hasVenueJoin ? ', v.venue_name' : ', NULL AS venue_name';

$proposal = null;
$proposalSql = 'SELECT p.*, c.club_name' . $schoolSelect . $venueSelect . ', u.full_name AS submitted_by_name FROM proposals p LEFT JOIN clubs c ON c.id = p.club_id' . $schoolJoin . $venueJoin . ' LEFT JOIN users u ON u.id = p.' . $proposalSubmitterColumn . ' WHERE p.id = ? LIMIT 1';
$stmt = $conn->prepare($proposalSql);
$stmt->bind_param('i', $proposalId);
$stmt->execute();
$proposal = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$proposal) {
    app_flash_set('error', 'White paper not found.');
    app_redirect(app_role_dashboard($role));
}

$workflowSteps = [];
if (app_table_exists('approval_workflow_steps')) {
    $stmt = $conn->prepare('SELECT aws.*, u.full_name AS approver_name FROM approval_workflow_steps aws LEFT JOIN users u ON u.id = aws.approver_user_id WHERE aws.proposal_id = ? ORDER BY aws.step_order ASC');
    $stmt->bind_param('i', $proposalId);
    $stmt->execute();
    $workflowSteps = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $proposal['workflow_steps'] = $workflowSteps;
}

$services = [];
if (app_table_exists('proposal_service_requirements')) {
    $stmt = $conn->prepare('SELECT * FROM proposal_service_requirements WHERE proposal_id = ? ORDER BY id ASC');
    $stmt->bind_param('i', $proposalId);
    $stmt->execute();
    $services = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$budgetItems = [];
if (app_table_exists('proposal_budget_items')) {
    if (app_column_exists('proposal_budget_items', 'is_custom')) {
        $stmt = $conn->prepare('SELECT * FROM proposal_budget_items WHERE proposal_id = ? ORDER BY is_custom ASC, id ASC');
    } else {
        $stmt = $conn->prepare('SELECT * FROM proposal_budget_items WHERE proposal_id = ? ORDER BY id ASC');
    }
    $stmt->bind_param('i', $proposalId);
    $stmt->execute();
    $budgetItems = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$spocRows = [];
if (app_table_exists('proposal_spoc')) {
    $stmt = $conn->prepare('SELECT * FROM proposal_spoc WHERE proposal_id = ? ORDER BY id ASC');
    $stmt->bind_param('i', $proposalId);
    $stmt->execute();
    $spocRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$declarations = [];
if (app_table_exists('proposal_declaration')) {
    $stmt = $conn->prepare('SELECT serial_no, student_name, mobile_number, signature_text FROM proposal_declaration WHERE proposal_id = ? ORDER BY serial_no ASC');
    $stmt->bind_param('i', $proposalId);
    $stmt->execute();
    $declarations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} elseif (app_table_exists('proposal_declaration_members')) {
    $stmt = $conn->prepare('SELECT id AS serial_no, member_name AS student_name, mobile_number, signature_text, role_label FROM proposal_declaration_members WHERE proposal_id = ? ORDER BY id ASC');
    $stmt->bind_param('i', $proposalId);
    $stmt->execute();
    $declarations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

if (empty($declarations)) {
    if (trim((string) ($proposal['declaration_head_name'] ?? '')) !== '') {
        $declarations[] = [
            'serial_no' => 1,
            'student_name' => (string) ($proposal['declaration_head_name'] ?? ''),
            'mobile_number' => (string) ($proposal['declaration_head_mobile'] ?? ''),
            'signature_text' => '',
            'role_label' => 'Head',
        ];
    }
    if (trim((string) ($proposal['declaration_co_head_name'] ?? '')) !== '') {
        $declarations[] = [
            'serial_no' => 2,
            'student_name' => (string) ($proposal['declaration_co_head_name'] ?? ''),
            'mobile_number' => (string) ($proposal['declaration_co_head_mobile'] ?? ''),
            'signature_text' => '',
            'role_label' => 'Co-Head',
        ];
    }
}

$attachments = [];
if (app_table_exists('proposal_attachments')) {
    $stmt = $conn->prepare('SELECT * FROM proposal_attachments WHERE proposal_id = ? ORDER BY uploaded_at DESC');
    $stmt->bind_param('i', $proposalId);
    $stmt->execute();
    $attachments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$queries = [];
if (app_table_exists('queries')) {
    $stmt = $conn->prepare('SELECT q.*, u1.full_name AS raised_by_name, u2.full_name AS raised_to_name FROM queries q LEFT JOIN users u1 ON q.raised_by = u1.id LEFT JOIN users u2 ON q.raised_to = u2.id WHERE q.proposal_id = ? ORDER BY q.created_at DESC');
    $stmt->bind_param('i', $proposalId);
    $stmt->execute();
    $queries = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$responses = [];
if (app_table_exists('proposal_responses')) {
    $stmt = $conn->prepare('SELECT pr.*, u.full_name AS responded_by_name FROM proposal_responses pr LEFT JOIN users u ON pr.responded_by = u.id WHERE pr.proposal_id = ? ORDER BY pr.created_at DESC');
    $stmt->bind_param('i', $proposalId);
    $stmt->execute();
    $responses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$rejections = [];
if (app_table_exists('proposal_rejections')) {
    $stmt = $conn->prepare('SELECT prj.*, u.full_name AS rejected_by_name FROM proposal_rejections prj LEFT JOIN users u ON prj.rejected_by = u.id WHERE prj.proposal_id = ? ORDER BY prj.created_at DESC');
    $stmt->bind_param('i', $proposalId);
    $stmt->execute();
    $rejections = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$responsesByQueryId = [];
$orphanResponses = [];
foreach ($responses as $response) {
    $queryId = (int) ($response['query_id'] ?? 0);
    if ($queryId > 0) {
        if (!isset($responsesByQueryId[$queryId])) {
            $responsesByQueryId[$queryId] = [];
        }
        $responsesByQueryId[$queryId][] = $response;
    } else {
        $orphanResponses[] = $response;
    }
}

$serviceMatrix = [
    'Lights and Sound System' => false,
    'Camera' => false,
    'Inauguration Lamp' => false,
    'Executive Lunch/Dinner' => false,
    'Projector' => false,
    'IT Support' => false,
    'Security' => false,
    'Sports Event / Venue' => false,
    'Other Resources' => false,
];
$otherResourcesText = '';

foreach ($services as $service) {
    $name = trim((string) ($service['service_name'] ?? ''));
    $required = (int) ($service['required'] ?? 0) === 1;
    if ($name === '') {
        continue;
    }

    $nameLower = strtolower($name);
    if (str_contains($nameLower, 'lights') || str_contains($nameLower, 'sound')) {
        $serviceMatrix['Lights and Sound System'] = $serviceMatrix['Lights and Sound System'] || $required;
    }
    if (str_contains($nameLower, 'camera')) {
        $serviceMatrix['Camera'] = $serviceMatrix['Camera'] || $required;
    }
    if (str_contains($nameLower, 'lamp')) {
        $serviceMatrix['Inauguration Lamp'] = $serviceMatrix['Inauguration Lamp'] || $required;
    }
    if (str_contains($nameLower, 'lunch') || str_contains($nameLower, 'dinner')) {
        $serviceMatrix['Executive Lunch/Dinner'] = $serviceMatrix['Executive Lunch/Dinner'] || $required;
    }
    if (str_contains($nameLower, 'projector')) {
        $serviceMatrix['Projector'] = $serviceMatrix['Projector'] || $required;
    }
    if (str_contains($nameLower, 'it support') || $nameLower === 'it support') {
        $serviceMatrix['IT Support'] = $serviceMatrix['IT Support'] || $required;
    }
    if (str_contains($nameLower, 'security')) {
        $serviceMatrix['Security'] = $serviceMatrix['Security'] || $required;
    }
    if (str_contains($nameLower, 'sports')) {
        $serviceMatrix['Sports Event / Venue'] = $serviceMatrix['Sports Event / Venue'] || $required;
    }
    if (str_contains($nameLower, 'other')) {
        $serviceMatrix['Other Resources'] = $serviceMatrix['Other Resources'] || $required;
        $remarks = trim((string) ($service['remarks'] ?? ''));
        if ($remarks !== '') {
            $otherResourcesText = $remarks;
        }
    }
}

if ($otherResourcesText === '') {
    $otherResourcesText = trim((string) ($proposal['other_requirements'] ?? ''));
}

$classImpact = [
    'First Year' => ((int) ($proposal['class_first_year'] ?? $proposal['classes_first_year'] ?? 0)) === 1,
    'Second Year' => ((int) ($proposal['class_second_year'] ?? $proposal['classes_second_year'] ?? 0)) === 1,
    'Third Year' => ((int) ($proposal['classes_third_year'] ?? 0)) === 1,
    'Fourth Year' => ((int) ($proposal['classes_fourth_year'] ?? 0)) === 1,
];

$fixedBudgetItems = [
    'Transport',
    'Executive Lunch/Dinner',
    'Water Bottles',
    'Bouquet',
    'Gift/Memento',
    'Certificates',
    'Medals',
    'Others',
];

$budgetGrandTotal = 0.0;
foreach ($budgetItems as $budgetItem) {
    $budgetGrandTotal += (float) ($budgetItem['total'] ?? 0);
}
if ($budgetGrandTotal <= 0) {
    $budgetGrandTotal = (float) ($proposal['budget_total'] ?? $proposal['grand_total'] ?? 0);
}

$statusLabel = app_workflow_step_badge_label((string) ($proposal['overall_status'] ?? $proposal['current_stage'] ?? 'pending'));
$statusClass = strtolower(str_replace(' ', '_', $statusLabel));

$submissionDate = (string) ($proposal['submission_date'] ?? $proposal['created_at'] ?? '');
$eventDate = (string) ($proposal['event_date'] ?? '');
$eventDay = trim((string) ($proposal['event_day'] ?? ''));
if ($eventDay === '' && $eventDate !== '') {
    $dayTs = strtotime($eventDate);
    if ($dayTs) {
        $eventDay = date('l', $dayTs);
    }
}

layout_render_header('View White Paper', $user, 'approvals');
?>
<section class="panel no-print" style="margin-bottom:16px;">
    <div class="panel-header">
        <h3>White Paper Actions</h3>
        <div class="inline-actions">
            <a class="btn secondary" href="javascript:history.back()">Back</a>
            <button class="btn" type="button" onclick="window.print()">Print</button>
            <button class="btn" type="button" onclick="window.print()">Download PDF</button>
        </div>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h3><?php echo htmlspecialchars(vwp_safe_text((string) ($proposal['event_name'] ?? ''))); ?></h3>
            <p><?php echo htmlspecialchars(vwp_safe_text((string) ($proposal['school_name'] ?? ''), 'School Not Set')); ?> · <?php echo htmlspecialchars(vwp_safe_text((string) ($proposal['club_name'] ?? ''), 'Club Not Set')); ?></p>
        </div>
        <span class="badge <?php echo htmlspecialchars($statusClass); ?>"><?php echo htmlspecialchars($statusLabel); ?></span>
    </div>

    <div class="table-wrap">
        <table>
            <tbody>
                <tr><th>Proposal Code</th><td><?php echo htmlspecialchars(vwp_safe_text((string) ($proposal['proposal_code'] ?? ''))); ?></td></tr>
                <tr><th>Event Title</th><td><?php echo htmlspecialchars(vwp_safe_text((string) ($proposal['event_name'] ?? ''))); ?></td></tr>
                <tr><th>Current Status</th><td><?php echo htmlspecialchars($statusLabel); ?></td></tr>
                <tr><th>School</th><td><?php echo htmlspecialchars(vwp_safe_text((string) ($proposal['school_name'] ?? ''))); ?></td></tr>
                <tr><th>Club</th><td><?php echo htmlspecialchars(vwp_safe_text((string) ($proposal['club_name'] ?? ''))); ?></td></tr>
                <tr><th>Submitted By</th><td><?php echo htmlspecialchars(vwp_safe_text((string) ($proposal['submitted_by_name'] ?? ''))); ?></td></tr>
                <tr><th>Submission Date</th><td><?php echo htmlspecialchars(vwp_format_date($submissionDate)); ?></td></tr>
            </tbody>
        </table>
    </div>
</section>

<section class="panel">
    <div class="panel-header"><h3>Event Information</h3></div>
    <div class="table-wrap">
        <table>
            <tbody>
                <tr><th>Event Name</th><td><?php echo htmlspecialchars(vwp_safe_text((string) ($proposal['event_name'] ?? ''))); ?></td></tr>
                <tr><th>Activity / Event Planned</th><td><?php echo htmlspecialchars(vwp_safe_text((string) ($proposal['event_type'] ?? $proposal['event_name'] ?? ''))); ?></td></tr>
                <tr><th>Event Date</th><td><?php echo htmlspecialchars(vwp_format_date($eventDate)); ?></td></tr>
                <tr><th>Day</th><td><?php echo htmlspecialchars(vwp_safe_text($eventDay)); ?></td></tr>
                <tr><th>Start Time</th><td><?php echo htmlspecialchars(vwp_format_time((string) ($proposal['start_time'] ?? ''))); ?></td></tr>
                <tr><th>End Time</th><td><?php echo htmlspecialchars(vwp_format_time((string) ($proposal['end_time'] ?? ''))); ?></td></tr>
                <tr><th>Venue</th><td><?php echo htmlspecialchars(vwp_safe_text((string) ($proposal['venue_name'] ?? ''))); ?></td></tr>
                <tr><th>Event Mode</th><td><?php echo htmlspecialchars(vwp_safe_text((string) ($proposal['event_mode'] ?? ''), 'offline')); ?></td></tr>
                <tr><th>Expected Participants</th><td><?php echo htmlspecialchars((string) ((int) ($proposal['expected_participants'] ?? 0))); ?></td></tr>
            </tbody>
        </table>
    </div>
</section>

<section class="panel">
    <div class="panel-header"><h3>Academic Impact / Class Impact</h3></div>
    <div class="chip-grid" style="margin-bottom:14px;">
        <?php foreach ($classImpact as $classLabel => $isAffected) { ?>
            <span class="chip"><?php echo htmlspecialchars($classLabel); ?>: <span class="badge <?php echo $isAffected ? 'approved' : 'not_required'; ?>"><?php echo $isAffected ? 'Preponed' : 'No'; ?></span></span>
        <?php } ?>
    </div>
    <p><strong>Additional Remarks:</strong> <?php echo htmlspecialchars(vwp_safe_text((string) ($proposal['class_disruption_details'] ?? ''), 'No additional class-impact remarks provided.')); ?></p>
</section>

<section class="panel">
    <div class="panel-header"><h3>Event Details</h3></div>
    <div class="timeline">
        <div class="timeline-item">
            <strong>Event Objective</strong>
            <p><?php echo nl2br(htmlspecialchars(vwp_safe_text((string) ($proposal['event_objective'] ?? ''), (string) ($proposal['event_details'] ?? 'No objective provided.')))); ?></p>
        </div>
        <div class="timeline-item">
            <strong>Event Description / Details</strong>
            <p><?php echo nl2br(htmlspecialchars(vwp_safe_text((string) ($proposal['event_description'] ?? $proposal['event_details'] ?? ''), 'No description provided.'))); ?></p>
        </div>
        <div class="timeline-item">
            <strong>Schedule Summary</strong>
            <p><?php echo nl2br(htmlspecialchars(vwp_safe_text((string) ($proposal['minute_to_minute_schedule'] ?? ''), 'No schedule summary provided.'))); ?></p>
        </div>
    </div>
</section>

<section class="panel">
    <div class="panel-header"><h3>Service Requirements</h3></div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Service</th><th>Required</th></tr>
            </thead>
            <tbody>
                <?php foreach ($serviceMatrix as $serviceLabel => $isRequired) { ?>
                    <tr>
                        <td><?php echo htmlspecialchars($serviceLabel); ?></td>
                        <td><span class="badge <?php echo $isRequired ? 'approved' : 'not_required'; ?>"><?php echo $isRequired ? 'Yes' : 'No'; ?></span></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
    <p style="margin-top:12px;"><strong>Other Resources Text:</strong> <?php echo htmlspecialchars(vwp_safe_text($otherResourcesText, 'None')); ?></p>
</section>

<section class="panel">
    <div class="panel-header"><h3>Budget Requirements</h3></div>
    <?php if (empty($budgetItems)) { ?>
        <p>No budget item rows were found for this white paper.</p>
    <?php } else { ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Quantity</th>
                        <th>Rate</th>
                        <th>Total</th>
                        <th>Type</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($budgetItems as $budgetItem) {
                        $itemName = (string) ($budgetItem['item_name'] ?? 'Budget Item');
                        $isCustom = app_column_exists('proposal_budget_items', 'is_custom')
                            ? ((int) ($budgetItem['is_custom'] ?? 0) === 1)
                            : !in_array($itemName, $fixedBudgetItems, true);
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($itemName); ?></td>
                            <td><?php echo htmlspecialchars((string) ($budgetItem['quantity'] ?? 0)); ?></td>
                            <td>₹<?php echo number_format((float) ($budgetItem['rate'] ?? 0), 2); ?></td>
                            <td>₹<?php echo number_format((float) ($budgetItem['total'] ?? 0), 2); ?></td>
                            <td><span class="badge <?php echo $isCustom ? 'query_raised' : 'submitted'; ?>"><?php echo $isCustom ? 'Custom' : 'Fixed'; ?></span></td>
                        </tr>
                    <?php } ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="3">Grand Total</th>
                        <th>₹<?php echo number_format($budgetGrandTotal, 2); ?></th>
                        <th></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php } ?>
</section>

<section class="panel">
    <div class="panel-header"><h3>SPOC Details</h3></div>
    <?php if (empty($spocRows)) { ?>
        <p>No SPOC row found for this proposal.</p>
    <?php } else { ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>Name</th><th>Mobile</th><th>Email</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($spocRows as $spocRow) { ?>
                        <tr>
                            <td><?php echo htmlspecialchars(vwp_safe_text((string) ($spocRow['spoc_name'] ?? ''))); ?></td>
                            <td><?php echo htmlspecialchars(vwp_safe_text((string) ($spocRow['spoc_phone'] ?? $spocRow['spoc_mobile'] ?? ''))); ?></td>
                            <td><?php echo htmlspecialchars(vwp_safe_text((string) ($spocRow['spoc_email'] ?? ''))); ?></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    <?php } ?>
</section>

<section class="panel">
    <div class="panel-header"><h3>Declaration</h3></div>
    <?php if (empty($declarations)) { ?>
        <p>No declaration members were found.</p>
    <?php } else { ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>#</th><th>Name</th><th>Mobile</th><th>Role</th><th>Signature</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($declarations as $declaration) { ?>
                        <tr>
                            <td><?php echo (int) ($declaration['serial_no'] ?? 0); ?></td>
                            <td><?php echo htmlspecialchars(vwp_safe_text((string) ($declaration['student_name'] ?? ''))); ?></td>
                            <td><?php echo htmlspecialchars(vwp_safe_text((string) ($declaration['mobile_number'] ?? ''))); ?></td>
                            <td><?php echo htmlspecialchars(vwp_safe_text((string) ($declaration['role_label'] ?? ''), '-')); ?></td>
                            <td><?php echo htmlspecialchars(vwp_safe_text((string) ($declaration['signature_text'] ?? ''), '-')); ?></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    <?php } ?>
    <p style="margin-top:12px;"><strong>Declaration Accepted:</strong> <?php echo vwp_bool_label(((int) ($proposal['declaration_agreed'] ?? 0)) === 1); ?></p>
</section>

<section class="panel">
    <div class="panel-header"><h3>Attachments</h3></div>
    <?php if (empty($attachments)) { ?>
        <p>No attachments uploaded.</p>
    <?php } else { ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>File Name</th><th>Type</th><th>Uploaded At</th><th>Action</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($attachments as $attachment) { ?>
                        <tr>
                            <td><?php echo htmlspecialchars(vwp_safe_text((string) ($attachment['file_name'] ?? ''))); ?></td>
                            <td><?php echo htmlspecialchars(vwp_safe_text((string) ($attachment['file_type'] ?? ''), '-')); ?></td>
                            <td><?php echo htmlspecialchars(vwp_format_date((string) ($attachment['uploaded_at'] ?? ''))); ?></td>
                            <td>
                                <?php if (trim((string) ($attachment['file_path'] ?? '')) !== '') { ?>
                                    <a class="btn secondary" target="_blank" rel="noopener" href="<?php echo htmlspecialchars((string) $attachment['file_path']); ?>">View / Download</a>
                                <?php } else { ?>
                                    -
                                <?php } ?>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    <?php } ?>
</section>

<section class="panel">
    <div class="panel-header"><h3>Approval Workflow</h3></div>
    <?php if (empty($workflowSteps)) { ?>
        <p>No workflow rows found.</p>
    <?php } else { ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>Step</th><th>Role</th><th>Approver</th><th>Status</th><th>Acted At</th><th>Remarks</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($workflowSteps as $workflowStep) {
                        $workflowStatusLabel = app_workflow_step_badge_label((string) ($workflowStep['status'] ?? 'pending'));
                        $workflowStatusClass = strtolower(str_replace(' ', '_', $workflowStatusLabel));
                        ?>
                        <tr>
                            <td><?php echo (int) ($workflowStep['step_order'] ?? 0); ?></td>
                            <td><?php echo htmlspecialchars(app_role_label((string) ($workflowStep['role_name'] ?? ''))); ?></td>
                            <td><?php echo htmlspecialchars(vwp_safe_text((string) ($workflowStep['approver_name'] ?? ''), 'Unassigned')); ?></td>
                            <td><span class="badge <?php echo htmlspecialchars($workflowStatusClass); ?>"><?php echo htmlspecialchars($workflowStatusLabel); ?></span></td>
                            <td><?php echo htmlspecialchars(vwp_format_date((string) ($workflowStep['acted_at'] ?? ''))); ?></td>
                            <td><?php echo htmlspecialchars(vwp_safe_text((string) ($workflowStep['remarks'] ?? ''), '-')); ?></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    <?php } ?>
</section>

<section class="panel" style="margin-bottom:12px;">
    <div class="panel-header"><h3>Query / Rejection History</h3></div>

    <h4 style="margin-bottom:10px;">Query History</h4>
    <?php if (empty($queries)) { ?>
        <p>No query history found.</p>
    <?php } else { ?>
        <div class="timeline">
            <?php foreach ($queries as $query) {
                $qId = (int) ($query['id'] ?? 0);
                $linkedResponses = $responsesByQueryId[$qId] ?? [];
                ?>
                <div class="timeline-item">
                    <strong><?php echo htmlspecialchars(vwp_safe_text((string) ($query['raised_by_name'] ?? ''), app_role_label((string) ($query['role_name'] ?? 'Reviewer')))); ?></strong>
                    <p><strong>Raised To:</strong> <?php echo htmlspecialchars(vwp_safe_text((string) ($query['raised_to_name'] ?? ''), 'Club Head')); ?></p>
                    <p><strong>When:</strong> <?php echo htmlspecialchars(vwp_format_date((string) ($query['created_at'] ?? ''))); ?></p>
                    <p><strong>Reason / Query:</strong> <?php echo nl2br(htmlspecialchars(vwp_safe_text((string) ($query['query_text'] ?? ''), '-'))); ?></p>
                    <p><strong>Status:</strong> <span class="badge <?php echo htmlspecialchars((string) ($query['status'] ?? 'open')); ?>"><?php echo htmlspecialchars((string) ($query['status'] ?? 'open')); ?></span></p>
                    <?php if (!empty($query['club_response'])) { ?><p><strong>Club Response:</strong> <?php echo nl2br(htmlspecialchars((string) $query['club_response'])); ?></p><?php } ?>

                    <?php if (!empty($linkedResponses)) { ?>
                        <div style="margin-top:8px;">
                            <strong>Response Log</strong>
                            <?php foreach ($linkedResponses as $linkedResponse) { ?>
                                <p><?php echo htmlspecialchars(vwp_format_date((string) ($linkedResponse['created_at'] ?? ''))); ?> · <?php echo htmlspecialchars(vwp_safe_text((string) ($linkedResponse['responded_by_name'] ?? ''), 'Club Head')); ?>: <?php echo nl2br(htmlspecialchars(vwp_safe_text((string) ($linkedResponse['response_text'] ?? ''), '-'))); ?></p>
                            <?php } ?>
                        </div>
                    <?php } ?>
                </div>
            <?php } ?>
        </div>
    <?php } ?>

    <h4 style="margin:16px 0 10px;">Rejection History</h4>
    <?php if (empty($rejections)) { ?>
        <p>No rejection history found.</p>
    <?php } else { ?>
        <div class="timeline">
            <?php foreach ($rejections as $rejection) { ?>
                <div class="timeline-item">
                    <strong><?php echo htmlspecialchars(vwp_safe_text((string) ($rejection['rejected_by_name'] ?? ''), app_role_label((string) ($rejection['role_name'] ?? 'Reviewer')))); ?></strong>
                    <p><strong>When:</strong> <?php echo htmlspecialchars(vwp_format_date((string) ($rejection['created_at'] ?? ''))); ?></p>
                    <p><strong>Reason:</strong> <?php echo nl2br(htmlspecialchars(vwp_safe_text((string) ($rejection['rejection_reason'] ?? ''), '-'))); ?></p>
                    <p><strong>Count:</strong> <?php echo (int) ($rejection['rejection_count'] ?? 0); ?></p>
                </div>
            <?php } ?>
        </div>
    <?php } ?>

    <?php if (!empty($orphanResponses)) { ?>
        <h4 style="margin:16px 0 10px;">Additional Responses</h4>
        <div class="timeline">
            <?php foreach ($orphanResponses as $orphanResponse) { ?>
                <div class="timeline-item">
                    <strong><?php echo htmlspecialchars(vwp_safe_text((string) ($orphanResponse['responded_by_name'] ?? ''), 'Club Head')); ?></strong>
                    <p><strong>When:</strong> <?php echo htmlspecialchars(vwp_format_date((string) ($orphanResponse['created_at'] ?? ''))); ?></p>
                    <p><?php echo nl2br(htmlspecialchars(vwp_safe_text((string) ($orphanResponse['response_text'] ?? ''), '-'))); ?></p>
                </div>
            <?php } ?>
        </div>
    <?php } ?>
</section>

<style>
@media print {
    .no-print,
    .topbar,
    .sidebar,
    .sidebar-overlay,
    .app-footer {
        display: none !important;
    }

    .main-content {
        margin-left: 0 !important;
        padding: 0 !important;
    }

    .panel {
        break-inside: avoid;
        box-shadow: none !important;
    }
}
</style>
<?php layout_render_footer(); ?>
