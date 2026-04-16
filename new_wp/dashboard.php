<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/layout.php';
require_once __DIR__ . '/inc/workflow.php';

$user = app_require_login();
$role = app_normalize_role((string) $user['role'], $user['sub_role'] ?? null);
$effectiveSchoolId = (int) (app_effective_school_id($user) ?? 0);
$effectiveClubId = (int) (app_effective_club_id($user) ?? 0);
$title = 'Dashboard';

workflow_refresh_completed_events();

$proposalsCount = 0;
$approvedCount = 0;
$pendingCount = 0;
$upcomingCount = 0;
$eventsCount = 0;
$tasksCount = 0;
$reportsCount = app_table_exists('event_reports') ? app_safe_count('SELECT COUNT(*) AS c FROM event_reports') : 0;
$recentActivity = app_fetch_recent_activity(6);

if (app_table_exists('proposals')) {
    $userId = (int) $user['id'];
    $hasWorkflow = app_table_exists('approval_workflow_steps');

    if ($role === 'club_head') {
        $proposalsCount = app_safe_count('SELECT COUNT(*) AS c FROM proposals WHERE submitted_by = ?', 'i', [$userId]);
        if ($hasWorkflow) {
            $pendingCount = app_safe_count('SELECT COUNT(*) AS c FROM proposals WHERE submitted_by = ? AND overall_status NOT IN ("approved", "locked", "cancelled", "closed", "completed")', 'i', [$userId]);
            $approvedCount = app_safe_count('SELECT COUNT(*) AS c FROM proposals WHERE submitted_by = ? AND overall_status = "approved"', 'i', [$userId]);
            $eventsCount = app_safe_count('SELECT COUNT(*) AS c FROM events e JOIN proposals p ON p.id = e.proposal_id WHERE p.submitted_by = ?', 'i', [$userId]);
        } else {
            $pendingCount = app_safe_count('SELECT COUNT(*) AS c FROM proposals WHERE user_id = ? AND (faculty_mentor_status = "Pending" OR program_chair_status = "Pending")', 'i', [$userId]);
            $approvedCount = app_safe_count('SELECT COUNT(*) AS c FROM proposals WHERE user_id = ? AND faculty_mentor_status = "Approved" AND program_chair_status = "Approved"', 'i', [$userId]);
            $eventsCount = 0;
        }
    } elseif (app_is_main_approver_role($role)) {
        if ($hasWorkflow) {
            $roleLevel = app_approval_level_for_role($role) ?? 0;
            $pendingCount = app_safe_count(
                'SELECT COUNT(*) AS c FROM approval_workflow_steps aws JOIN proposals p ON p.id = aws.proposal_id WHERE aws.role_name = ? AND p.current_approval_level = ? AND p.overall_status NOT IN ("query_raised", "rejected_pending_response", "locked") AND aws.status IN ("pending", "resubmitted") AND (aws.approver_user_id = ? OR aws.approver_user_id IS NULL OR aws.approver_user_id = 0)',
                'sii',
                [$role, $roleLevel, $userId]
            );
            $upcomingCount = app_safe_count(
                'SELECT COUNT(*) AS c FROM approval_workflow_steps aws JOIN proposals p ON p.id = aws.proposal_id WHERE aws.role_name = ? AND p.current_approval_level <> ? AND p.overall_status NOT IN ("query_raised", "rejected_pending_response", "locked") AND aws.status IN ("pending", "resubmitted") AND (aws.approver_user_id = ? OR aws.approver_user_id IS NULL OR aws.approver_user_id = 0)',
                'sii',
                [$role, $roleLevel, $userId]
            );
            $approvedCount = app_safe_count('SELECT COUNT(*) AS c FROM approval_workflow_steps WHERE role_name = ? AND (approver_user_id = ? OR approver_user_id IS NULL OR approver_user_id = 0) AND status = "approved"', 'si', [$role, $userId]);
            $proposalsCount = $pendingCount;
            if ($effectiveSchoolId > 0) {
                $eventsCount = app_safe_count('SELECT COUNT(*) AS c FROM events e JOIN proposals p ON p.id = e.proposal_id WHERE p.school_id = ?', 'i', [$effectiveSchoolId]);
            } else {
                $eventsCount = app_safe_count('SELECT COUNT(*) AS c FROM events');
            }
        } else {
            $pendingCount = app_safe_count('SELECT COUNT(*) AS c FROM proposals WHERE faculty_mentor_status = "Pending" OR program_chair_status = "Pending"');
            $approvedCount = app_safe_count('SELECT COUNT(*) AS c FROM proposals WHERE faculty_mentor_status = "Approved" OR program_chair_status = "Approved"');
            $proposalsCount = app_safe_count('SELECT COUNT(*) AS c FROM proposals');
        }
    } elseif (app_is_department_role($role)) {
        if ($hasWorkflow && app_table_exists('department_tasks')) {
            $roleLevel = app_approval_level_for_role($role) ?? 0;
            $pendingCount = app_safe_count('SELECT COUNT(*) AS c FROM department_tasks dt JOIN proposals p ON p.id = dt.proposal_id WHERE dt.department_role = ? AND p.current_approval_level = ? AND p.overall_status NOT IN ("query_raised", "rejected_pending_response", "locked") AND dt.status = "pending" AND (dt.assigned_user_id = ? OR dt.assigned_user_id IS NULL OR dt.assigned_user_id = 0)', 'sii', [$role, $roleLevel, $userId]);
            $upcomingCount = app_safe_count('SELECT COUNT(*) AS c FROM department_tasks dt JOIN proposals p ON p.id = dt.proposal_id WHERE dt.department_role = ? AND p.current_approval_level <> ? AND p.overall_status NOT IN ("query_raised", "rejected_pending_response", "locked") AND dt.status = "pending" AND (dt.assigned_user_id = ? OR dt.assigned_user_id IS NULL OR dt.assigned_user_id = 0)', 'sii', [$role, $roleLevel, $userId]);
            $approvedCount = app_safe_count('SELECT COUNT(*) AS c FROM department_tasks WHERE department_role = ? AND status IN ("approved", "completed") AND (assigned_user_id = ? OR assigned_user_id IS NULL OR assigned_user_id = 0)', 'si', [$role, $userId]);
            $tasksCount = $pendingCount + $upcomingCount;
            $proposalsCount = $pendingCount;
            if ($effectiveSchoolId > 0) {
                $eventsCount = app_safe_count('SELECT COUNT(*) AS c FROM events e JOIN proposals p ON p.id = e.proposal_id WHERE p.school_id = ?', 'i', [$effectiveSchoolId]);
            } else {
                $eventsCount = app_safe_count('SELECT COUNT(*) AS c FROM events');
            }
        } elseif ($hasWorkflow) {
            $roleLevel = app_approval_level_for_role($role) ?? 0;
            $pendingCount = app_safe_count('SELECT COUNT(*) AS c FROM approval_workflow_steps aws JOIN proposals p ON p.id = aws.proposal_id WHERE aws.role_name = ? AND p.current_approval_level = ? AND p.overall_status NOT IN ("query_raised", "rejected_pending_response", "locked") AND aws.status IN ("pending", "resubmitted") AND (aws.approver_user_id = ? OR aws.approver_user_id IS NULL OR aws.approver_user_id = 0)', 'sii', [$role, $roleLevel, $userId]);
            $upcomingCount = app_safe_count('SELECT COUNT(*) AS c FROM approval_workflow_steps aws JOIN proposals p ON p.id = aws.proposal_id WHERE aws.role_name = ? AND p.current_approval_level <> ? AND p.overall_status NOT IN ("query_raised", "rejected_pending_response", "locked") AND aws.status IN ("pending", "resubmitted") AND (aws.approver_user_id = ? OR aws.approver_user_id IS NULL OR aws.approver_user_id = 0)', 'sii', [$role, $roleLevel, $userId]);
            $approvedCount = app_safe_count('SELECT COUNT(*) AS c FROM approval_workflow_steps WHERE role_name = ? AND (approver_user_id = ? OR approver_user_id IS NULL OR approver_user_id = 0) AND status = "approved"', 'si', [$role, $userId]);
            $proposalsCount = $pendingCount;
        }
    } elseif ($role === 'student') {
        $eventsCount = app_safe_count('SELECT COUNT(*) AS c FROM events WHERE event_status = "upcoming"');
        $approvedCount = app_safe_count('SELECT COUNT(*) AS c FROM event_registrations WHERE student_user_id = ?', 'i', [$userId]);
    } elseif ($role === 'super_admin') {
        if ($hasWorkflow) {
            $proposalsCount = app_safe_count('SELECT COUNT(*) AS c FROM proposals');
            $pendingCount = app_safe_count('SELECT COUNT(*) AS c FROM proposals WHERE overall_status NOT IN ("approved", "locked", "cancelled", "closed", "completed")');
            $approvedCount = app_safe_count('SELECT COUNT(*) AS c FROM proposals WHERE overall_status = "approved"');
            $eventsCount = app_safe_count('SELECT COUNT(*) AS c FROM events');
        } else {
            $proposalsCount = app_safe_count('SELECT COUNT(*) AS c FROM proposals');
        }
    }
}

