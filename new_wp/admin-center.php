<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/layout.php';

$user = app_require_login();
$role = app_normalize_role((string) $user['role'], $user['sub_role'] ?? null);

if ($role !== 'super_admin') {
    app_flash_set('error', 'This page is for super admin only.');
    app_redirect(app_role_dashboard($role));
}

$usersCount = app_safe_count('SELECT COUNT(*) AS c FROM users');
$clubsCount = app_safe_count('SELECT COUNT(*) AS c FROM clubs');
$proposalsCount = app_safe_count('SELECT COUNT(*) AS c FROM proposals');
$eventsCount = app_safe_count('SELECT COUNT(*) AS c FROM events');
$reportsCount = app_table_exists('event_reports') ? app_safe_count('SELECT COUNT(*) AS c FROM event_reports') : 0;
$blockedDatesCount = app_table_exists('blocked_dates') ? app_safe_count('SELECT COUNT(*) AS c FROM blocked_dates') : 0;
$recentActivity = app_fetch_recent_activity(8);

layout_render_header('Admin Center', $user, 'admin_center');
?>
<section class="card-grid">
    <div class="card"><p>Users</p><h3><?php echo (int)$usersCount; ?></h3></div>
    <div class="card"><p>Clubs</p><h3><?php echo (int)$clubsCount; ?></h3></div>
    <div class="card"><p>Proposals</p><h3><?php echo (int)$proposalsCount; ?></h3></div>
    <div class="card"><p>Events</p><h3><?php echo (int)$eventsCount; ?></h3></div>
</section>

<section class="card-grid" style="margin-top:14px;">
    <div class="card"><p>Reports</p><h3><?php echo (int)$reportsCount; ?></h3></div>
    <div class="card"><p>Blocked Dates</p><h3><?php echo (int)$blockedDatesCount; ?></h3></div>
    <div class="card"><p>Schools</p><h3><?php echo (int) app_safe_count('SELECT COUNT(*) AS c FROM schools'); ?></h3></div>
    <div class="card"><p>Venues</p><h3><?php echo (int) app_safe_count('SELECT COUNT(*) AS c FROM venues'); ?></h3></div>
</section>

<section class="panel">
    <div class="panel-header"><h3>System Controls</h3></div>
    <div class="chip-grid">
        <a class="chip" href="dashboard.php"><i class="fa-solid fa-gauge"></i> Open Dashboard</a>
        <a class="chip" href="manage-schools.php"><i class="fa-solid fa-school"></i> Schools</a>
        <a class="chip" href="manage-school-roles.php"><i class="fa-solid fa-id-card"></i> School Roles</a>
        <a class="chip" href="manage-clubs.php"><i class="fa-solid fa-people-group"></i> Clubs</a>
        <a class="chip" href="manage-venues.php"><i class="fa-solid fa-map-location-dot"></i> Venues</a>
        <a class="chip" href="blocked-dates.php"><i class="fa-solid fa-calendar-xmark"></i> Blocked Dates</a>
        <a class="chip" href="all-proposals.php"><i class="fa-solid fa-file-lines"></i> All Proposals</a>
        <a class="chip" href="activity-feed.php"><i class="fa-solid fa-stream"></i> Activity Feed</a>
    </div>
</section>

<section class="panel">
    <div class="panel-header"><h3>Recent Activity</h3></div>
    <div class="timeline">
        <?php foreach ($recentActivity as $activity) { ?>
            <div class="timeline-item">
                <strong><?php echo htmlspecialchars($activity['summary']); ?></strong>
                <p><?php echo htmlspecialchars($activity['full_name']); ?> · <?php echo htmlspecialchars($activity['created_at']); ?></p>
            </div>
        <?php } ?>
    </div>
</section>
<?php layout_render_footer(); ?>
