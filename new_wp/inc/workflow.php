<?php
declare(strict_types=1);

require_once __DIR__ . '/app.php';

function workflow_find_approver_for_role(string $role, int $schoolId, int $clubId): ?int
{
    global $conn;

    $role = app_normalize_role($role);

    if ($role === 'faculty_mentor') {
        $stmt = $conn->prepare('SELECT faculty_mentor_user_id FROM clubs WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $clubId);
        $stmt->execute();
        $result = $stmt->get_result();
        $mentorId = (int) ($result->fetch_assoc()['faculty_mentor_user_id'] ?? 0);
        $stmt->close();
        return $mentorId > 0 ? $mentorId : null;
    }

    if (in_array($role, ['president_vc', 'gs_treasurer', 'school_head'], true) && app_table_exists('school_role_assignments')) {
        $assignment = app_get_school_role_assignment($schoolId, $role);
        if ($assignment && !empty($assignment['user_id'])) {
            return (int) $assignment['user_id'];
        }
    }

    $findByAliasWithSchool = static function (string $sql, int $school) use ($conn): ?int {
        $stmt = $conn->prepare($sql . ' AND school_id = ? AND status = "active" ORDER BY id ASC LIMIT 1');
        $stmt->bind_param('i', $school);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $row ? (int) $row['id'] : null;
    };

    $findByAliasGlobal = static function (string $sql) use ($conn): ?int {
        $stmt = $conn->prepare($sql . ' AND status = "active" ORDER BY id ASC LIMIT 1');
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $row ? (int) $row['id'] : null;
    };

    if ($role === 'deputy_director') {
        if ($schoolId > 0) {
            $id = $findByAliasWithSchool('SELECT id FROM users WHERE role IN ("deputy_director", "dy_director")', $schoolId);
            if ($id) {
                return $id;
            }
        }

        return $findByAliasGlobal('SELECT id FROM users WHERE role IN ("deputy_director", "dy_director")');
    }

    if ($role === 'admin_office') {
        if ($schoolId > 0) {
            $id = $findByAliasWithSchool('SELECT id FROM users WHERE role IN ("admin_office", "administration_officer", "administrative_officer")', $schoolId);
            if ($id) {
                return $id;
            }
        }

        return $findByAliasGlobal('SELECT id FROM users WHERE role IN ("admin_office", "administration_officer", "administrative_officer")');
    }

    if ($role === 'sports_dept') {
        if ($schoolId > 0) {
            $id = $findByAliasWithSchool('SELECT id FROM users WHERE role IN ("sports_dept", "sports_department")', $schoolId);
            if ($id) {
                return $id;
            }
        }

        return $findByAliasGlobal('SELECT id FROM users WHERE role IN ("sports_dept", "sports_department")');
    }

    if ($schoolId > 0) {
        $stmt = $conn->prepare('SELECT id FROM users WHERE role = ? AND school_id = ? AND status = "active" ORDER BY id ASC LIMIT 1');
        $stmt->bind_param('si', $role, $schoolId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($row) {
            return (int) $row['id'];
        }
    }

    $stmt = $conn->prepare('SELECT id FROM users WHERE role = ? AND status = "active" ORDER BY id ASC LIMIT 1');
    $stmt->bind_param('s', $role);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ? (int) $row['id'] : null;
}

function workflow_log_activity(int $actorUserId, string $roleName, string $actionType, string $summary, ?int $proposalId = null, ?int $eventId = null): void
{
    global $conn;

    if (!app_table_exists('activity_logs')) {
        return;
    }

    $stmt = $conn->prepare('INSERT INTO activity_logs (actor_user_id, role_name, action_type, summary, related_proposal_id, related_event_id) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->bind_param('isssii', $actorUserId, $roleName, $actionType, $summary, $proposalId, $eventId);
    $stmt->execute();
    $stmt->close();
}

function workflow_create_main_steps(int $proposalId, int $schoolId, int $clubId): void
{
    global $conn;

    $chain = app_main_workflow_chain();

    foreach ($chain as $index => $role) {
        $stepOrder = $index + 1;
        $approverId = workflow_find_approver_for_role($role, $schoolId, $clubId);
        if ($approverId === null) {
            $stmt = $conn->prepare('INSERT INTO approval_workflow_steps (proposal_id, step_order, role_name, approver_user_id, status) VALUES (?, ?, ?, NULL, "pending")');
            $stmt->bind_param('iis', $proposalId, $stepOrder, $role);
            $stmt->execute();
            $stmt->close();
            continue;
        }

        $stmt = $conn->prepare('INSERT INTO approval_workflow_steps (proposal_id, step_order, role_name, approver_user_id, status) VALUES (?, ?, ?, ?, "pending")');
        $stmt->bind_param('iisi', $proposalId, $stepOrder, $role, $approverId);
        $stmt->execute();
        $stmt->close();
    }
}

function workflow_fetch_proposal_row(int $proposalId): ?array
{
    global $conn;

    $stmt = $conn->prepare('SELECT * FROM proposals WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $proposalId);
    $stmt->execute();
    $proposal = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $proposal ?: null;
}

function workflow_proposal_service_roles(int $proposalId, float $budgetTotal): array
{
    global $conn;

    $services = [];
    if (app_table_exists('proposal_service_requirements')) {
        $stmt = $conn->prepare('SELECT service_name FROM proposal_service_requirements WHERE proposal_id = ? AND required = 1');
        $stmt->bind_param('i', $proposalId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        $services = array_column($rows, 'service_name');
    }

    $roles = workflow_derive_departments($services, $budgetTotal);
    $roles[] = 'admin_office';

    return array_values(array_unique($roles));
}

function workflow_update_explicit_status(int $proposalId, string $column, string $status): void
{
    global $conn;

    $stmt = $conn->prepare('UPDATE proposals SET ' . $column . ' = ? WHERE id = ?');
    $stmt->bind_param('si', $status, $proposalId);
    $stmt->execute();
    $stmt->close();
}

function workflow_explicit_admin_fields_complete(array $proposal): bool
{
    $fields = [
        $proposal['admin_officer_status'] ?? 'Pending',
        $proposal['it_team_status'] ?? 'Not Required',
        $proposal['housekeeping_status'] ?? 'Not Required',
        $proposal['security_status'] ?? 'Not Required',
        $proposal['purchase_status'] ?? 'Not Required',
        $proposal['accounts_status'] ?? 'Not Required'
    ];

    foreach ($fields as $status) {
        if (in_array($status, ['Pending', 'Query Raised'], true)) {
            return false;
        }
    }

    return true;
}

function workflow_notify_first_user_by_role(string $role, string $title, string $message, ?int $schoolId = null): void
{
    global $conn;

    $sql = 'SELECT id FROM users WHERE role = ? AND status = "active"';
    if ($schoolId !== null) {
        $sql .= ' AND school_id = ?';
    }
    $sql .= ' ORDER BY id ASC LIMIT 1';

    $stmt = $conn->prepare($sql);
    if ($schoolId !== null) {
        $stmt->bind_param('si', $role, $schoolId);
    } else {
        $stmt->bind_param('s', $role);
    }
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row) {
        app_create_notification((int)$row['id'], $title, $message, null, null, 'approval');
    }
}

function workflow_set_query_statuses(int $proposalId, string $roleName, string $queryText): void
{
    global $conn;

    if (!app_has_explicit_approval_flow()) {
        return;
    }

    $column = app_role_to_approval_column($roleName);
    if ($column) {
        workflow_update_explicit_status($proposalId, $column, 'Query Raised');
    }

    $stmt = $conn->prepare('UPDATE proposals SET overall_status = "query_raised" WHERE id = ?');
    $stmt->bind_param('i', $proposalId);
    $stmt->execute();
    $stmt->close();
}

function workflow_sync_admin_clearance(int $proposalId): void
{
    if (!app_has_explicit_approval_flow()) {
        return;
    }

    $proposal = workflow_fetch_proposal_row($proposalId);
    if (!$proposal) {
        return;
    }

    if ((int)($proposal['current_approval_level'] ?? 0) !== 5) {
        return;
    }

    if (!workflow_explicit_admin_fields_complete($proposal)) {
        return;
    }

    global $conn;

    $stmt = $conn->prepare('UPDATE proposals SET current_approval_level = 6, overall_status = "under_deputy_registrar_review" WHERE id = ?');
    $stmt->bind_param('i', $proposalId);
    $stmt->execute();
    $stmt->close();

    workflow_notify_first_user_by_role('deputy_registrar', 'Proposal Ready for Dy Registrar', 'All required admin office tasks for this proposal are complete.', (int)$proposal['school_id']);
}

function workflow_handle_explicit_action(int $proposalId, int $actorId, string $actorRole, string $action, string $remarks, ?string $queryText = null, ?string $deadline = null): void
{
    global $conn;

    $proposal = workflow_fetch_proposal_row($proposalId);
    if (!$proposal) {
        throw new RuntimeException('Proposal not found.');
    }

    $column = app_role_to_approval_column($actorRole);
    if (!$column) {
        throw new RuntimeException('This role cannot act on the explicit approval flow.');
    }

    $currentLevel = (int)($proposal['current_approval_level'] ?? 1);
    $roleLevel = app_approval_level_for_role($actorRole) ?? 0;

    if ($action === 'reject') {
        workflow_update_explicit_status($proposalId, $column, 'Rejected');
        $stmt = $conn->prepare('UPDATE proposals SET overall_status = "rejected", current_approval_level = ? WHERE id = ?');
        $stmt->bind_param('ii', $currentLevel, $proposalId);
        $stmt->execute();
        $stmt->close();
        workflow_log_action($proposalId, $actorId, $actorRole, 'rejected', $remarks);
        $owner = (int)($proposal['submitted_by'] ?? 0);
        if ($owner > 0) {
            app_create_notification($owner, 'Proposal Rejected', app_role_label($actorRole) . ' rejected your proposal. Reason: ' . $remarks, $proposalId, null, 'rejected');
        }
        return;
    }

    if ($action === 'query') {
        workflow_set_query_statuses($proposalId, $actorRole, $queryText ?? $remarks);
        $stmt = $conn->prepare('UPDATE proposals SET overall_status = "query_raised", current_approval_level = ? WHERE id = ?');
        $stmt->bind_param('ii', $currentLevel, $proposalId);
        $stmt->execute();
        $stmt->close();
        $owner = (int)($proposal['submitted_by'] ?? 0);
        if ($owner > 0) {
            app_create_notification($owner, 'Query Raised On Proposal', app_role_label($actorRole) . ' raised a query: ' . ($queryText ?? $remarks), $proposalId, null, 'query');
        }
        if (app_table_exists('queries') && $owner > 0) {
            $queryStmt = $conn->prepare('INSERT INTO queries (proposal_id, raised_by, raised_to, role_name, query_text, deadline, status) VALUES (?, ?, ?, ?, ?, ?, "open")');
            $queryStmt->bind_param('iiisss', $proposalId, $actorId, $owner, $actorRole, $queryText, $deadline);
            $queryStmt->execute();
            $queryStmt->close();
        }
        workflow_log_action($proposalId, $actorId, $actorRole, 'query_raised', $queryText ?? $remarks);
        return;
    }

    if ($action !== 'approve') {
        throw new InvalidArgumentException('Invalid action.');
    }

    workflow_update_explicit_status($proposalId, $column, 'Approved');
    workflow_log_action($proposalId, $actorId, $actorRole, 'approved', $remarks);

    if ($actorRole === 'faculty_mentor') {
        $stmt = $conn->prepare('UPDATE proposals SET current_approval_level = 2, overall_status = "under_president_vc_review", president_status = "Pending" WHERE id = ?');
        $stmt->bind_param('i', $proposalId);
        $stmt->execute();
        $stmt->close();
        workflow_notify_first_user_by_role('president_vc', 'Proposal Awaiting Approval', 'A proposal is ready for President / VC review.', (int)$proposal['school_id']);
        return;
    }

    if ($actorRole === 'president_vc') {
        $stmt = $conn->prepare('UPDATE proposals SET current_approval_level = 3, overall_status = "under_gs_treasurer_review", gs_treasurer_status = "Pending" WHERE id = ?');
        $stmt->bind_param('i', $proposalId);
        $stmt->execute();
        $stmt->close();
        workflow_notify_first_user_by_role('gs_treasurer', 'Proposal Awaiting Approval', 'A proposal is ready for GS / Treasurer review.', (int)$proposal['school_id']);
        return;
    }

    if ($actorRole === 'gs_treasurer') {
        $stmt = $conn->prepare('UPDATE proposals SET current_approval_level = 4, overall_status = "under_school_head_review", school_head_status = "Pending" WHERE id = ?');
        $stmt->bind_param('i', $proposalId);
        $stmt->execute();
        $stmt->close();
        workflow_notify_first_user_by_role('school_head', 'Proposal Awaiting Approval', 'A proposal is ready for School Head review.', (int)$proposal['school_id']);
        return;
    }

    if ($actorRole === 'school_head') {
        $adminRoles = workflow_proposal_service_roles($proposalId, (float)($proposal['budget_total'] ?? 0));
        $setParts = [
            'current_approval_level = 5',
            'overall_status = "under_admin_office_review"',
            'admin_officer_status = "Pending"'
        ];

        $explicitFields = [
            'it_team_status' => in_array('it_team', $adminRoles, true) ? 'Pending' : 'Not Required',
            'housekeeping_status' => in_array('housekeeping', $adminRoles, true) ? 'Pending' : 'Not Required',
            'security_status' => in_array('security_officer', $adminRoles, true) ? 'Pending' : 'Not Required',
            'purchase_status' => in_array('purchase_officer', $adminRoles, true) ? 'Pending' : 'Not Required',
            'accounts_status' => in_array('accounts_officer', $adminRoles, true) ? 'Pending' : 'Not Required'
        ];

        $sql = 'UPDATE proposals SET ' . implode(', ', $setParts);
        foreach ($explicitFields as $column => $status) {
            $sql .= ', ' . $column . ' = "' . $status . '"';
        }
        $sql .= ' WHERE id = ?';
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $proposalId);
        $stmt->execute();
        $stmt->close();

        if (app_table_exists('department_tasks')) {
            $names = [
                'it_team' => 'Projector / IT Support',
                'housekeeping' => 'Housekeeping Staff',
                'food_admin' => 'Food / Admin Side',
                'security_officer' => 'Security Staff',
                'purchase_officer' => 'Purchase Items',
                'accounts_officer' => 'Budget Review'
            ];
            foreach ($adminRoles as $adminRole) {
                if (!isset($names[$adminRole])) {
                    continue;
                }
                $roleName = $adminRole;
                $stmt = $conn->prepare('SELECT id FROM users WHERE role = ? AND status = "active" ORDER BY id ASC LIMIT 1');
                $stmt->bind_param('s', $roleName);
                $stmt->execute();
                $userRow = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                $assignedId = $userRow ? (int)$userRow['id'] : null;
                if ($assignedId === null) {
                    $taskStmt = $conn->prepare('INSERT INTO department_tasks (proposal_id, department_role, assigned_user_id, status) VALUES (?, ?, NULL, "pending")');
                    $taskStmt->bind_param('is', $proposalId, $roleName);
                } else {
                    $taskStmt = $conn->prepare('INSERT INTO department_tasks (proposal_id, department_role, assigned_user_id, status) VALUES (?, ?, ?, "pending")');
                    $taskStmt->bind_param('isi', $proposalId, $roleName, $assignedId);
                }
                $taskStmt->execute();
                $taskStmt->close();

                if ($assignedId) {
                    app_create_notification($assignedId, 'New Service Clearance Request', 'Proposal requires ' . app_role_label($roleName) . ' clearance.', $proposalId, null, 'task');
                }
            }
        }

        $adminOfficeStmt = $conn->prepare('SELECT id FROM users WHERE role = "admin_office" AND status = "active" ORDER BY id ASC LIMIT 1');
        $adminOfficeStmt->execute();
        $adminOffice = $adminOfficeStmt->get_result()->fetch_assoc();
        $adminOfficeStmt->close();
        if ($adminOffice) {
            app_create_notification((int)$adminOffice['id'], 'Proposal Ready for Admin Office', 'A proposal has reached the Admin Office stage.', $proposalId, null, 'approval');
        }
        return;
    }

    if (in_array($actorRole, ['admin_office', 'it_team', 'housekeeping', 'security_officer', 'purchase_officer', 'accounts_officer'], true)) {
        $stmt = $conn->prepare('UPDATE proposals SET ' . $column . ' = "Approved" WHERE id = ?');
        $stmt->bind_param('i', $proposalId);
        $stmt->execute();
        $stmt->close();
        workflow_sync_admin_clearance($proposalId);
        return;
    }

    if ($actorRole === 'deputy_registrar') {
        $stmt = $conn->prepare('UPDATE proposals SET current_approval_level = 6, overall_status = "approved", dy_registrar_status = "Approved" WHERE id = ?');
        $stmt->bind_param('i', $proposalId);
        $stmt->execute();
        $stmt->close();
        workflow_promote_to_event($proposalId);
        return;
    }

    if ($actorRole === 'dy_director') {
        $stmt = $conn->prepare('UPDATE proposals SET current_approval_level = 7, overall_status = "approved", dy_director_status = "Approved" WHERE id = ?');
        $stmt->bind_param('i', $proposalId);
        $stmt->execute();
        $stmt->close();
        workflow_promote_to_event($proposalId);
        return;
    }

    if ($actorRole === 'director') {
        $stmt = $conn->prepare('UPDATE proposals SET director_status = "Approved", current_approval_level = 8, overall_status = "approved" WHERE id = ?');
        $stmt->bind_param('i', $proposalId);
        $stmt->execute();
        $stmt->close();
        workflow_promote_to_event($proposalId);
        return;
    }
}

function workflow_derive_departments(array $serviceNames, float $budgetTotal): array
{
    $map = app_service_to_department_role();
    $roles = [];

    foreach ($serviceNames as $serviceName) {
        if (isset($map[$serviceName])) {
            $roles[] = $map[$serviceName];
        }

        if (stripos($serviceName, 'sports') !== false || stripos($serviceName, 'ground') !== false || stripos($serviceName, 'stadium') !== false) {
            $roles[] = 'sports_dept';
        }
        if (stripos($serviceName, 'housekeeping') !== false) {
            $roles[] = 'housekeeping';
        }
        if (stripos($serviceName, 'security') !== false) {
            $roles[] = 'security_officer';
        }
        if (stripos($serviceName, 'food') !== false || stripos($serviceName, 'snack') !== false || stripos($serviceName, 'lunch') !== false || stripos($serviceName, 'dinner') !== false) {
            $roles[] = 'food_admin';
        }
        if (stripos($serviceName, 'projector') !== false || stripos($serviceName, 'sound') !== false || stripos($serviceName, 'it support') !== false || stripos($serviceName, 'it') !== false) {
            $roles[] = 'it_team';
        }
        if (stripos($serviceName, 'camera') !== false || stripos($serviceName, 'purchase') !== false || stripos($serviceName, 'bouquet') !== false || stripos($serviceName, 'memento') !== false || stripos($serviceName, 'medal') !== false || stripos($serviceName, 'certificate') !== false || stripos($serviceName, 'lamp') !== false || stripos($serviceName, 'transport') !== false) {
            $roles[] = 'purchase_officer';
        }
    }

    if ($budgetTotal > 0) {
        $roles[] = 'accounts_officer';
    }

    return array_values(array_unique($roles));
}

function workflow_create_department_tasks(int $proposalId, int $schoolId, array $departmentRoles): void
{
    global $conn;

    if (empty($departmentRoles)) {
        return;
    }

    $findUserStmt = $conn->prepare('SELECT id FROM users WHERE role = ? AND school_id = ? AND status = "active" ORDER BY id ASC LIMIT 1');

    foreach ($departmentRoles as $role) {
        $assignedId = null;
        $findUserStmt->bind_param('si', $role, $schoolId);
        $findUserStmt->execute();
        $result = $findUserStmt->get_result();
        $row = $result->fetch_assoc();
        if ($row) {
            $assignedId = (int) $row['id'];
        }

        if ($assignedId === null) {
            $insertTaskStmt = $conn->prepare('INSERT INTO department_tasks (proposal_id, department_role, assigned_user_id, status) VALUES (?, ?, NULL, "pending")');
            $insertTaskStmt->bind_param('is', $proposalId, $role);
            $insertTaskStmt->execute();
            $insertTaskStmt->close();
        } else {
            $insertTaskStmt = $conn->prepare('INSERT INTO department_tasks (proposal_id, department_role, assigned_user_id, status) VALUES (?, ?, ?, "pending")');
            $insertTaskStmt->bind_param('isi', $proposalId, $role, $assignedId);
            $insertTaskStmt->execute();
            $insertTaskStmt->close();
        }

        if ($assignedId) {
            app_create_notification(
                $assignedId,
                'New Department Task',
                'A proposal requires ' . app_role_label($role) . ' clearance.',
                $proposalId,
                null,
                'task'
            );
        }
    }

    $findUserStmt->close();
}

function workflow_log_action(int $proposalId, int $actedBy, string $roleName, string $actionType, string $remarks = ''): void
{
    global $conn;

    $roleName = app_normalize_role($roleName);

    $stmt = $conn->prepare('INSERT INTO approval_logs (proposal_id, acted_by, role_name, action_type, remarks) VALUES (?, ?, ?, ?, ?)');
    $stmt->bind_param('iisss', $proposalId, $actedBy, $roleName, $actionType, $remarks);
    $stmt->execute();
    $stmt->close();

    $summary = match ($actionType) {
        'submitted' => app_role_label($roleName) . ' submitted the white paper.',
        'approved' => app_role_label($roleName) . ' approved the white paper.',
        'rejected' => app_role_label($roleName) . ' rejected the white paper.',
        'query_raised' => app_role_label($roleName) . ' raised a query on the white paper.',
        'resubmitted' => 'Club Head resubmitted the white paper after response.',
        'forwarded' => app_role_label($roleName) . ' completed a service clearance action.',
        default => app_role_label($roleName) . ' performed a workflow action.',
    };

    if ($remarks !== '') {
        $summary .= ' Remarks: ' . $remarks;
    }

    workflow_log_activity($actedBy, $roleName, $actionType, $summary, $proposalId, null);
}

function workflow_get_next_pending_step(int $proposalId): ?array
{
    global $conn;

    $stmt = $conn->prepare('SELECT id, step_order, role_name, approver_user_id FROM approval_workflow_steps WHERE proposal_id = ? AND status = "pending" ORDER BY step_order ASC LIMIT 1');
    $stmt->bind_param('i', $proposalId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

function workflow_update_current_stage(int $proposalId, string $role): void
{
    global $conn;

    $stageMap = app_stage_map();
    $newStatus = $stageMap[$role] ?? 'submitted';

    $stmt = $conn->prepare('UPDATE proposals SET current_stage = ?, overall_status = ? WHERE id = ?');
    $stmt->bind_param('ssi', $role, $newStatus, $proposalId);
    $stmt->execute();
    $stmt->close();
}

function workflow_mark_main_approved_and_progress(int $proposalId, int $actorId, string $actorRole, string $remarks): void
{
    global $conn;

    $conn->begin_transaction();

    try {
        $stepStmt = $conn->prepare('UPDATE approval_workflow_steps SET status = "approved", remarks = ?, acted_at = NOW() WHERE proposal_id = ? AND role_name = ? AND status = "pending" LIMIT 1');
        $stepStmt->bind_param('sis', $remarks, $proposalId, $actorRole);
        $stepStmt->execute();
        $stepStmt->close();

        workflow_log_action($proposalId, $actorId, $actorRole, 'approved', $remarks);

        $next = workflow_get_next_pending_step($proposalId);
        if ($next) {
            workflow_update_current_stage($proposalId, $next['role_name']);
            if (!empty($next['approver_user_id'])) {
                app_create_notification(
                    (int) $next['approver_user_id'],
                    'Proposal Awaiting Your Approval',
                    'A proposal moved to your approval stage (' . app_role_label($next['role_name']) . ').',
                    $proposalId,
                    null,
                    'approval'
                );
            }
        } else {
            $status = 'approved';
            $stage = 'director';
            $stmt = $conn->prepare('UPDATE proposals SET overall_status = ?, current_stage = ? WHERE id = ?');
            $stmt->bind_param('ssi', $status, $stage, $proposalId);
            $stmt->execute();
            $stmt->close();

            workflow_promote_to_event($proposalId);
        }

        $ownerStmt = $conn->prepare('SELECT submitted_by FROM proposals WHERE id = ? LIMIT 1');
        $ownerStmt->bind_param('i', $proposalId);
        $ownerStmt->execute();
        $owner = $ownerStmt->get_result()->fetch_assoc();
        $ownerStmt->close();

        if ($owner) {
            app_create_notification(
                (int)$owner['submitted_by'],
                'Proposal Updated',
                app_role_label($actorRole) . ' approved your proposal.',
                $proposalId,
                null,
                'approval'
            );
        }

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}

function workflow_mark_main_rejected(int $proposalId, int $actorId, string $actorRole, string $remarks): void
{
    global $conn;

    $conn->begin_transaction();

    try {
        $stepStmt = $conn->prepare('UPDATE approval_workflow_steps SET status = "rejected", remarks = ?, acted_at = NOW() WHERE proposal_id = ? AND role_name = ? AND status = "pending" LIMIT 1');
        $stepStmt->bind_param('sis', $remarks, $proposalId, $actorRole);
        $stepStmt->execute();
        $stepStmt->close();

        $status = 'rejected';
        $stage = $actorRole;
        $proposalStmt = $conn->prepare('UPDATE proposals SET overall_status = ?, current_stage = ? WHERE id = ?');
        $proposalStmt->bind_param('ssi', $status, $stage, $proposalId);
        $proposalStmt->execute();
        $proposalStmt->close();

        workflow_log_action($proposalId, $actorId, $actorRole, 'rejected', $remarks);

        $ownerStmt = $conn->prepare('SELECT submitted_by FROM proposals WHERE id = ? LIMIT 1');
        $ownerStmt->bind_param('i', $proposalId);
        $ownerStmt->execute();
        $owner = $ownerStmt->get_result()->fetch_assoc();
        $ownerStmt->close();

        if ($owner) {
            app_create_notification(
                (int)$owner['submitted_by'],
                'Proposal Rejected',
                app_role_label($actorRole) . ' rejected your proposal. Reason: ' . $remarks,
                $proposalId,
                null,
                'rejected'
            );
        }

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}

function workflow_mark_main_query(int $proposalId, int $actorId, string $actorRole, int $submittedBy, string $queryText, ?string $deadline): void
{
    global $conn;

    $conn->begin_transaction();

    try {
        $stepStmt = $conn->prepare('UPDATE approval_workflow_steps SET status = "query_raised", remarks = ?, acted_at = NOW() WHERE proposal_id = ? AND role_name = ? AND status = "pending" LIMIT 1');
        $stepStmt->bind_param('sis', $queryText, $proposalId, $actorRole);
        $stepStmt->execute();
        $stepStmt->close();

        $status = 'query_raised';
        $stage = $actorRole;
        $proposalStmt = $conn->prepare('UPDATE proposals SET overall_status = ?, current_stage = ? WHERE id = ?');
        $proposalStmt->bind_param('ssi', $status, $stage, $proposalId);
        $proposalStmt->execute();
        $proposalStmt->close();

        $queryStmt = $conn->prepare('INSERT INTO queries (proposal_id, raised_by, raised_to, role_name, query_text, deadline, status) VALUES (?, ?, ?, ?, ?, ?, "open")');
        $queryStmt->bind_param('iiisss', $proposalId, $actorId, $submittedBy, $actorRole, $queryText, $deadline);
        $queryStmt->execute();
        $queryStmt->close();

        workflow_log_action($proposalId, $actorId, $actorRole, 'query_raised', $queryText);

        app_create_notification(
            $submittedBy,
            'Query Raised On Proposal',
            app_role_label($actorRole) . ' raised a query. Please update and resubmit your proposal.',
            $proposalId,
            null,
            'query'
        );

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}

function workflow_resubmit_from_query(int $proposalId, int $submittedBy): void
{
    global $conn;

    $conn->begin_transaction();

    try {
        $queryStmt = $conn->prepare('SELECT id, role_name, raised_by FROM queries WHERE proposal_id = ? AND status = "open" ORDER BY id DESC LIMIT 1');
        $queryStmt->bind_param('i', $proposalId);
        $queryStmt->execute();
        $query = $queryStmt->get_result()->fetch_assoc();
        $queryStmt->close();

        if (!$query) {
            throw new RuntimeException('No open query found for resubmission.');
        }

        $updateQueryStmt = $conn->prepare('UPDATE queries SET status = "responded", responded_at = NOW() WHERE id = ?');
        $queryId = (int) $query['id'];
        $updateQueryStmt->bind_param('i', $queryId);
        $updateQueryStmt->execute();
        $updateQueryStmt->close();

        $resetStepStmt = $conn->prepare('UPDATE approval_workflow_steps SET status = "pending", remarks = NULL, acted_at = NULL WHERE proposal_id = ? AND role_name = ? LIMIT 1');
        $roleName = (string) $query['role_name'];
        $resetStepStmt->bind_param('is', $proposalId, $roleName);
        $resetStepStmt->execute();
        $resetStepStmt->close();

        workflow_update_current_stage($proposalId, $roleName);
        $status = 'resubmitted';
        $proposalStmt = $conn->prepare('UPDATE proposals SET overall_status = ? WHERE id = ?');
        $proposalStmt->bind_param('si', $status, $proposalId);
        $proposalStmt->execute();
        $proposalStmt->close();

        workflow_log_action($proposalId, $submittedBy, 'club_head', 'resubmitted', 'Proposal resubmitted after query.');

        app_create_notification(
            (int) $query['raised_by'],
            'Proposal Resubmitted',
            'Club Head has resubmitted the proposal after query response.',
            $proposalId,
            null,
            'resubmitted'
        );

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}

function workflow_promote_to_event(int $proposalId): void
{
    global $conn;

    $stmt = $conn->prepare('SELECT event_name, event_date, start_time, end_time, venue_id, school_id, club_id FROM proposals WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $proposalId);
    $stmt->execute();
    $proposal = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$proposal) {
        return;
    }

    $eventId = 0;
    if (app_table_exists('events')) {
        $existingStmt = $conn->prepare('SELECT id FROM events WHERE proposal_id = ? ORDER BY id DESC LIMIT 1');
        $existingStmt->bind_param('i', $proposalId);
        $existingStmt->execute();
        $existingEvent = $existingStmt->get_result()->fetch_assoc();
        $existingStmt->close();

        if ($existingEvent) {
            return;
        }
    }

    if ($eventId <= 0) {
        $insertEventStmt = $conn->prepare('INSERT INTO events (proposal_id, event_name, event_date, start_time, end_time, venue_id, registration_required, registration_deadline, max_participants, event_status) VALUES (?, ?, ?, ?, ?, ?, 1, DATE_SUB(?, INTERVAL 1 DAY), 300, "upcoming")');

        $eventDate = $proposal['event_date'];
        $insertEventStmt->bind_param(
            'issssis',
            $proposalId,
            $proposal['event_name'],
            $eventDate,
            $proposal['start_time'],
            $proposal['end_time'],
            $proposal['venue_id'],
            $eventDate
        );
        $insertEventStmt->execute();
        $eventId = (int) $insertEventStmt->insert_id;
        $insertEventStmt->close();
    }

    $ownerStmt = $conn->prepare('SELECT submitted_by FROM proposals WHERE id = ? LIMIT 1');
    $ownerStmt->bind_param('i', $proposalId);
    $ownerStmt->execute();
    $owner = $ownerStmt->get_result()->fetch_assoc();
    $ownerStmt->close();

    if (app_table_exists('resource_bookings')) {
        $bookingStmt = $conn->prepare('UPDATE resource_bookings SET booking_status = "confirmed" WHERE proposal_id = ?');
        $bookingStmt->bind_param('i', $proposalId);
        $bookingStmt->execute();
        $bookingStmt->close();
    }

    if ($owner) {
        app_create_notification(
            (int)$owner['submitted_by'],
            'Proposal Fully Approved',
            'Your proposal has completed all approvals and is now published as an event.',
            $proposalId,
            $eventId,
            'approved'
        );
    }

    if (!empty($owner['submitted_by'])) {
        workflow_log_activity((int)$owner['submitted_by'], 'club_head', 'event_published', 'Proposal published as event: ' . (string)$proposal['event_name'], $proposalId, $eventId);
    }

    $schoolId = (int)($proposal['school_id'] ?? 0);
    $clubId = (int)($proposal['club_id'] ?? 0);
    $notifyTargets = [];

    if (($owner['submitted_by'] ?? 0) > 0) {
        $notifyTargets[] = (int)$owner['submitted_by'];
    }
    if ($mentorId = app_school_role_user_id($schoolId, 'school_head')) {
        $notifyTargets[] = $mentorId;
    }
    if ($mentorId = app_school_role_user_id($schoolId, 'president_vc')) {
        $notifyTargets[] = $mentorId;
    }
    if ($mentorId = app_school_role_user_id($schoolId, 'gs_treasurer')) {
        $notifyTargets[] = $mentorId;
    }
    if (($clubHead = workflow_find_approver_for_role('faculty_mentor', $schoolId, $clubId)) !== null) {
        $notifyTargets[] = $clubHead;
    }
    if (($adminOfficeId = workflow_find_approver_for_role('admin_office', $schoolId, $clubId)) !== null) {
        $notifyTargets[] = $adminOfficeId;
    }
    if (($drId = workflow_find_approver_for_role('deputy_registrar', $schoolId, $clubId)) !== null) {
        $notifyTargets[] = $drId;
    }

    foreach (array_unique(array_filter($notifyTargets)) as $userId) {
        app_create_notification(
            (int)$userId,
            'Event Published',
            'A new event has been fully approved and published in the calendar.',
            $proposalId,
            $eventId,
            'approved'
        );
    }
}

function workflow_refresh_completed_events(): void
{
    global $conn;

    if (!app_table_exists('events')) {
        return;
    }

    $stmt = $conn->prepare('SELECT e.id, e.proposal_id, e.event_name, p.submitted_by, p.club_id, p.school_id FROM events e JOIN proposals p ON p.id = e.proposal_id WHERE e.event_status = "upcoming" AND e.event_date < CURDATE()');
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($rows as $row) {
        $eventId = (int)$row['id'];
        $proposalId = (int)$row['proposal_id'];
        $stmt = $conn->prepare('UPDATE events SET event_status = "completed" WHERE id = ?');
        $stmt->bind_param('i', $eventId);
        $stmt->execute();
        $stmt->close();

        if (($row['submitted_by'] ?? 0) > 0) {
            app_create_notification((int)$row['submitted_by'], 'Event Completed', 'Event completed. Please upload event report and event images.', $proposalId, $eventId, 'info');
        }

        if (!empty($row['submitted_by'])) {
            workflow_log_activity((int)$row['submitted_by'], 'club_head', 'event_completed', 'Event completed automatically: ' . (string)$row['event_name'], $proposalId, $eventId);
        }
    }
}

function workflow_review_chain_roles(): array
{
    return [
        'faculty_mentor',
        'gs_treasurer',
        'president_vc',
        'school_head',
        'it_team',
        'housekeeping',
        'food_admin',
        'sports_dept',
        'security_officer',
        'rector',
        'purchase_officer',
        'accounts_officer',
        'admin_office',
        'deputy_registrar',
        'deputy_director',
        'director',
    ];
}

function workflow_level_roles(): array
{
    return [
        1 => ['faculty_mentor'],
        2 => ['gs_treasurer'],
        3 => ['president_vc'],
        4 => ['school_head'],
        5 => ['it_team', 'housekeeping', 'food_admin', 'sports_dept'],
        6 => ['security_officer', 'rector', 'purchase_officer', 'accounts_officer', 'admin_office'],
        7 => ['deputy_registrar'],
        8 => ['deputy_director'],
        9 => ['director'],
    ];
}

function workflow_role_level(string $role): ?int
{
    return app_approval_level_for_role(app_normalize_role($role));
}

function workflow_required_service_roles(array $proposal): array
{
    $roles = [];
    $proposalId = (int) ($proposal['id'] ?? 0);
    $selectedServices = [];

    if ($proposalId > 0 && app_table_exists('proposal_service_requirements')) {
        global $conn;
        $stmt = $conn->prepare('SELECT service_name FROM proposal_service_requirements WHERE proposal_id = ? AND required = 1');
        $stmt->bind_param('i', $proposalId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $selectedServices = array_map(static fn(array $row): string => strtolower(trim((string) ($row['service_name'] ?? ''))), $rows);
    }

    $derivedRoles = workflow_derive_departments($selectedServices, (float) ($proposal['budget_total'] ?? 0));
    $allowedOptionalRoles = ['it_team', 'housekeeping', 'food_admin', 'sports_dept', 'security_officer', 'purchase_officer', 'accounts_officer'];

    foreach ($derivedRoles as $derivedRole) {
        $normalized = app_normalize_role((string) $derivedRole);
        if (in_array($normalized, $allowedOptionalRoles, true)) {
            $roles[] = $normalized;
        }
    }

    return array_values(array_unique($roles));
}

function workflow_proposal_requires_role(array $proposal, string $role, array $requiredServiceRoles): bool
{
    $role = app_normalize_role($role);

    return match ($role) {
        'faculty_mentor', 'gs_treasurer', 'president_vc', 'school_head', 'rector', 'admin_office', 'deputy_registrar', 'deputy_director', 'director' => true,
        'it_team', 'housekeeping', 'food_admin', 'sports_dept', 'security_officer', 'purchase_officer', 'accounts_officer' => in_array($role, $requiredServiceRoles, true),
        default => false,
    };
}

function workflow_fetch_proposal_steps(int $proposalId): array
{
    global $conn;

    if (!app_table_exists('approval_workflow_steps')) {
        return [];
    }

    $stmt = $conn->prepare('SELECT id, proposal_id, step_order, role_name, approver_user_id, status, remarks, acted_at FROM approval_workflow_steps WHERE proposal_id = ? ORDER BY step_order ASC, id ASC');
    $stmt->bind_param('i', $proposalId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $rows;
}

function workflow_seed_proposal_steps(int $proposalId): void
{
    global $conn;

    if (!app_table_exists('approval_workflow_steps') || !app_table_exists('proposals')) {
        return;
    }

    $proposal = workflow_fetch_proposal_row($proposalId);
    if (!$proposal) {
        return;
    }

    $requiredServiceRoles = workflow_required_service_roles($proposal);
    $chain = workflow_review_chain_roles();

    $conn->begin_transaction();

    try {
        $deleteStmt = $conn->prepare('DELETE FROM approval_workflow_steps WHERE proposal_id = ?');
        $deleteStmt->bind_param('i', $proposalId);
        $deleteStmt->execute();
        $deleteStmt->close();

        foreach ($chain as $index => $roleName) {
            $roleName = app_normalize_role((string) $roleName);
            $stepOrder = $index + 1;
            $approverId = workflow_find_approver_for_role($roleName, (int) $proposal['school_id'], (int) $proposal['club_id']);
            $required = workflow_proposal_requires_role($proposal, $roleName, $requiredServiceRoles);
            $status = $required ? 'pending' : 'not_required';

            if ($approverId !== null && $approverId > 0) {
                $stmt = $conn->prepare('INSERT INTO approval_workflow_steps (proposal_id, step_order, role_name, approver_user_id, status) VALUES (?, ?, ?, ?, ?)');
                $stmt->bind_param('iisis', $proposalId, $stepOrder, $roleName, $approverId, $status);
            } else {
                $stmt = $conn->prepare('INSERT INTO approval_workflow_steps (proposal_id, step_order, role_name, approver_user_id, status) VALUES (?, ?, ?, NULL, ?)');
                $stmt->bind_param('iiss', $proposalId, $stepOrder, $roleName, $status);
            }
            $stmt->execute();
            $stmt->close();
        }

        $updateStmt = $conn->prepare('UPDATE proposals SET current_stage = "faculty_mentor", overall_status = "under_faculty_mentor_review", current_approval_level = 1 WHERE id = ?');
        $updateStmt->bind_param('i', $proposalId);
        $updateStmt->execute();
        $updateStmt->close();

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }

    workflow_notify_level_reviewers($proposalId, 1);
}

function workflow_primary_role_for_level(int $proposalId, int $level): string
{
    $defaultRoles = workflow_level_roles()[$level] ?? [];
    $steps = workflow_fetch_proposal_steps($proposalId);

    foreach ($steps as $step) {
        $stepLevel = workflow_role_level((string) ($step['role_name'] ?? ''));
        $status = strtolower((string) ($step['status'] ?? ''));
        if ($stepLevel === $level && in_array($status, ['pending', 'resubmitted'], true)) {
            return (string) $step['role_name'];
        }
    }

    return $defaultRoles[0] ?? 'faculty_mentor';
}

function workflow_first_open_level(int $proposalId): ?int
{
    $steps = workflow_fetch_proposal_steps($proposalId);
    $levels = [];

    foreach ($steps as $step) {
        $status = strtolower((string) ($step['status'] ?? ''));
        if (!in_array($status, ['pending', 'resubmitted', 'query_raised', 'rejected'], true)) {
            continue;
        }

        $stepLevel = workflow_role_level((string) ($step['role_name'] ?? ''));
        if ($stepLevel !== null) {
            $levels[] = $stepLevel;
        }
    }

    if (empty($levels)) {
        return null;
    }

    sort($levels);
    return $levels[0];
}

function workflow_level_complete(int $proposalId, int $level): bool
{
    $steps = workflow_fetch_proposal_steps($proposalId);
    $requiredFound = false;

    foreach ($steps as $step) {
        if (workflow_role_level((string) ($step['role_name'] ?? '')) !== $level) {
            continue;
        }

        $status = strtolower((string) ($step['status'] ?? ''));
        if (in_array($status, ['not_required', 'skipped', 'locked'], true)) {
            continue;
        }

        $requiredFound = true;
        if ($status !== 'approved') {
            return false;
        }
    }

    if (!$requiredFound) {
        return true;
    }

    return true;
}

function workflow_notify_level_reviewers(int $proposalId, int $level): void
{
    global $conn;

    if (!app_table_exists('approval_workflow_steps')) {
        return;
    }

    $proposal = workflow_fetch_proposal_row($proposalId);
    if (!$proposal) {
        return;
    }

    $steps = workflow_fetch_proposal_steps($proposalId);
    $notified = [];

    foreach ($steps as $step) {
        $roleName = app_normalize_role((string) ($step['role_name'] ?? ''));
        if (workflow_role_level($roleName) !== $level) {
            continue;
        }

        $status = strtolower((string) ($step['status'] ?? ''));
        if (!in_array($status, ['pending', 'resubmitted'], true)) {
            continue;
        }

        if (app_is_department_role($roleName) && app_table_exists('department_tasks')) {
            continue;
        }

        $approverId = (int) ($step['approver_user_id'] ?? 0);
        if ($approverId <= 0) {
            $approverId = (int) (workflow_find_approver_for_role($roleName, (int) ($proposal['school_id'] ?? 0), (int) ($proposal['club_id'] ?? 0)) ?? 0);
            if ($approverId > 0) {
                $stmt = $conn->prepare('UPDATE approval_workflow_steps SET approver_user_id = ? WHERE id = ? AND (approver_user_id IS NULL OR approver_user_id = 0)');
                $stepId = (int) ($step['id'] ?? 0);
                $stmt->bind_param('ii', $approverId, $stepId);
                $stmt->execute();
                $stmt->close();
            }
        }

        if ($approverId <= 0 || isset($notified[$approverId])) {
            continue;
        }

        $notified[$approverId] = true;
        app_create_notification(
            $approverId,
            'Proposal Awaiting Your Approval',
            'White paper "' . (string) ($proposal['event_name'] ?? 'Proposal') . '" is ready for ' . app_role_label($roleName) . ' review.',
            $proposalId,
            null,
            'approval'
        );
    }
}

function workflow_sync_department_task_for_role(int $proposalId, string $roleName, string $targetStatus = 'pending', string $remarks = ''): void
{
    global $conn;

    $normalizedRole = app_normalize_role($roleName);
    if (!app_table_exists('department_tasks') || !app_is_department_role($normalizedRole)) {
        return;
    }

    $proposal = workflow_fetch_proposal_row($proposalId);
    if (!$proposal) {
        return;
    }

    $status = strtolower($targetStatus);
    $mappedStatus = match ($status) {
        'approved' => 'approved',
        'completed' => 'completed',
        'rejected' => 'rejected',
        default => 'pending',
    };

    $approverId = (int) (workflow_find_approver_for_role($normalizedRole, (int) ($proposal['school_id'] ?? 0), (int) ($proposal['club_id'] ?? 0)) ?? 0);

    $existingStmt = $conn->prepare('SELECT id, status, assigned_user_id FROM department_tasks WHERE proposal_id = ? AND department_role = ? ORDER BY id DESC LIMIT 1');
    $existingStmt->bind_param('is', $proposalId, $normalizedRole);
    $existingStmt->execute();
    $existingTask = $existingStmt->get_result()->fetch_assoc();
    $existingStmt->close();

    if (!$existingTask) {
        if ($approverId > 0) {
            $insertStmt = $conn->prepare('INSERT INTO department_tasks (proposal_id, department_role, assigned_user_id, status, remarks) VALUES (?, ?, ?, ?, ?)');
            $insertStmt->bind_param('isiss', $proposalId, $normalizedRole, $approverId, $mappedStatus, $remarks);
        } else {
            $insertStmt = $conn->prepare('INSERT INTO department_tasks (proposal_id, department_role, assigned_user_id, status, remarks) VALUES (?, ?, NULL, ?, ?)');
            $insertStmt->bind_param('isss', $proposalId, $normalizedRole, $mappedStatus, $remarks);
        }
        $insertStmt->execute();
        $insertStmt->close();
    } else {
        $taskId = (int) $existingTask['id'];
        $assignedUserId = (int) ($existingTask['assigned_user_id'] ?? 0);
        $assign = $assignedUserId > 0 ? $assignedUserId : ($approverId > 0 ? $approverId : 0);

        if ($assign > 0) {
            $updateStmt = $conn->prepare('UPDATE department_tasks SET assigned_user_id = ?, status = ?, remarks = ?, acted_at = CASE WHEN ? IN ("approved", "rejected", "completed") THEN NOW() ELSE NULL END WHERE id = ?');
            $updateStmt->bind_param('isssi', $assign, $mappedStatus, $remarks, $mappedStatus, $taskId);
        } else {
            $updateStmt = $conn->prepare('UPDATE department_tasks SET assigned_user_id = NULL, status = ?, remarks = ?, acted_at = CASE WHEN ? IN ("approved", "rejected", "completed") THEN NOW() ELSE NULL END WHERE id = ?');
            $updateStmt->bind_param('sssi', $mappedStatus, $remarks, $mappedStatus, $taskId);
        }
        $updateStmt->execute();
        $updateStmt->close();
    }

    if ($mappedStatus === 'pending' && $approverId > 0) {
        app_create_notification(
            $approverId,
            'New Service Clearance Request',
            'Proposal requires ' . app_role_label($normalizedRole) . ' clearance.',
            $proposalId,
            null,
            'task'
        );
    }
}

function workflow_sync_department_tasks_for_level(int $proposalId, int $level): void
{
    $levelRoles = workflow_level_roles()[$level] ?? [];
    if (empty($levelRoles)) {
        return;
    }

    $steps = workflow_fetch_proposal_steps($proposalId);
    foreach ($steps as $step) {
        $roleName = app_normalize_role((string) ($step['role_name'] ?? ''));
        if (!in_array($roleName, $levelRoles, true) || !app_is_department_role($roleName)) {
            continue;
        }

        $status = strtolower((string) ($step['status'] ?? 'pending'));
        if (in_array($status, ['pending', 'resubmitted'], true)) {
            workflow_sync_department_task_for_role($proposalId, $roleName, 'pending');
        }
    }
}

function workflow_advance_to_next_level_if_ready(int $proposalId, int $completedLevel): void
{
    global $conn;

    if (!workflow_level_complete($proposalId, $completedLevel)) {
        return;
    }

    $nextLevel = workflow_first_open_level($proposalId);
    if ($nextLevel === null) {
        $finalStage = 'director';
        $finalStatus = 'approved';
        $stmt = $conn->prepare('UPDATE proposals SET current_stage = ?, overall_status = ?, current_approval_level = 9 WHERE id = ?');
        $stmt->bind_param('ssi', $finalStage, $finalStatus, $proposalId);
        $stmt->execute();
        $stmt->close();

        workflow_promote_to_event($proposalId);
        return;
    }

    if ($nextLevel <= $completedLevel) {
        return;
    }

    $primaryRole = workflow_primary_role_for_level($proposalId, $nextLevel);
    $stageMap = app_stage_map();
    $overallStatus = $stageMap[$primaryRole] ?? 'under_review';

    $stmt = $conn->prepare('UPDATE proposals SET current_stage = ?, overall_status = ?, current_approval_level = ? WHERE id = ?');
    $stmt->bind_param('ssii', $primaryRole, $overallStatus, $nextLevel, $proposalId);
    $stmt->execute();
    $stmt->close();

    workflow_sync_department_tasks_for_level($proposalId, $nextLevel);
    workflow_notify_level_reviewers($proposalId, $nextLevel);
}

function workflow_get_current_actionable_step(int $proposalId, string $role, int $userId): ?array
{
    global $conn;

    if (!app_table_exists('approval_workflow_steps')) {
        return null;
    }

    $role = app_normalize_role($role);
    $roleLevel = workflow_role_level($role);
    $activeLevel = workflow_first_open_level($proposalId);
    if ($roleLevel === null || $activeLevel === null || $roleLevel !== $activeLevel) {
        return null;
    }

    $proposal = workflow_fetch_proposal_row($proposalId);
    $proposalStatus = strtolower((string) ($proposal['overall_status'] ?? ''));
    if (in_array($proposalStatus, ['query_raised', 'rejected_pending_response', 'locked'], true)) {
        return null;
    }

    if ($proposal && (int) ($proposal['current_approval_level'] ?? 0) !== $roleLevel) {
        return null;
    }

    $stmt = $conn->prepare('SELECT id, proposal_id, step_order, role_name, approver_user_id, status, remarks FROM approval_workflow_steps WHERE proposal_id = ? AND role_name = ? AND status IN ("pending", "resubmitted") AND (approver_user_id = ? OR approver_user_id IS NULL OR approver_user_id = 0) ORDER BY step_order ASC LIMIT 1');
    $stmt->bind_param('isi', $proposalId, $role, $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return null;
    }

    if (empty($row['approver_user_id'])) {
        $stepId = (int) $row['id'];
        $assignStmt = $conn->prepare('UPDATE approval_workflow_steps SET approver_user_id = ? WHERE id = ? AND (approver_user_id IS NULL OR approver_user_id = 0)');
        $assignStmt->bind_param('ii', $userId, $stepId);
        $assignStmt->execute();
        $assignStmt->close();
        $row['approver_user_id'] = $userId;
    }

    return $row;
}

function workflow_get_next_open_step(int $proposalId, int $afterStepOrder): ?array
{
    global $conn;

    if (!app_table_exists('approval_workflow_steps')) {
        return null;
    }

    $stmt = $conn->prepare('SELECT id, proposal_id, step_order, role_name, approver_user_id, status FROM approval_workflow_steps WHERE proposal_id = ? AND step_order > ? AND status IN ("pending", "resubmitted") ORDER BY step_order ASC LIMIT 1');
    $stmt->bind_param('ii', $proposalId, $afterStepOrder);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

function workflow_update_step_status(int $stepId, string $status, string $remarks = null): void
{
    global $conn;

    $stmt = $conn->prepare('UPDATE approval_workflow_steps SET status = ?, remarks = ?, acted_at = NOW() WHERE id = ?');
    $stmt->bind_param('ssi', $status, $remarks, $stepId);
    $stmt->execute();
    $stmt->close();
}

function workflow_mark_proposal_locked(int $proposalId, string $reason): void
{
    global $conn;

    $stmt = $conn->prepare('UPDATE proposals SET overall_status = "locked", is_locked = 1, locked_reason = ? WHERE id = ?');
    $stmt->bind_param('si', $reason, $proposalId);
    $stmt->execute();
    $stmt->close();

    if (app_table_exists('approval_workflow_steps')) {
        $stepStmt = $conn->prepare('UPDATE approval_workflow_steps SET status = "locked" WHERE proposal_id = ? AND status IN ("pending", "resubmitted", "query_raised", "rejected")');
        $stepStmt->bind_param('i', $proposalId);
        $stepStmt->execute();
        $stepStmt->close();
    }
}

function workflow_log_query_or_reject(int $proposalId, int $actorId, string $actorRole, string $type, string $message, ?int $stepId = null, ?int $raisedTo = null): void
{
    global $conn;

    if (!app_table_exists('queries')) {
        return;
    }

    $hasWorkflowStepId = app_column_exists('queries', 'workflow_step_id');
    $hasQueryType = app_column_exists('queries', 'query_type');
    $hasClubResponse = app_column_exists('queries', 'club_response');

    $columns = ['proposal_id', 'raised_by', 'raised_to', 'role_name', 'query_text', 'deadline', 'status'];
    $values = [$proposalId, $actorId, $raisedTo ?? 0, $actorRole, $message, null, 'open'];
    $types = 'iiissss';

    if ($hasWorkflowStepId) {
        $columns[] = 'workflow_step_id';
        $values[] = $stepId;
        $types .= 'i';
    }

    if ($hasQueryType) {
        $columns[] = 'query_type';
        $values[] = $type;
        $types .= 's';
    }

    if ($hasClubResponse) {
        $columns[] = 'club_response';
        $values[] = null;
        $types .= 's';
    }

    $placeholders = implode(', ', array_fill(0, count($columns), '?'));
    $sql = 'INSERT INTO queries (' . implode(', ', $columns) . ') VALUES (' . $placeholders . ')';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$values);
    $stmt->execute();
    $stmt->close();
}

function workflow_log_rejection(int $proposalId, int $stepId, int $actorId, string $actorRole, string $reason, int $rejectionCount): void
{
    global $conn;

    if (!app_table_exists('proposal_rejections')) {
        return;
    }

    $stmt = $conn->prepare('INSERT INTO proposal_rejections (proposal_id, workflow_step_id, rejected_by, role_name, rejection_reason, rejection_count) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->bind_param('iiissi', $proposalId, $stepId, $actorId, $actorRole, $reason, $rejectionCount);
    $stmt->execute();
    $stmt->close();
}

function workflow_log_response(int $proposalId, int $queryId, int $respondedBy, string $responseText, ?string $attachmentPath = null): void
{
    global $conn;

    if (!app_table_exists('proposal_responses')) {
        return;
    }

    $stmt = $conn->prepare('INSERT INTO proposal_responses (proposal_id, query_id, responded_by, response_text, attachment_path) VALUES (?, ?, ?, ?, ?)');
    $stmt->bind_param('iiiss', $proposalId, $queryId, $respondedBy, $responseText, $attachmentPath);
    $stmt->execute();
    $stmt->close();
}

function workflow_handle_review_action(int $proposalId, int $actorId, string $actorRole, string $action, string $remarks): void
{
    global $conn;

    $actorRole = app_normalize_role($actorRole);

    $proposal = workflow_fetch_proposal_row($proposalId);
    if (!$proposal) {
        throw new RuntimeException('Proposal not found.');
    }

    if (!empty($proposal['is_locked'])) {
        throw new RuntimeException('This white paper is locked. Please submit a new proposal.');
    }

    $step = workflow_get_current_actionable_step($proposalId, $actorRole, $actorId);
    if (!$step) {
        throw new RuntimeException('This proposal is not awaiting your action.');
    }

    $stepId = (int) $step['id'];
    $currentRole = (string) $step['role_name'];
    $currentLevel = workflow_role_level($currentRole) ?? (int) ($proposal['current_approval_level'] ?? 1);
    $ownerId = (int) ($proposal['submitted_by'] ?? 0);

    $conn->begin_transaction();

    try {
        if ($action === 'approve') {
            workflow_update_step_status($stepId, 'approved', $remarks);
            workflow_log_action($proposalId, $actorId, $actorRole, 'approved', $remarks);

            if (app_is_department_role($currentRole)) {
                workflow_sync_department_task_for_role($proposalId, $currentRole, 'approved', $remarks);
            }

            if (workflow_level_complete($proposalId, $currentLevel)) {
                workflow_advance_to_next_level_if_ready($proposalId, $currentLevel);
            } else {
                $stageMap = app_stage_map();
                $stageStatus = $stageMap[$currentRole] ?? 'under_review';
                $updateStmt = $conn->prepare('UPDATE proposals SET current_stage = ?, overall_status = ?, current_approval_level = ? WHERE id = ?');
                $updateStmt->bind_param('ssii', $currentRole, $stageStatus, $currentLevel, $proposalId);
                $updateStmt->execute();
                $updateStmt->close();
            }
        } elseif ($action === 'query') {
            if ($remarks === '') {
                throw new RuntimeException('Query reason is required.');
            }

            workflow_update_step_status($stepId, 'query_raised', $remarks);
            $updateStmt = $conn->prepare('UPDATE proposals SET overall_status = "query_raised", current_stage = ?, current_approval_level = ? WHERE id = ?');
            $updateStmt->bind_param('sii', $currentRole, $currentLevel, $proposalId);
            $updateStmt->execute();
            $updateStmt->close();

            workflow_log_query_or_reject($proposalId, $actorId, $actorRole, 'query', $remarks, $stepId, $ownerId);
            workflow_log_action($proposalId, $actorId, $actorRole, 'query_raised', $remarks);

            if (app_is_department_role($currentRole)) {
                workflow_sync_department_task_for_role($proposalId, $currentRole, 'pending', $remarks);
            }

            if ($ownerId > 0) {
                app_create_notification($ownerId, 'Query Raised On Proposal', app_role_label($actorRole) . ' raised a query: ' . $remarks, $proposalId, null, 'query');
            }
        } elseif ($action === 'reject') {
            if ($remarks === '') {
                throw new RuntimeException('Rejection reason is required.');
            }

            $rejectionCount = (int) ($proposal['rejection_count'] ?? 0) + 1;
            workflow_update_step_status($stepId, 'rejected', $remarks);
            workflow_log_rejection($proposalId, $stepId, $actorId, $actorRole, $remarks, $rejectionCount);
            workflow_log_query_or_reject($proposalId, $actorId, $actorRole, 'reject', $remarks, $stepId, $ownerId);

            if ($rejectionCount >= 3) {
                workflow_mark_proposal_locked($proposalId, 'Maximum rejection attempts reached');
                $updateStmt = $conn->prepare('UPDATE proposals SET rejection_count = ?, locked_reason = "Maximum rejection attempts reached" WHERE id = ?');
                $updateStmt->bind_param('ii', $rejectionCount, $proposalId);
                $updateStmt->execute();
                $updateStmt->close();
            } else {
                $updateStmt = $conn->prepare('UPDATE proposals SET overall_status = "rejected_pending_response", current_stage = ?, current_approval_level = ?, rejection_count = ? WHERE id = ?');
                $updateStmt->bind_param('siii', $currentRole, $currentLevel, $rejectionCount, $proposalId);
                $updateStmt->execute();
                $updateStmt->close();
            }

            workflow_log_action($proposalId, $actorId, $actorRole, 'rejected', $remarks);

            if (app_is_department_role($currentRole)) {
                workflow_sync_department_task_for_role($proposalId, $currentRole, 'rejected', $remarks);
            }

            if ($ownerId > 0) {
                $message = app_role_label($actorRole) . ' rejected your proposal. Reason: ' . $remarks;
                if ($rejectionCount >= 3) {
                    $message = 'Your proposal was rejected 3 times and is now locked. Please submit a new white paper.';
                }
                app_create_notification($ownerId, 'Proposal Rejected', $message, $proposalId, null, 'rejected');
            }
        } else {
            throw new InvalidArgumentException('Invalid action.');
        }

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}

function workflow_handle_club_head_response(int $proposalId, int $submittedBy, string $responseText, ?string $attachmentPath = null): void
{
    global $conn;

    $proposal = workflow_fetch_proposal_row($proposalId);
    if (!$proposal) {
        throw new RuntimeException('Proposal not found.');
    }

    if ((int) ($proposal['submitted_by'] ?? 0) !== $submittedBy) {
        throw new RuntimeException('You are not allowed to respond to this proposal.');
    }

    if (!empty($proposal['is_locked'])) {
        throw new RuntimeException('This proposal is locked. Create a new white paper.');
    }

    $queryRow = null;
    if (app_table_exists('queries')) {
        $hasType = app_column_exists('queries', 'query_type');
        $hasStepId = app_column_exists('queries', 'workflow_step_id');
        $sql = 'SELECT id, workflow_step_id, raised_by, raised_to, role_name, query_text, status' . ($hasType ? ', query_type, club_response' : '') . ' FROM queries WHERE proposal_id = ? AND status = "open" ORDER BY id DESC LIMIT 1';
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $proposalId);
        $stmt->execute();
        $queryRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$queryRow && $hasType) {
            $sql = 'SELECT id, workflow_step_id, raised_by, raised_to, role_name, query_text, status, query_type, club_response FROM queries WHERE proposal_id = ? AND query_type = "reject" ORDER BY id DESC LIMIT 1';
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $proposalId);
            $stmt->execute();
            $queryRow = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }
    }

    if (!$queryRow) {
        throw new RuntimeException('No open query or rejection found for this proposal.');
    }

    $stepId = !empty($queryRow['workflow_step_id']) ? (int) $queryRow['workflow_step_id'] : 0;
    $roleName = (string) ($queryRow['role_name'] ?? 'faculty_mentor');
    $queryId = (int) $queryRow['id'];
    $roleLevel = workflow_role_level($roleName) ?? 1;

    if ($stepId > 0) {
        $stepOrderStmt = $conn->prepare('SELECT step_order, role_name FROM approval_workflow_steps WHERE id = ? LIMIT 1');
        $stepOrderStmt->bind_param('i', $stepId);
        $stepOrderStmt->execute();
        $stepOrderRow = $stepOrderStmt->get_result()->fetch_assoc();
        $stepOrderStmt->close();

        if (!empty($stepOrderRow['role_name'])) {
            $roleName = (string) $stepOrderRow['role_name'];
            $roleLevel = workflow_role_level($roleName) ?? $roleLevel;
        }
    } else {
        $stepStmt = $conn->prepare('SELECT id FROM approval_workflow_steps WHERE proposal_id = ? AND role_name = ? ORDER BY step_order ASC LIMIT 1');
        $stepStmt->bind_param('is', $proposalId, $roleName);
        $stepStmt->execute();
        $stepRow = $stepStmt->get_result()->fetch_assoc();
        $stepStmt->close();
        $stepId = (int) ($stepRow['id'] ?? 0);
    }

    $conn->begin_transaction();

    try {
        workflow_log_response($proposalId, $queryId, $submittedBy, $responseText, $attachmentPath);

        $updateQueryStmt = $conn->prepare('UPDATE queries SET status = "responded", responded_at = NOW()' . (app_column_exists('queries', 'club_response') ? ', club_response = ?' : '') . ' WHERE id = ?');
        if (app_column_exists('queries', 'club_response')) {
            $updateQueryStmt->bind_param('si', $responseText, $queryId);
        } else {
            $updateQueryStmt->bind_param('i', $queryId);
        }
        $updateQueryStmt->execute();
        $updateQueryStmt->close();

        if ($stepId > 0) {
            $stepStmt = $conn->prepare('UPDATE approval_workflow_steps SET status = "resubmitted", remarks = ?, acted_at = NOW() WHERE id = ?');
            $stepStmt->bind_param('si', $responseText, $stepId);
            $stepStmt->execute();
            $stepStmt->close();
        }

        $updateProposalStmt = $conn->prepare('UPDATE proposals SET overall_status = "resubmitted", current_stage = ?, current_approval_level = ?, rejection_count = rejection_count WHERE id = ?');
        $updateProposalStmt->bind_param('sii', $roleName, $roleLevel, $proposalId);
        $updateProposalStmt->execute();
        $updateProposalStmt->close();

        if (app_is_department_role($roleName)) {
            workflow_sync_department_task_for_role($proposalId, $roleName, 'pending', $responseText);
        }

        $raisedTo = (int) ($queryRow['raised_by'] ?? 0);
        if ($raisedTo > 0) {
            app_create_notification($raisedTo, 'Proposal Resubmitted', 'Club Head responded to your query/rejection and resubmitted the proposal.', $proposalId, null, 'resubmitted');
        }

        workflow_log_action($proposalId, $submittedBy, 'club_head', 'resubmitted', 'Club Head responded: ' . $responseText);

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}

function workflow_submit_event_report(int $eventId, int $submittedBy, string $reportTitle, string $reportDescription, int $participantsCount, ?string $reportFilePath = null, array $imagePaths = []): int
{
    global $conn;

    if (!app_table_exists('event_reports')) {
        throw new RuntimeException('Event report module is not available.');
    }

    $stmt = $conn->prepare('SELECT e.*, p.club_id, p.school_id, p.submitted_by FROM events e JOIN proposals p ON p.id = e.proposal_id WHERE e.id = ? LIMIT 1');
    $stmt->bind_param('i', $eventId);
    $stmt->execute();
    $event = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$event) {
        throw new RuntimeException('Event not found.');
    }

    $stmt = $conn->prepare('INSERT INTO event_reports (event_id, submitted_by, report_title, report_description, participants_count, report_file_path, status) VALUES (?, ?, ?, ?, ?, ?, "submitted")');
    $stmt->bind_param('iissis', $eventId, $submittedBy, $reportTitle, $reportDescription, $participantsCount, $reportFilePath);
    $stmt->execute();
    $reportId = (int) $stmt->insert_id;
    $stmt->close();

    if (!empty($imagePaths) && app_table_exists('event_images')) {
        foreach ($imagePaths as $imagePath) {
            if ($imagePath === '') {
                continue;
            }
            $stmt = $conn->prepare('INSERT INTO event_images (event_id, uploaded_by, image_path) VALUES (?, ?, ?)');
            $stmt->bind_param('iis', $eventId, $submittedBy, $imagePath);
            $stmt->execute();
            $stmt->close();
        }
    }

    $stmt = $conn->prepare('UPDATE events SET event_status = "completed" WHERE id = ?');
    $stmt->bind_param('i', $eventId);
    $stmt->execute();
    $stmt->close();

    if (app_table_exists('event_report_visibility_log')) {
        $visibleRoles = ['faculty_mentor', 'school_head', 'president_vc', 'gs_treasurer', 'deputy_registrar'];
        foreach ($visibleRoles as $roleName) {
            $userId = app_school_role_user_id((int)$event['school_id'], $roleName);
            if (!$userId) {
                continue;
            }
            $stmt = $conn->prepare('INSERT INTO event_report_visibility_log (event_report_id, visible_to_user_id, role_name) VALUES (?, ?, ?)');
            $stmt->bind_param('iis', $reportId, $userId, $roleName);
            $stmt->execute();
            $stmt->close();
        }
    }

    $notifyRoles = ['faculty_mentor', 'school_head', 'president_vc', 'gs_treasurer', 'deputy_registrar'];
    foreach ($notifyRoles as $roleName) {
        if ($roleName === 'faculty_mentor') {
            $mentorId = workflow_find_approver_for_role('faculty_mentor', (int)$event['school_id'], (int)$event['club_id']);
            if ($mentorId) {
                app_create_notification($mentorId, 'Post-event Report Submitted', 'A post-event report is ready for review.', $event['proposal_id'], $eventId, 'info');
            }
            continue;
        }

        $userId = app_school_role_user_id((int)$event['school_id'], $roleName);
        if ($userId) {
            app_create_notification($userId, 'Post-event Report Submitted', 'A post-event report is ready for review.', $event['proposal_id'], $eventId, 'info');
        }
    }

    workflow_log_activity($submittedBy, 'club_head', 'post_event_report', 'Submitted post-event report for: ' . (string)$event['event_name'], (int)$event['proposal_id'], $eventId);

    return $reportId;
}

function workflow_department_action(int $taskId, int $actorId, string $actorRole, string $action, string $remarks): array
{
    global $conn;

    $actorRole = app_normalize_role($actorRole);
    if (!app_is_department_role($actorRole)) {
        throw new RuntimeException('Only service department roles can act on department tasks.');
    }

    $allowedActions = ['approved', 'rejected', 'completed'];
    if (!in_array($action, $allowedActions, true)) {
        throw new InvalidArgumentException('Invalid department task action.');
    }

    if (!app_table_exists('department_tasks')) {
        throw new RuntimeException('Department task module is not available.');
    }

    $taskStmt = $conn->prepare('SELECT proposal_id, assigned_user_id, department_role FROM department_tasks WHERE id = ? LIMIT 1');
    $taskStmt->bind_param('i', $taskId);
    $taskStmt->execute();
    $task = $taskStmt->get_result()->fetch_assoc();
    $taskStmt->close();

    if (!$task) {
        throw new RuntimeException('Department task not found.');
    }

    $taskRole = app_normalize_role((string) ($task['department_role'] ?? ''));
    if ($taskRole !== $actorRole) {
        throw new RuntimeException('You are not allowed to act on this department role task.');
    }

    $assignedUserId = (int) ($task['assigned_user_id'] ?? 0);
    if ($assignedUserId > 0 && $assignedUserId !== $actorId) {
        throw new RuntimeException('You are not assigned to this task.');
    }

    if ($assignedUserId <= 0) {
        $claimStmt = $conn->prepare('UPDATE department_tasks SET assigned_user_id = ? WHERE id = ? AND (assigned_user_id IS NULL OR assigned_user_id = 0)');
        $claimStmt->bind_param('ii', $actorId, $taskId);
        $claimStmt->execute();
        $claimStmt->close();
    }

    $proposalId = (int) $task['proposal_id'];
    $workflowAction = $action === 'rejected' ? 'reject' : 'approve';
    $workflowRemarks = $remarks;
    if ($workflowRemarks === '') {
        $workflowRemarks = $workflowAction === 'approve' ? 'Approved by department.' : 'Rejected by department.';
    }

    workflow_handle_review_action($proposalId, $actorId, $actorRole, $workflowAction, $workflowRemarks);

    $taskStatus = $action;
    $taskRemarks = $remarks;
    if ($taskRemarks === '') {
        $taskRemarks = match ($action) {
            'approved' => 'Approved by department.',
            'completed' => 'Completed by department.',
            default => 'Rejected by department.',
        };
    }

    $updateStmt = $conn->prepare('UPDATE department_tasks SET assigned_user_id = ?, status = ?, remarks = ?, acted_at = NOW() WHERE id = ?');
    $updateStmt->bind_param('issi', $actorId, $taskStatus, $taskRemarks, $taskId);
    $updateStmt->execute();
    $updateStmt->close();

    return ['proposal_id' => $proposalId, 'action' => $action];
}