$primaryCardLabel = 'Total Proposals';
$primaryCardValue = $proposalsCount;
$secondaryCardLabel = 'Pending';
$secondaryCardValue = $pendingCount;

if (app_is_main_approver_role($role) || app_is_department_role($role)) {
    $primaryCardLabel = 'Actionable Now';
    $primaryCardValue = $pendingCount;
    $secondaryCardLabel = 'Upcoming';
    $secondaryCardValue = $upcomingCount;
}

$schoolId = $effectiveSchoolId > 0 ? $effectiveSchoolId : null;
$clubId = $effectiveClubId > 0 ? $effectiveClubId : null;
$schoolClubs = ($role === 'school_head' && $schoolId && app_table_exists('clubs')) ? app_fetch_school_clubs($schoolId) : [];
$reportPendingCount = 0;
if (app_table_exists('events') && $role === 'club_head' && $clubId) {
    $reportPendingCount = app_safe_count('SELECT COUNT(*) AS c FROM events e JOIN proposals p ON p.id = e.proposal_id WHERE p.club_id = ? AND e.event_status = "completed" AND e.id NOT IN (SELECT event_id FROM event_reports)', 'i', [$clubId]);
}

layout_render_header($title, $user, 'dashboard');
?>
<section class="card-grid">
    <div class="card"><p><?php echo htmlspecialchars($primaryCardLabel); ?></p><h3><?php echo (int)$primaryCardValue; ?></h3></div>
    <div class="card"><p><?php echo htmlspecialchars($secondaryCardLabel); ?></p><h3><?php echo (int)$secondaryCardValue; ?></h3></div>
    <div class="card"><p>Approved</p><h3><?php echo (int)$approvedCount; ?></h3></div>
    <div class="card"><p>Events</p><h3><?php echo (int)$eventsCount; ?></h3></div>
