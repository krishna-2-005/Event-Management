<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/layout.php';
require_once __DIR__ . '/inc/workflow.php';

$user = app_require_login();
$role = app_normalize_role((string) $user['role'], $user['sub_role'] ?? null);

if ($role !== 'club_head') {
    app_flash_set('error', 'Only club heads can submit proposals.');
    app_redirect(app_role_dashboard($role));
}

$schools = [];
$clubs = [];
$venues = [];

if (app_table_exists('schools')) {
    $result = $conn->query('SELECT id, school_name FROM schools ORDER BY school_name ASC');
    if ($result) {
        $schools = $result->fetch_all(MYSQLI_ASSOC);
    }
}

if (app_table_exists('clubs')) {
    $stmt = $conn->prepare('SELECT id, club_name FROM clubs WHERE id = ? OR club_head_user_id = ? ORDER BY club_name ASC');
    $stmt->bind_param('ii', $user['club_id'], $user['id']);
    $stmt->execute();
    $clubs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

if (app_table_exists('venues')) {
    $result = $conn->query('SELECT id, venue_name, capacity FROM venues WHERE status = "available" ORDER BY venue_name ASC');
    if ($result) {
        $venues = $result->fetch_all(MYSQLI_ASSOC);
    }
}

$budgetRows = [
    'transport' => 'Transport',
    'executive_lunch_dinner' => 'Executive Lunch/Dinner',
    'water_bottles' => 'Water Bottles',
    'bouquet' => 'Bouquet',
    'gift_memento' => 'Gift/Memento',
    'certificates' => 'Certificates',
    'medals' => 'Medals',
    'others' => 'Others'
]
;

$serviceRows = [
    'lights_sound_system' => 'Lights and Sound System',
    'camera' => 'Camera',
    'inauguration_lamp' => 'Inauguration Lamp',
    'executive_lunch_dinner' => 'Executive lunch/dinner',
    'projector' => 'Projector',
    'it_support' => 'IT Support',
    'security_required' => 'Security',
    'sports_event' => 'Sports Event / Venue',
    'other_resources' => 'Other Resources'
];

function submit_proposal_checked(string $key): int
{
    return isset($_POST[$key]) ? 1 : 0;
}

function submit_proposal_to_float($value): float
{
    $normalized = preg_replace('/[^0-9.]/', '', (string) $value);
    return $normalized === '' ? 0.0 : (float) $normalized;
}

function submit_proposal_clash_exists(int $venueId, string $eventDate, string $startTime, string $endTime): bool
{
    global $conn;

    if ($venueId <= 0) {
        return false;
    }

    $sql = 'SELECT COUNT(*) AS c FROM resource_bookings WHERE venue_id = ? AND booking_date = ? AND booking_status IN ("tentative", "confirmed", "ongoing") AND NOT (end_time <= ? OR start_time >= ?)';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('isss', $venueId, $eventDate, $startTime, $endTime);
    $stmt->execute();
    $count = (int) ($stmt->get_result()->fetch_assoc()['c'] ?? 0);
    $stmt->close();

    if ($count > 0) {
        return true;
    }

    if (!app_table_exists('events')) {
        return false;
    }

    $stmt = $conn->prepare('SELECT COUNT(*) AS c FROM events WHERE venue_id = ? AND event_date = ? AND event_status IN ("upcoming", "ongoing") AND NOT (end_time <= ? OR start_time >= ?)');
    $stmt->bind_param('isss', $venueId, $eventDate, $startTime, $endTime);
    $stmt->execute();
    $count = (int) ($stmt->get_result()->fetch_assoc()['c'] ?? 0);
    $stmt->close();

    return $count > 0;
}

function submit_proposal_insert_declarations(int $proposalId, array $members): void
{
    global $conn;

    foreach ($members as $index => $member) {
        $serialNo = $index + 1;
        $name = trim((string) ($member['name'] ?? ''));
        $mobile = trim((string) ($member['mobile'] ?? ''));
        $signature = trim((string) ($member['signature'] ?? ''));

        if ($name === '' && $mobile === '') {
            continue;
        }

        if (app_table_exists('proposal_declaration')) {
            $stmt = $conn->prepare('INSERT INTO proposal_declaration (proposal_id, serial_no, student_name, mobile_number, signature_text) VALUES (?, ?, ?, ?, ?)');
            $stmt->bind_param('iisss', $proposalId, $serialNo, $name, $mobile, $signature);
            $stmt->execute();
            $stmt->close();
        }

        if (app_table_exists('proposal_declaration_members')) {
            $roleLabel = $serialNo === 1 ? 'Head' : 'Co-Head';
            $stmt = $conn->prepare('INSERT INTO proposal_declaration_members (proposal_id, member_name, mobile_number, role_label, signature_text) VALUES (?, ?, ?, ?, ?)');
            $stmt->bind_param('issss', $proposalId, $name, $mobile, $roleLabel, $signature);
            $stmt->execute();
            $stmt->close();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $schoolId = (int) ($_POST['school_id'] ?? ($user['school_id'] ?? 0));
    $clubId = (int) ($_POST['club_id'] ?? ($user['club_id'] ?? 0));
    $submissionDate = date('Y-m-d');
    $eventName = app_clean_text((string) ($_POST['event_name'] ?? ''));
    $eventDate = (string) ($_POST['event_date'] ?? '');
    $eventDay = $eventDate !== '' ? date('l', strtotime($eventDate)) : '';
    $startTime = (string) ($_POST['start_time'] ?? '');
    $endTime = (string) ($_POST['end_time'] ?? '');
    $venueId = (int) ($_POST['venue_id'] ?? 0);
    $eventDetails = app_clean_text((string) ($_POST['event_details'] ?? ''));
    $classDisruptionDetails = app_clean_text((string) ($_POST['class_disruption_details'] ?? ''));
    $classFirst = submit_proposal_checked('class_first_year');
    $classSecond = submit_proposal_checked('class_second_year');
    $classThird = submit_proposal_checked('class_third_year');
    $classFourth = submit_proposal_checked('class_fourth_year');
    $spocName = app_clean_text((string) ($_POST['spoc_name'] ?? ''));
    $spocMobile = app_clean_text((string) ($_POST['spoc_mobile'] ?? ''));
    $spocEmail = app_clean_text((string) ($_POST['spoc_email'] ?? ''));
    $headName = app_clean_text((string) ($_POST['declaration_head_name'] ?? ''));
    $headMobile = app_clean_text((string) ($_POST['declaration_head_mobile'] ?? ''));
    $coHeadName = app_clean_text((string) ($_POST['declaration_co_head_name'] ?? ''));
    $coHeadMobile = app_clean_text((string) ($_POST['declaration_co_head_mobile'] ?? ''));
    $headSignature = app_clean_text((string) ($_POST['declaration_head_signature'] ?? ''));
    $coHeadSignature = app_clean_text((string) ($_POST['declaration_co_head_signature'] ?? ''));
    $declarationAgreed = submit_proposal_checked('declaration_agreed');
    $otherResources = app_clean_text((string) ($_POST['other_resources'] ?? ''));
    $customItemNames = $_POST['custom_item_name'] ?? [];
    $customItemQty = $_POST['custom_item_qty'] ?? [];
    $customItemRate = $_POST['custom_item_rate'] ?? [];

    if ($schoolId <= 0 || $clubId <= 0 || $eventName === '' || $eventDate === '' || $startTime === '' || $endTime === '' || $venueId <= 0) {
        app_flash_set('error', 'Please complete the required proposal fields.');
        app_redirect('submit-proposal.php');
    }

    if (!$declarationAgreed) {
        app_flash_set('error', 'You must accept the declaration before submitting.');
        app_redirect('submit-proposal.php');
    }

    if ($headName === '' || $headMobile === '' || $coHeadName === '' || $coHeadMobile === '') {
        app_flash_set('error', 'Please fill both declaration member details.');
        app_redirect('submit-proposal.php');
    }

    $spocKeyName = mb_strtolower(trim($spocName));
    $spocKeyMobile = trim($spocMobile);
    $headKeyName = mb_strtolower(trim($headName));
    $coHeadKeyName = mb_strtolower(trim($coHeadName));
    if ($spocKeyName !== '' && ($spocKeyName === $headKeyName || $spocKeyName === $coHeadKeyName || $spocKeyMobile === trim($headMobile) || $spocKeyMobile === trim($coHeadMobile))) {
        app_flash_set('error', 'SPOC must be different from the Head and Co-Head.');
        app_redirect('submit-proposal.php');
    }

    if ($startTime >= $endTime) {
        app_flash_set('error', 'Closing time must be later than starting time.');
        app_redirect('submit-proposal.php');
    }

    if (app_table_exists('blocked_dates')) {
        $stmt = $conn->prepare('SELECT id FROM blocked_dates WHERE block_date = ? LIMIT 1');
        $stmt->bind_param('s', $eventDate);
        $stmt->execute();
        $blocked = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($blocked) {
            app_flash_set('error', 'Selected event date is blocked.');
            app_redirect('submit-proposal.php');
        }
    }

    if (submit_proposal_clash_exists($venueId, $eventDate, $startTime, $endTime)) {
        app_flash_set('error', 'Selected venue already has a clash for that date and time.');
        app_redirect('submit-proposal.php');
    }

    $budgetTotals = [];
    $budgetGrandTotal = 0.0;
    foreach ($budgetRows as $key => $label) {
        $quantity = max(0, (int) ($_POST['budget_quantity'][$key] ?? 0));
        $rate = submit_proposal_to_float($_POST['budget_rate'][$key] ?? 0);
        $total = $quantity * $rate;
        $budgetTotals[$key] = compact('quantity', 'rate', 'total', 'label');
        $budgetGrandTotal += $total;
    }

    $customBudgetRows = [];
    foreach ($customItemNames as $index => $nameRaw) {
        $name = app_clean_text((string) $nameRaw);
        if ($name === '') {
            continue;
        }

        $quantity = max(0.0, submit_proposal_to_float($customItemQty[$index] ?? 0));
        $rate = max(0.0, submit_proposal_to_float($customItemRate[$index] ?? 0));
        $total = $quantity * $rate;
        $customBudgetRows[] = [
            'item_name' => $name,
            'quantity' => $quantity,
            'rate' => $rate,
            'total' => $total,
        ];
        $budgetGrandTotal += $total;
    }

    $selectedServiceLabels = [];
    foreach ($serviceRows as $key => $label) {
        if ($key === 'other_resources') {
            if ($otherResources !== '') {
                $selectedServiceLabels[] = $otherResources;
            }
            continue;
        }
        if (submit_proposal_checked($key)) {
            $selectedServiceLabels[] = $label;
        }
    }

    $selectedClassText = [];
    if ($classFirst) {
        $selectedClassText[] = 'First Year';
    }
    if ($classSecond) {
        $selectedClassText[] = 'Second Year';
    }
    if ($classThird) {
        $selectedClassText[] = 'Third Year';
    }
    if ($classFourth) {
        $selectedClassText[] = 'Fourth Year';
    }

    $conn->begin_transaction();
    try {
        $proposalCode = 'TMP';
        $stage = 'faculty_mentor';
        $status = 'submitted';
        $priority = 'normal';
        $classDetails = implode(', ', $selectedClassText);

        $stmt = $conn->prepare('INSERT INTO proposals (proposal_code, submitted_by, club_id, school_id, submission_date, event_name, event_date, event_day, start_time, end_time, venue_id, class_first_year, class_second_year, classes_first_year, classes_second_year, classes_third_year, classes_fourth_year, class_disruption_details, event_description, event_details, budget_total, grand_total, current_stage, overall_status, priority_level) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('siiissssssiiiiiiisssddsss', $proposalCode, $user['id'], $clubId, $schoolId, $submissionDate, $eventName, $eventDate, $eventDay, $startTime, $endTime, $venueId, $classFirst, $classSecond, $classFirst, $classSecond, $classThird, $classFourth, $classDetails, $eventDetails, $eventDetails, $budgetGrandTotal, $budgetGrandTotal, $stage, $status, $priority);
        $stmt->execute();
        $proposalId = (int) $stmt->insert_id;
        $stmt->close();

        $proposalCode = app_make_proposal_code($proposalId);
        $stmt = $conn->prepare('UPDATE proposals SET proposal_code = ?, budget_total = ?, grand_total = ? WHERE id = ?');
        $stmt->bind_param('sddi', $proposalCode, $budgetGrandTotal, $budgetGrandTotal, $proposalId);
        $stmt->execute();
        $stmt->close();

        if (app_has_explicit_approval_flow()) {
            app_initialize_explicit_proposal_flow($proposalId);
        }

        if (app_table_exists('proposal_service_requirements')) {
            foreach ($serviceRows as $key => $label) {
                $required = $key === 'other_resources' ? ($otherResources !== '' ? 1 : 0) : submit_proposal_checked($key);
                $remarks = $key === 'other_resources' ? $otherResources : null;
                $stmt = $conn->prepare('INSERT INTO proposal_service_requirements (proposal_id, service_name, required, remarks) VALUES (?, ?, ?, ?)');
                $stmt->bind_param('isis', $proposalId, $label, $required, $remarks);
                $stmt->execute();
                $stmt->close();
            }
        }

        if (app_table_exists('approval_workflow_steps')) {
            workflow_seed_proposal_steps($proposalId);
        }

        if (app_table_exists('proposal_budget_items')) {
            $hasCustomFlag = app_column_exists('proposal_budget_items', 'is_custom');

            foreach ($budgetRows as $key => $label) {
                $quantity = (float) $budgetTotals[$key]['quantity'];
                $rate = $budgetTotals[$key]['rate'];
                $total = $budgetTotals[$key]['total'];

                if ($hasCustomFlag) {
                    $isCustom = 0;
                    $stmt = $conn->prepare('INSERT INTO proposal_budget_items (proposal_id, item_name, quantity, rate, total, is_custom) VALUES (?, ?, ?, ?, ?, ?)');
                    $stmt->bind_param('isdddi', $proposalId, $label, $quantity, $rate, $total, $isCustom);
                } else {
                    $stmt = $conn->prepare('INSERT INTO proposal_budget_items (proposal_id, item_name, quantity, rate, total) VALUES (?, ?, ?, ?, ?)');
                    $stmt->bind_param('isddd', $proposalId, $label, $quantity, $rate, $total);
                }

                $stmt->execute();
                $stmt->close();
            }

            foreach ($customBudgetRows as $customBudgetRow) {
                $itemName = $customBudgetRow['item_name'];
                $quantity = (float) $customBudgetRow['quantity'];
                $rate = (float) $customBudgetRow['rate'];
                $total = (float) $customBudgetRow['total'];

                if ($hasCustomFlag) {
                    $isCustom = 1;
                    $stmt = $conn->prepare('INSERT INTO proposal_budget_items (proposal_id, item_name, quantity, rate, total, is_custom) VALUES (?, ?, ?, ?, ?, ?)');
                    $stmt->bind_param('isdddi', $proposalId, $itemName, $quantity, $rate, $total, $isCustom);
                } else {
                    $stmt = $conn->prepare('INSERT INTO proposal_budget_items (proposal_id, item_name, quantity, rate, total) VALUES (?, ?, ?, ?, ?)');
                    $stmt->bind_param('isddd', $proposalId, $itemName, $quantity, $rate, $total);
                }

                $stmt->execute();
                $stmt->close();
            }
        }

        if (app_table_exists('proposal_spoc') && $spocName !== '' && $spocMobile !== '') {
            $stmt = $conn->prepare('INSERT INTO proposal_spoc (proposal_id, spoc_name, spoc_phone, spoc_email) VALUES (?, ?, ?, ?)');
            $stmt->bind_param('isss', $proposalId, $spocName, $spocMobile, $spocEmail);
            $stmt->execute();
            $stmt->close();
        }

        submit_proposal_insert_declarations($proposalId, [
            ['name' => $headName, 'mobile' => $headMobile, 'signature' => $headSignature],
            ['name' => $coHeadName, 'mobile' => $coHeadMobile, 'signature' => $coHeadSignature],
        ]);

        if (app_table_exists('resource_bookings')) {
            $stmt = $conn->prepare('INSERT INTO resource_bookings (proposal_id, venue_id, booking_date, start_time, end_time, booking_status, priority_level) VALUES (?, ?, ?, ?, ?, "tentative", "normal")');
            $stmt->bind_param('iisss', $proposalId, $venueId, $eventDate, $startTime, $endTime);
            $stmt->execute();
            $stmt->close();
        }

        if (app_table_exists('approval_logs')) {
            workflow_log_action($proposalId, (int) $user['id'], 'club_head', 'submitted', 'Proposal submitted by club head.');
        }

        $conn->commit();
        app_flash_set('success', 'Proposal submitted successfully.');
        app_redirect('my-proposals.php');
    } catch (Throwable $e) {
        $conn->rollback();
        app_flash_set('error', $e->getMessage());
        app_redirect('submit-proposal.php');
    }
}

$formSchoolId = (int) ($_POST['school_id'] ?? ($user['school_id'] ?? 0));
$formClubId = (int) ($_POST['club_id'] ?? ($user['club_id'] ?? 0));
$formVenueId = (int) ($_POST['venue_id'] ?? 0);
$formEventDate = (string) ($_POST['event_date'] ?? '');
$formEventDay = $formEventDate !== '' ? date('l', strtotime($formEventDate)) : '';
$formSubmissionDate = date('Y-m-d');

layout_render_header('Submit White Paper', $user, 'submit_proposal');
?>
<section class="proposal-shell">
    <div class="proposal-modal">
        <div class="proposal-modal-head">
            <div>
                <p class="proposal-kicker">New Event Proposal</p>
                <h3>Digital White Paper Approval Form</h3>
            </div>
            <span class="badge">Popup layout preserved</span>
        </div>

        <form method="post" class="proposal-form" id="proposal-form">
            <section class="proposal-section">
                <h4>Event Proposal Form</h4>
                <div class="proposal-grid">
                    <div class="field"><label>Name of the School</label><select name="school_id" required><?php foreach ($schools as $school) { ?><option value="<?php echo (int) $school['id']; ?>" <?php echo $formSchoolId === (int) $school['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($school['school_name']); ?></option><?php } ?></select></div>
                    <div class="field"><label>Date of Submission</label><input type="date" value="<?php echo htmlspecialchars($formSubmissionDate); ?>" readonly></div>
                    <div class="field"><label>Name of the Club/Committee</label><select name="club_id" required><?php foreach ($clubs as $club) { ?><option value="<?php echo (int) $club['id']; ?>" <?php echo $formClubId === (int) $club['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($club['club_name']); ?></option><?php } ?></select></div>
                    <div class="field"><label>Activity/Event Planned</label><input name="event_name" value="<?php echo htmlspecialchars((string) ($_POST['event_name'] ?? '')); ?>" required></div>
                    <div class="field"><label>Date and Day of the Program</label><div class="inline-row"><input type="date" name="event_date" id="event-date" value="<?php echo htmlspecialchars($formEventDate); ?>" required><input type="text" id="event-day" value="<?php echo htmlspecialchars($formEventDay); ?>" placeholder="Day" readonly></div></div>
                    <div class="field"><label>Time</label><div class="inline-row"><input type="time" name="start_time" value="<?php echo htmlspecialchars((string) ($_POST['start_time'] ?? '')); ?>" required><input type="time" name="end_time" value="<?php echo htmlspecialchars((string) ($_POST['end_time'] ?? '')); ?>" required></div></div>
                    <div class="field"><label>Venue</label><select name="venue_id" required><option value="">Select Venue</option><?php foreach ($venues as $venue) { ?><option value="<?php echo (int) $venue['id']; ?>" <?php echo $formVenueId === (int) $venue['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($venue['venue_name']); ?></option><?php } ?></select></div>
                    <div class="field"><label>Classes that are to be preponed</label><div class="chip-grid"><label class="chip"><input type="checkbox" name="class_first_year" value="1" <?php echo submit_proposal_checked('class_first_year') ? 'checked' : ''; ?>> First Year</label><label class="chip"><input type="checkbox" name="class_second_year" value="1" <?php echo submit_proposal_checked('class_second_year') ? 'checked' : ''; ?>> Second Year</label><label class="chip"><input type="checkbox" name="class_third_year" value="1" <?php echo submit_proposal_checked('class_third_year') ? 'checked' : ''; ?>> Third Year</label><label class="chip"><input type="checkbox" name="class_fourth_year" value="1" <?php echo submit_proposal_checked('class_fourth_year') ? 'checked' : ''; ?>> Fourth Year</label></div></div>
                    <div class="field field-span"><label>Event Details</label><textarea name="event_details" rows="5" required><?php echo htmlspecialchars((string) ($_POST['event_details'] ?? '')); ?></textarea></div>
                    <input type="hidden" name="class_disruption_details" value="<?php echo htmlspecialchars((string) ($_POST['class_disruption_details'] ?? '')); ?>">
                </div>
            </section>

            <section class="proposal-section">
                <h4>Service Requirements</h4>
                <div class="chip-grid service-grid">
                    <label class="chip"><input type="checkbox" name="lights_sound_system" value="1" <?php echo submit_proposal_checked('lights_sound_system') ? 'checked' : ''; ?>> Lights and Sound System</label>
                    <label class="chip"><input type="checkbox" name="camera" value="1" <?php echo submit_proposal_checked('camera') ? 'checked' : ''; ?>> Camera</label>
                    <label class="chip"><input type="checkbox" name="inauguration_lamp" value="1" <?php echo submit_proposal_checked('inauguration_lamp') ? 'checked' : ''; ?>> Inauguration Lamp</label>
                    <label class="chip"><input type="checkbox" name="executive_lunch_dinner" value="1" <?php echo submit_proposal_checked('executive_lunch_dinner') ? 'checked' : ''; ?>> Executive lunch/dinner</label>
                    <label class="chip"><input type="checkbox" name="projector" value="1" <?php echo submit_proposal_checked('projector') ? 'checked' : ''; ?>> Projector</label>
                    <label class="chip"><input type="checkbox" name="it_support" value="1" <?php echo submit_proposal_checked('it_support') ? 'checked' : ''; ?>> IT Support</label>
                    <label class="chip"><input type="checkbox" name="security_required" value="1" <?php echo submit_proposal_checked('security_required') ? 'checked' : ''; ?>> Security Required</label>
                    <label class="chip"><input type="checkbox" name="sports_event" value="1" <?php echo submit_proposal_checked('sports_event') ? 'checked' : ''; ?>> Sports Event / Venue</label>
                </div>
                <div class="field" style="margin-top:12px;"><label>Other Resources</label><textarea name="other_resources" rows="3" placeholder="Enter any additional resources needed"><?php echo htmlspecialchars((string) ($_POST['other_resources'] ?? '')); ?></textarea></div>
            </section>

            <section class="proposal-section">
                <h4>Budget Requirements</h4>
                <div class="table-wrap">
                    <table class="budget-table" id="budget-table">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Quantity</th>
                                <th>Rate</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($budgetRows as $key => $label) { ?>
                                <tr data-budget-row="<?php echo htmlspecialchars($key); ?>">
                                    <td><?php echo htmlspecialchars($label); ?></td>
                                    <td><input type="number" min="0" step="1" name="budget_quantity[<?php echo htmlspecialchars($key); ?>]" value="<?php echo htmlspecialchars((string) ($_POST['budget_quantity'][$key] ?? '0')); ?>" class="budget-qty"></td>
                                    <td><input type="number" min="0" step="0.01" name="budget_rate[<?php echo htmlspecialchars($key); ?>]" value="<?php echo htmlspecialchars((string) ($_POST['budget_rate'][$key] ?? '0')); ?>" class="budget-rate"></td>
                                    <td><input type="text" value="0.00" readonly class="budget-total"></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="3">Grand Total</th>
                                <th><input type="text" id="grand-total" value="0.00" readonly></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div style="margin-top:14px;">
                    <div class="panel-header" style="margin-bottom:10px;">
                        <h4>Additional Custom Budget Items</h4>
                        <button class="btn secondary" type="button" id="add-custom-budget-item">+ Add Custom Budget Item</button>
                    </div>
                    <div id="custom-budget-items" class="timeline"></div>
                </div>
            </section>

            <section class="proposal-section">
                <h4>SPOC Details</h4>
                <div class="proposal-grid">
                    <div class="field"><label>SPOC Name</label><input name="spoc_name" value="<?php echo htmlspecialchars((string) ($_POST['spoc_name'] ?? '')); ?>"></div>
                    <div class="field"><label>SPOC Mobile Number</label><input name="spoc_mobile" value="<?php echo htmlspecialchars((string) ($_POST['spoc_mobile'] ?? '')); ?>"></div>
                    <div class="field field-span"><label>SPOC Email</label><input type="email" name="spoc_email" value="<?php echo htmlspecialchars((string) ($_POST['spoc_email'] ?? '')); ?>"></div>
                </div>
            </section>

            <section class="proposal-section">
                <h4>Declaration</h4>
                <div class="proposal-grid">
                    <div class="field"><label>Head Name</label><input name="declaration_head_name" value="<?php echo htmlspecialchars((string) ($_POST['declaration_head_name'] ?? '')); ?>"></div>
                    <div class="field"><label>Head Mobile Number</label><input name="declaration_head_mobile" value="<?php echo htmlspecialchars((string) ($_POST['declaration_head_mobile'] ?? '')); ?>"></div>
                    <div class="field"><label>Head Typed Signature</label><input name="declaration_head_signature" value="<?php echo htmlspecialchars((string) ($_POST['declaration_head_signature'] ?? '')); ?>" placeholder="Type your name as digital signature"></div>
                    <div class="field"><label>Co-Head Name</label><input name="declaration_co_head_name" value="<?php echo htmlspecialchars((string) ($_POST['declaration_co_head_name'] ?? '')); ?>"></div>
                    <div class="field"><label>Co-Head Mobile Number</label><input name="declaration_co_head_mobile" value="<?php echo htmlspecialchars((string) ($_POST['declaration_co_head_mobile'] ?? '')); ?>"></div>
                    <div class="field"><label>Co-Head Typed Signature</label><input name="declaration_co_head_signature" value="<?php echo htmlspecialchars((string) ($_POST['declaration_co_head_signature'] ?? '')); ?>" placeholder="Type your name as digital signature"></div>
                </div>
                <label class="chip declaration-check"><input type="checkbox" name="declaration_agreed" value="1" <?php echo submit_proposal_checked('declaration_agreed') ? 'checked' : ''; ?>> I declare that the information provided above is accurate.</label>
            </section>

            <div class="proposal-actions inline-actions">
                <button class="btn" type="submit">Submit Proposal</button>
                <a class="btn secondary" href="dashboard.php">Cancel</a>
            </div>
        </form>
    </div>
</section>

<script>
(() => {
    const form = document.getElementById('proposal-form');
    const eventDate = document.getElementById('event-date');
    const eventDay = document.getElementById('event-day');
    const budgetTable = document.getElementById('budget-table');
    const grandTotalField = document.getElementById('grand-total');
    const addCustomBudgetItemBtn = document.getElementById('add-custom-budget-item');
    const customBudgetItemsWrap = document.getElementById('custom-budget-items');

    const updateDay = () => {
        if (!eventDate || !eventDay) return;
        if (!eventDate.value) {
            eventDay.value = '';
            return;
        }
        const date = new Date(eventDate.value + 'T00:00:00');
        eventDay.value = date.toLocaleDateString('en-US', { weekday: 'long' });
    };

    const updateBudgets = () => {
        let grandTotal = 0;
        budgetTable.querySelectorAll('tbody tr').forEach((row) => {
            const qty = parseFloat(row.querySelector('.budget-qty').value || '0');
            const rate = parseFloat(row.querySelector('.budget-rate').value || '0');
            const total = qty * rate;
            row.querySelector('.budget-total').value = total.toFixed(2);
            grandTotal += total;
        });

        if (customBudgetItemsWrap) {
            customBudgetItemsWrap.querySelectorAll('.custom-budget-row').forEach((row) => {
                const qtyInput = row.querySelector('.custom-budget-qty');
                const rateInput = row.querySelector('.custom-budget-rate');
                const totalInput = row.querySelector('.custom-budget-total');
                const qty = parseFloat((qtyInput && qtyInput.value) || '0');
                const rate = parseFloat((rateInput && rateInput.value) || '0');
                const total = qty * rate;
                if (totalInput) {
                    totalInput.value = total.toFixed(2);
                }
                grandTotal += total;
            });
        }

        grandTotalField.value = grandTotal.toFixed(2);
    };

    const createCustomBudgetRow = (name = '', qty = '0', rate = '0') => {
        if (!customBudgetItemsWrap) {
            return;
        }

        const row = document.createElement('div');
        row.className = 'timeline-item custom-budget-row';
        row.innerHTML = `
            <div class="form-grid" style="margin-top:0;">
                <div class="field"><label>Custom Item Name</label><input name="custom_item_name[]" value="${name}" placeholder="e.g. Printing"></div>
                <div class="field"><label>Quantity</label><input class="custom-budget-qty" type="number" min="0" step="0.01" name="custom_item_qty[]" value="${qty}"></div>
                <div class="field"><label>Rate</label><input class="custom-budget-rate" type="number" min="0" step="0.01" name="custom_item_rate[]" value="${rate}"></div>
                <div class="field"><label>Total</label><input class="custom-budget-total" type="text" value="0.00" readonly></div>
                <div class="field" style="justify-content:end;"><label>&nbsp;</label><button class="btn bad remove-custom-budget-item" type="button">Remove</button></div>
            </div>
        `;

        customBudgetItemsWrap.appendChild(row);
        updateBudgets();
    };

    if (eventDate) {
        eventDate.addEventListener('change', updateDay);
    }
    if (budgetTable) {
        budgetTable.addEventListener('input', updateBudgets);
        updateBudgets();
    }

    if (addCustomBudgetItemBtn) {
        addCustomBudgetItemBtn.addEventListener('click', () => {
            createCustomBudgetRow();
        });
    }

    if (customBudgetItemsWrap) {
        customBudgetItemsWrap.addEventListener('input', (event) => {
            if (event.target && event.target.classList.contains('custom-budget-qty')) {
                updateBudgets();
            }
            if (event.target && event.target.classList.contains('custom-budget-rate')) {
                updateBudgets();
            }
        });

        customBudgetItemsWrap.addEventListener('click', (event) => {
            const target = event.target;
            if (target && target.classList.contains('remove-custom-budget-item')) {
                const row = target.closest('.custom-budget-row');
                if (row) {
                    row.remove();
                    updateBudgets();
                }
            }
        });
    }

    createCustomBudgetRow();
    updateDay();

    if (form) {
        form.addEventListener('submit', () => {
            updateBudgets();
            updateDay();
        });
    }
})();
</script>
<?php layout_render_footer(); ?>
