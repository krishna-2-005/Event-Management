<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/layout.php';

$user = app_require_login();
$role = app_normalize_role((string) $user['role'], $user['sub_role'] ?? null);

if ($role !== 'school_head') {
    app_flash_set('error', 'This page is for school heads only.');
    app_redirect(app_role_dashboard($role));
}

$schoolId = (int) (app_effective_school_id($user) ?? 0);
$clubs = app_fetch_school_clubs($schoolId);

layout_render_header('School Clubs', $user, 'school_clubs');
?>
<section class="panel">
    <div class="panel-header"><h3>Clubs in Your School</h3></div>
    <?php if ($schoolId <= 0) { ?>
        <p>Your school mapping is missing. Assign this user in School Roles first.</p>
    <?php } elseif (empty($clubs)) { ?>
        <p>No clubs are currently mapped under your school.</p>
    <?php } ?>
    <div class="card-grid">
        <?php foreach ($clubs as $club) { ?>
            <a class="card" href="club-detail.php?id=<?php echo (int)$club['id']; ?>" style="text-decoration:none;">
                <?php if (!empty($club['club_logo'])) { ?><img src="<?php echo htmlspecialchars($club['club_logo']); ?>" alt="Club Logo" style="width:56px;height:56px;border-radius:14px;object-fit:cover;margin-bottom:10px;"><?php } ?>
                <p><?php echo htmlspecialchars($club['club_code'] ?? ''); ?></p>
                <h3><?php echo htmlspecialchars($club['club_name']); ?></h3>
                <p><?php echo (int) $club['proposal_count']; ?> proposals</p>
                <p><?php echo (int) $club['approved_count']; ?> approved</p>
            </a>
        <?php } ?>
    </div>
</section>
<?php layout_render_footer(); ?>