</section>

<div class="split">
    <section class="panel">
        <div class="panel-header">
            <h3>Quick Actions</h3>
            <span class="badge"><?php echo htmlspecialchars(app_role_label($role)); ?></span>
        </div>
        <div class="chip-grid">
            <?php if ($role === 'club_head') { ?>
                <a class="chip" href="submit-proposal.php"><i class="fa-solid fa-file-pen"></i> Submit White Paper</a>
                <a class="chip" href="my-proposals.php"><i class="fa-solid fa-folder-open"></i> My Proposals</a>
                <a class="chip" href="post-event-report.php"><i class="fa-solid fa-images"></i> Post-Event Report</a>
            <?php } ?>
            <?php if (app_is_main_approver_role($role)) { ?>
                <a class="chip" href="approvals.php"><i class="fa-solid fa-circle-check"></i> Review Approvals</a>
            <?php } ?>
            <?php if (app_is_department_role($role)) { ?>
                <a class="chip" href="department-tasks.php"><i class="fa-solid fa-list-check"></i> Department Tasks</a>
            <?php } ?>
            <?php if ($role === 'school_head') { ?>
                <a class="chip" href="school-clubs.php"><i class="fa-solid fa-building-columns"></i> School Clubs</a>
            <?php } ?>
            <?php if ($role === 'super_admin') { ?>
                <a class="chip" href="manage-schools.php"><i class="fa-solid fa-school"></i> Manage Schools</a>
                <a class="chip" href="manage-club-members.php"><i class="fa-solid fa-user-gear"></i> Manage Club Members</a>
                <a class="chip" href="manage-clubs.php"><i class="fa-solid fa-people-group"></i> Manage Clubs</a>
                <a class="chip" href="manage-venues.php"><i class="fa-solid fa-map-location-dot"></i> Manage Venues</a>
            <?php } ?>
            <a class="chip" href="event-calendar.php"><i class="fa-solid fa-calendar-days"></i> Calendar</a>
            <a class="chip" href="notifications.php"><i class="fa-solid fa-bell"></i> Notifications</a>
        </div>
    </section>

    <section class="panel">
        <div class="panel-header">
            <h3>Current Stage</h3>
        </div>
        <div class="timeline">
            <?php if ($role === 'club_head') { ?>
                <div class="timeline-item"><strong>Club Snapshot</strong><p><?php echo htmlspecialchars(app_club_logo_path($clubId) ? 'Club logo available' : 'Club logo not set yet'); ?></p></div>
                <div class="timeline-item"><strong>Reports Pending</strong><p><?php echo (int)$reportPendingCount; ?> completed events still need reports.</p></div>
                <div class="timeline-item"><strong>Proposal Trail</strong><p>Submit proposals, review queries, and track each approval stage.</p></div>
            <?php } elseif (app_is_main_approver_role($role)) { ?>
                <div class="timeline-item"><strong>Approval Queue</strong><p><?php echo (int)$pendingCount; ?> proposal(s) are ready now. Use Pending Proposals to approve, reject, or raise a query.</p></div>
                <div class="timeline-item"><strong>Upcoming Reviews</strong><p><?php echo (int)$upcomingCount; ?> proposal(s) are assigned to your role but waiting for earlier levels.</p></div>
                <div class="timeline-item"><strong>Post-Event Review</strong><p>Review submitted reports and event outcomes for your school.</p></div>
            <?php } elseif (app_is_department_role($role)) { ?>
                <div class="timeline-item"><strong>Service Tasks</strong><p><?php echo (int)$pendingCount; ?> task(s) are actionable now in Service Tasks for approve/reject/complete.</p></div>
                <div class="timeline-item"><strong>Upcoming Tasks</strong><p><?php echo (int)$upcomingCount; ?> task(s) are queued and unlock after earlier approvals finish.</p></div>
                <div class="timeline-item"><strong>Institution Flow</strong><p>Department approvals feed directly into final institutional review.</p></div>
            <?php } elseif ($role === 'student') { ?>
                <div class="timeline-item"><strong>Explore Events</strong><p>Check upcoming approved events and register from the student portal.</p></div>
                <div class="timeline-item"><strong>Gallery</strong><p>Completed event reports and images appear after the event ends.</p></div>
            <?php } else { ?>
                <div class="timeline-item"><strong>System Overview</strong><p>Use the navigation to access proposals, workflow tasks, reporting, and event publishing tools.</p></div>
            <?php } ?>
        </div>
    </section>
