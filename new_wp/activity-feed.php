<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/layout.php';

$user = app_require_login();
$role = app_normalize_role((string) $user['role'], $user['sub_role'] ?? null);

if ($role !== 'super_admin') {
    app_flash_set('error', 'This page is for super admin only.');
    app_redirect(app_role_dashboard($role));
}

$activities = app_fetch_recent_activity(30);

layout_render_header('Activity Feed', $user, 'activity_feed');
?>
<section class="panel">
    <div class="panel-header"><h3>Recent Activity</h3></div>
    <div class="timeline">
        <?php foreach ($activities as $activity) { ?>
            <div class="timeline-item">
                <strong><?php echo htmlspecialchars($activity['summary']); ?></strong>
                <p><?php echo htmlspecialchars($activity['full_name']); ?> · <?php echo htmlspecialchars($activity['role_name']); ?> · <?php echo htmlspecialchars($activity['created_at']); ?></p>
            </div>
        <?php } ?>
    </div>
</section>
<?php layout_render_footer(); ?>
