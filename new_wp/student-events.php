<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/layout.php';

$user = app_require_login();
$role = app_normalize_role((string) $user['role'], $user['sub_role'] ?? null);

if ($role !== 'student') {
    app_flash_set('error', 'This page is for students only.');
    app_redirect(app_role_dashboard($role));
}

$events = [];
if (app_table_exists('events')) {
    $stmt = $conn->prepare('SELECT e.id, e.event_name, e.event_date, e.start_time, e.end_time, e.max_participants, e.event_status, v.venue_name, c.club_name FROM events e LEFT JOIN venues v ON v.id = e.venue_id LEFT JOIN proposals p ON p.id = e.proposal_id LEFT JOIN clubs c ON c.id = p.club_id WHERE e.event_status = "upcoming" ORDER BY e.event_date ASC, e.start_time ASC');
    $stmt->execute();
    $events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

layout_render_header('Student Events', $user, 'student_events');
?>
<section class="panel">
    <div class="panel-header">
        <h3>Upcoming Approved Events</h3>
        <a class="btn secondary" href="my-registrations.php">My Registrations</a>
    </div>

    <?php if (empty($events)) { ?>
        <p>No events are published yet. Once proposals are fully approved, they will appear here.</p>
    <?php } else { ?>
        <div class="timeline">
            <?php foreach ($events as $event) { ?>
                <div class="timeline-item">
                    <div class="panel-header" style="margin-bottom:8px;">
                        <div>
                            <strong><?php echo htmlspecialchars($event['event_name']); ?></strong><br>
                            <small><?php echo htmlspecialchars($event['club_name'] ?? ''); ?></small>
                        </div>
                        <span class="badge approved"><?php echo htmlspecialchars($event['event_status']); ?></span>
                    </div>
                    <p><strong>Date:</strong> <?php echo htmlspecialchars($event['event_date']); ?></p>
                    <p><strong>Time:</strong> <?php echo htmlspecialchars((string)$event['start_time']); ?> - <?php echo htmlspecialchars((string)$event['end_time']); ?></p>
                    <p><strong>Venue:</strong> <?php echo htmlspecialchars($event['venue_name'] ?? ''); ?></p>
                    <div class="inline-actions">
                        <a class="btn" href="event-calendar.php">View Calendar</a>
                        <a class="btn secondary" href="my-registrations.php">Register / Check Ticket</a>
                    </div>
                </div>
            <?php } ?>
        </div>
    <?php } ?>
</section>
<?php layout_render_footer(); ?>
