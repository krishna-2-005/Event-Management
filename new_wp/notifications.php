<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/layout.php';

$user = app_require_login();
$notifications = app_fetch_notifications((int)$user['id'], 25);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all_read'])) {
    app_mark_notifications_read((int)$user['id']);
    app_flash_set('success', 'All notifications marked as read.');
    app_redirect('notifications.php');
}

layout_render_header('Notifications', $user, 'notifications');
?>
<section class="panel">
    <div class="panel-header">
        <h3>Notifications</h3>
        <form method="post">
            <button class="btn secondary" type="submit" name="mark_all_read" value="1">Mark all read</button>
        </form>
    </div>

    <?php if (empty($notifications)) { ?>
        <p>No notifications yet.</p>
    <?php } else { ?>
        <div class="timeline">
            <?php foreach ($notifications as $notification) { ?>
                <div class="timeline-item">
                    <div class="panel-header" style="margin-bottom:8px;">
                        <strong><?php echo htmlspecialchars((string)($notification['title'] ?: 'Notification')); ?></strong>
                        <span class="badge <?php echo $notification['is_read'] ? 'approved' : 'pending'; ?>"><?php echo $notification['is_read'] ? 'Read' : 'Unread'; ?></span>
                    </div>
                    <p><?php echo htmlspecialchars($notification['message']); ?></p>
                    <small><?php echo htmlspecialchars($notification['created_at']); ?></small>
                </div>
            <?php } ?>
        </div>
    <?php } ?>
</section>
<?php layout_render_footer(); ?>
