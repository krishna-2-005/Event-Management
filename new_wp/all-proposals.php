<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/layout.php';

$user = app_require_login();
$role = app_normalize_role((string) $user['role'], $user['sub_role'] ?? null);

if ($role !== 'super_admin') {
    app_flash_set('error', 'This page is for super admin only.');
    app_redirect(app_role_dashboard($role));
}

$proposals = [];
if (app_table_exists('proposals')) {
    $sql = 'SELECT p.id, p.proposal_code, p.event_name, p.event_date, p.overall_status, p.current_stage, s.school_name, c.club_name FROM proposals p JOIN schools s ON s.id = p.school_id JOIN clubs c ON c.id = p.club_id ORDER BY p.created_at DESC';
    $result = $conn->query($sql);
    if ($result) {
        $proposals = $result->fetch_all(MYSQLI_ASSOC);
    }
}

layout_render_header('All Proposals', $user, 'all_proposals');
?>
<section class="panel">
    <div class="panel-header"><h3>All Proposals</h3></div>
    <div class="timeline">
        <?php foreach ($proposals as $proposal) { ?>
            <div class="timeline-item">
                <strong><a href="proposal-details.php?id=<?php echo (int)$proposal['id']; ?>"><?php echo htmlspecialchars($proposal['event_name']); ?></a></strong>
                <p><?php echo htmlspecialchars($proposal['school_name']); ?> · <?php echo htmlspecialchars($proposal['club_name']); ?> · <?php echo htmlspecialchars($proposal['event_date']); ?></p>
                <span class="badge <?php echo htmlspecialchars($proposal['overall_status'] ?? 'pending'); ?>"><?php echo htmlspecialchars($proposal['overall_status'] ?? 'pending'); ?></span>
            </div>
        <?php } ?>
    </div>
</section>
<?php layout_render_footer(); ?>
