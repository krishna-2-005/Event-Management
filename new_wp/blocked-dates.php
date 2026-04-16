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
    $blockDate = (string) ($_POST['block_date'] ?? '');
    $title = app_clean_text((string) ($_POST['title'] ?? ''));
    $reason = app_clean_text((string) ($_POST['reason'] ?? ''));
    $blockType = app_clean_text((string) ($_POST['block_type'] ?? 'institutional'));
    if ($blockDate !== '' && $title !== '') {
        $stmt = $conn->prepare('INSERT INTO blocked_dates (block_date, title, reason, block_type) VALUES (?, ?, ?, ?)');
        $stmt->bind_param('ssss', $blockDate, $title, $reason, $blockType);
        $stmt->execute();
        $stmt->close();
        app_flash_set('success', 'Blocked date saved.');
    }
    app_redirect('blocked-dates.php');
}

$blockedDates = [];
if (app_table_exists('blocked_dates')) {
    $result = $conn->query('SELECT * FROM blocked_dates ORDER BY block_date DESC');
    if ($result) {
        $blockedDates = $result->fetch_all(MYSQLI_ASSOC);
    }
}

layout_render_header('Blocked Dates', $user, 'blocked_dates');
?>
<section class="panel">
    <div class="panel-header"><h3>Add Blocked Date</h3></div>
    <form method="post" class="form-grid">
        <div class="field"><label>Date</label><input type="date" name="block_date" required></div>
        <div class="field"><label>Title</label><input name="title" required></div>
        <div class="field"><label>Type</label><select name="block_type"><option value="exam">Exam</option><option value="holiday">Holiday</option><option value="maintenance">Maintenance</option><option value="institutional">Institutional</option></select></div>
        <div class="field field-span"><label>Reason</label><textarea name="reason"></textarea></div>
        <div class="field" style="justify-content:end;"><label>&nbsp;</label><button class="btn" type="submit">Save Block</button></div>
    </form>
</section>

<section class="panel">
    <div class="panel-header"><h3>Blocked Dates</h3></div>
    <div class="timeline">
        <?php foreach ($blockedDates as $blockedDate) { ?>
            <div class="timeline-item">
                <strong><?php echo htmlspecialchars($blockedDate['title']); ?></strong>
                <p><?php echo htmlspecialchars($blockedDate['block_date']); ?> · <?php echo htmlspecialchars($blockedDate['block_type']); ?></p>
            </div>
        <?php } ?>
    </div>
</section>
<?php layout_render_footer(); ?>
