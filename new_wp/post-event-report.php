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

$clubId = (int) ($user['club_id'] ?? 0);
$events = [];

if (app_table_exists('events') && $clubId > 0) {
    $stmt = $conn->prepare('SELECT e.id, e.event_name, e.event_date, e.event_status, p.school_id FROM events e JOIN proposals p ON p.id = e.proposal_id WHERE p.club_id = ? ORDER BY e.event_date DESC');
    $stmt->bind_param('i', $clubId);
    $stmt->execute();
    $events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $eventId = (int) ($_POST['event_id'] ?? 0);
    $reportTitle = app_clean_text((string) ($_POST['report_title'] ?? ''));
    $reportDescription = app_clean_text((string) ($_POST['report_description'] ?? ''));
    $participantsCount = (int) ($_POST['participants_count'] ?? 0);
    $reportFilePath = null;
    $imagePaths = [];

    if (!empty($_FILES['report_file']['name']) && is_uploaded_file($_FILES['report_file']['tmp_name'])) {
        $targetDir = __DIR__ . '/uploads/reports';
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }
        $fileName = uniqid('report_', true) . '_' . basename($_FILES['report_file']['name']);
        $targetPath = $targetDir . '/' . $fileName;
        if (move_uploaded_file($_FILES['report_file']['tmp_name'], $targetPath)) {
            $reportFilePath = 'uploads/reports/' . $fileName;
        }
    }

    if (!empty($_FILES['images']['name']) && is_array($_FILES['images']['name'])) {
        $targetDir = __DIR__ . '/uploads/event_images';
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }
        foreach ($_FILES['images']['name'] as $index => $name) {
            if (empty($name) || !is_uploaded_file($_FILES['images']['tmp_name'][$index])) {
                continue;
            }
            $fileName = uniqid('event_', true) . '_' . basename($name);
            $targetPath = $targetDir . '/' . $fileName;
            if (move_uploaded_file($_FILES['images']['tmp_name'][$index], $targetPath)) {
                $imagePaths[] = 'uploads/event_images/' . $fileName;
            }
        }
    }

    if ($eventId > 0 && $reportDescription !== '') {
        workflow_submit_event_report($eventId, (int) $user['id'], $reportTitle, $reportDescription, $participantsCount, $reportFilePath, $imagePaths);
        app_flash_set('success', 'Post-event report submitted.');
        app_redirect('post-event-report.php');
    }
}

$reports = [];
if (app_table_exists('event_reports') && $clubId > 0) {
    $stmt = $conn->prepare('SELECT er.*, e.event_name, e.event_date FROM event_reports er JOIN events e ON e.id = er.event_id JOIN proposals p ON p.id = e.proposal_id WHERE p.club_id = ? ORDER BY er.submitted_at DESC');
    $stmt->bind_param('i', $clubId);
    $stmt->execute();
    $reports = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

layout_render_header('Post-Event Report', $user, 'post_event_report');
?>
<section class="panel">
    <div class="panel-header"><h3>Submit Post-Event Report</h3></div>
    <form method="post" enctype="multipart/form-data" class="form-grid">
        <div class="field"><label>Event</label><select name="event_id" required><?php foreach ($events as $event) { ?><option value="<?php echo (int)$event['id']; ?>"><?php echo htmlspecialchars($event['event_name']); ?> (<?php echo htmlspecialchars($event['event_date']); ?>)</option><?php } ?></select></div>
        <div class="field"><label>Report Title</label><input name="report_title"></div>
        <div class="field"><label>Participants Count</label><input type="number" name="participants_count" min="0" value="0"></div>
        <div class="field field-span"><label>Report Description / Outcome</label><textarea name="report_description" required></textarea></div>
        <div class="field"><label>Report File</label><input type="file" name="report_file" accept=".pdf,.doc,.docx"></div>
        <div class="field"><label>Event Images</label><input type="file" name="images[]" accept="image/*" multiple></div>
        <div class="field" style="justify-content:end;"><label>&nbsp;</label><button class="btn" type="submit">Submit Report</button></div>
    </form>
</section>

<section class="panel">
    <div class="panel-header"><h3>Submitted Reports</h3></div>
    <div class="timeline">
        <?php foreach ($reports as $report) { ?>
            <div class="timeline-item">
                <strong><?php echo htmlspecialchars($report['event_name']); ?></strong>
                <p><?php echo htmlspecialchars($report['report_title'] ?? 'Report'); ?></p>
                <p><?php echo htmlspecialchars($report['submitted_at']); ?></p>
            </div>
        <?php } ?>
    </div>
</section>
<?php layout_render_footer(); ?>
