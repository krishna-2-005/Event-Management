<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/layout.php';

$user = app_require_login();
$role = app_normalize_role((string) $user['role'], $user['sub_role'] ?? null);
$clubId = (int) ($_GET['id'] ?? 0);

if ($clubId <= 0 || !app_table_exists('clubs')) {
    app_flash_set('error', 'Club not found.');
    app_redirect(app_role_dashboard($role));
}

$stmt = $conn->prepare('SELECT c.*, s.school_name FROM clubs c JOIN schools s ON s.id = c.school_id WHERE c.id = ? LIMIT 1');
$stmt->bind_param('i', $clubId);
$stmt->execute();
$club = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$club) {
    app_flash_set('error', 'Club not found.');
    app_redirect(app_role_dashboard($role));
}

$proposals = [];
$events = [];
if (app_table_exists('proposals')) {
    $stmt = $conn->prepare('SELECT id, event_name, event_date, overall_status, current_stage FROM proposals WHERE club_id = ? ORDER BY created_at DESC');
    $stmt->bind_param('i', $clubId);
    $stmt->execute();
    $proposals = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
if (app_table_exists('events')) {
    $stmt = $conn->prepare('SELECT id, event_name, event_date, event_status FROM events e JOIN proposals p ON p.id = e.proposal_id WHERE p.club_id = ? ORDER BY e.event_date DESC');
    $stmt->bind_param('i', $clubId);
    $stmt->execute();
    $events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

layout_render_header('Club Detail', $user, 'dashboard');
?>
<section class="panel">
    <div class="panel-header">
        <div>
            <?php if (!empty($club['club_logo'])) { ?><img src="<?php echo htmlspecialchars($club['club_logo']); ?>" alt="Club Logo" style="width:64px;height:64px;border-radius:16px;object-fit:cover;margin-bottom:10px;"><?php } ?>
            <h3><?php echo htmlspecialchars($club['club_name']); ?></h3>
            <p><?php echo htmlspecialchars($club['school_name']); ?></p>
        </div>
        <span class="badge">Club View</span>
    </div>
</section>

<div class="split">
    <section class="panel">
        <div class="panel-header"><h3>Proposals</h3></div>
        <div class="timeline">
            <?php foreach ($proposals as $proposal) { ?>
                <div class="timeline-item">
                    <strong><a href="proposal-details.php?id=<?php echo (int)$proposal['id']; ?>"><?php echo htmlspecialchars($proposal['event_name']); ?></a></strong>
                    <p><?php echo htmlspecialchars($proposal['event_date']); ?></p>
                    <span class="badge <?php echo htmlspecialchars($proposal['overall_status'] ?? 'pending'); ?>"><?php echo htmlspecialchars($proposal['overall_status'] ?? 'pending'); ?></span>
                </div>
            <?php } ?>
        </div>
    </section>

    <section class="panel">
        <div class="panel-header"><h3>Events</h3></div>
        <div class="timeline">
            <?php foreach ($events as $event) { ?>
                <div class="timeline-item">
                    <strong><?php echo htmlspecialchars($event['event_name']); ?></strong>
                    <p><?php echo htmlspecialchars($event['event_date']); ?></p>
                    <span class="badge approved"><?php echo htmlspecialchars($event['event_status']); ?></span>
                </div>
            <?php } ?>
        </div>
    </section>
</div>
<?php layout_render_footer(); ?>
