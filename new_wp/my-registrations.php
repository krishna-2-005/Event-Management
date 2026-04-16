<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/layout.php';

$user = app_require_login();
$role = app_normalize_role((string) $user['role'], $user['sub_role'] ?? null);

if ($role !== 'student') {
    app_flash_set('error', 'This page is for students only.');
    app_redirect(app_role_dashboard($role));
}

$registrations = [];
if (app_table_exists('event_registrations')) {
    $stmt = $conn->prepare('SELECT er.registered_at, er.attendance_status, e.event_name, e.event_date, e.start_time, e.end_time, v.venue_name FROM event_registrations er JOIN events e ON e.id = er.event_id LEFT JOIN venues v ON v.id = e.venue_id WHERE er.student_user_id = ? ORDER BY er.registered_at DESC');
    $stmt->bind_param('i', $user['id']);
    $stmt->execute();
    $registrations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

layout_render_header('My Registrations', $user, 'student_events');
?>
<section class="panel">
    <div class="panel-header">
        <h3>My Event Registrations</h3>
        <a class="btn secondary" href="student-events.php">Back to Events</a>
    </div>

    <?php if (empty($registrations)) { ?>
        <p>You have not registered for any events yet.</p>
    <?php } else { ?>
        <div class="timeline">
            <?php foreach ($registrations as $registration) { ?>
                <div class="timeline-item">
                    <strong><?php echo htmlspecialchars($registration['event_name']); ?></strong>
                    <p><?php echo htmlspecialchars($registration['event_date']); ?> · <?php echo htmlspecialchars($registration['start_time']); ?> - <?php echo htmlspecialchars($registration['end_time']); ?></p>
                    <p><?php echo htmlspecialchars($registration['venue_name'] ?? ''); ?></p>
                    <span class="badge approved"><?php echo htmlspecialchars($registration['attendance_status']); ?></span>
                </div>
            <?php } ?>
        </div>
    <?php } ?>
</section>
<?php layout_render_footer(); ?>