</div>

<div class="split">
    <section class="panel">
        <div class="panel-header"><h3>Recent Activity</h3></div>
        <div class="timeline">
            <?php if (empty($recentActivity)) { ?>
                <div class="timeline-item"><strong>No activity yet</strong><p>Activity will appear here once users start submitting proposals and reports.</p></div>
            <?php } else { foreach ($recentActivity as $activity) { ?>
                <div class="timeline-item">
                    <strong><?php echo htmlspecialchars($activity['summary']); ?></strong>
                    <p><?php echo htmlspecialchars($activity['full_name']); ?> · <?php echo htmlspecialchars($activity['action_type']); ?></p>
                </div>
            <?php } } ?>
        </div>
    </section>

    <section class="panel">
        <div class="panel-header"><h3>School / Club Snapshot</h3></div>
        <?php if ($role === 'school_head' && !empty($schoolClubs)) { ?>
            <div class="card-grid" style="grid-template-columns: repeat(2, minmax(0, 1fr));">
                <?php foreach ($schoolClubs as $club) { ?>
                    <a class="card" href="club-detail.php?id=<?php echo (int)$club['id']; ?>" style="text-decoration:none;">
                        <?php if (!empty($club['club_logo'])) { ?><img src="<?php echo htmlspecialchars($club['club_logo']); ?>" alt="Club Logo" style="width:56px;height:56px;border-radius:14px;object-fit:cover;margin-bottom:10px;"><?php } ?>
                        <p><?php echo htmlspecialchars($club['club_name']); ?></p>
                        <h3><?php echo (int)$club['proposal_count']; ?></h3>
                        <small><?php echo (int)$club['approved_count']; ?> approved</small>
                    </a>
                <?php } ?>
            </div>
        <?php } elseif ($role === 'club_head') { ?>
            <div class="timeline-item">
                <strong><?php echo htmlspecialchars(app_school_label($schoolId)); ?></strong>
                <p>Current club workspace for proposals, reports, and approvals.</p>
            </div>
        <?php } else { ?>
            <div class="timeline-item"><strong>Current Context</strong><p><?php echo htmlspecialchars(app_school_label($schoolId)); ?></p></div>
        <?php } ?>
    </section>
</div>
<?php layout_render_footer(); ?>
