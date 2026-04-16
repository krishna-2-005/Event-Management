<?php
declare(strict_types=1);

require_once __DIR__ . '/app.php';

function layout_nav_items(array $user): array
{
    $role = app_normalize_role((string) ($user['role'] ?? ''), isset($user['sub_role']) ? (string) $user['sub_role'] : null);

    $items = [
        ['href' => 'dashboard.php', 'key' => 'dashboard', 'label' => 'Dashboard'],
    ];

    if ($role === 'club_head') {
        $items[] = ['href' => 'submit-proposal.php', 'key' => 'submit_proposal', 'label' => 'Submit White Paper'];
        $items[] = ['href' => 'my-proposals.php', 'key' => 'my_proposals', 'label' => 'My Proposals'];
        $items[] = ['href' => 'student-events.php', 'key' => 'my_events', 'label' => 'My Events'];
        $items[] = ['href' => 'post-event-report.php', 'key' => 'post_event_report', 'label' => 'Post-Event Report'];
    }

    if (app_is_main_approver_role($role)) {
        $items[] = ['href' => 'approvals.php', 'key' => 'approvals', 'label' => 'Pending Proposals'];
    }

    if (app_is_department_role($role)) {
        $items[] = ['href' => 'department-tasks.php', 'key' => 'department_tasks', 'label' => 'Service Tasks'];
    }

    if ($role === 'student') {
        $items[] = ['href' => 'student-events.php', 'key' => 'student_events', 'label' => 'Events'];
        $items[] = ['href' => 'my-registrations.php', 'key' => 'my_registrations', 'label' => 'My Registrations'];
    }

    if ($role === 'super_admin') {
        $items[] = ['href' => 'admin-center.php', 'key' => 'admin_center', 'label' => 'Admin Center'];
        $items[] = ['href' => 'manage-schools.php', 'key' => 'manage_schools', 'label' => 'Schools'];
        $items[] = ['href' => 'manage-school-roles.php', 'key' => 'manage_school_roles', 'label' => 'School Roles'];
        $items[] = ['href' => 'manage-users-roles.php', 'key' => 'manage_users_roles', 'label' => 'Users & Roles'];
        $items[] = ['href' => 'manage-club-members.php', 'key' => 'manage_club_members', 'label' => 'Club Members'];
        $items[] = ['href' => 'manage-clubs.php', 'key' => 'manage_clubs', 'label' => 'Clubs'];
        $items[] = ['href' => 'manage-venues.php', 'key' => 'manage_venues', 'label' => 'Venues'];
        $items[] = ['href' => 'blocked-dates.php', 'key' => 'blocked_dates', 'label' => 'Blocked Dates'];
        $items[] = ['href' => 'all-proposals.php', 'key' => 'all_proposals', 'label' => 'All Proposals'];
        $items[] = ['href' => 'activity-feed.php', 'key' => 'activity_feed', 'label' => 'Activity Feed'];
    }

    if ($role === 'school_head') {
        $items[] = ['href' => 'school-clubs.php', 'key' => 'school_clubs', 'label' => 'Clubs in My School'];
    }

    $items[] = ['href' => 'event-calendar.php', 'key' => 'event_calendar', 'label' => 'Event Calendar'];
    $items[] = ['href' => 'logout.php', 'key' => 'logout', 'label' => 'Logout'];

    return $items;
}

function layout_render_header(string $title, array $user, string $activeKey = 'dashboard'): void
{
    $notificationCount = app_get_unread_count((int) $user['id']);
    $unreadPopups = app_fetch_unread_notifications((int) $user['id'], 3);
    $normalizedRole = app_normalize_role((string) ($user['role'] ?? ''), isset($user['sub_role']) ? (string) $user['sub_role'] : null);
    $roleLabel = app_role_label($normalizedRole);
    $navItems = layout_nav_items($user);
    $brand = app_brand_identity($user);
    $sidebarLogo = (string) $brand['logo'];
    $brandTitle = (string) $brand['title'];
    $brandSubtitle = (string) $brand['subtitle'];
    $schoolLabel = app_school_label(isset($user['school_id']) ? (int) $user['school_id'] : null);

    echo '<!DOCTYPE html>';
    echo '<html lang="en">';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<title>' . htmlspecialchars($title) . ' - Smart Event Organizer</title>';
    echo '<link rel="preconnect" href="https://fonts.googleapis.com">';
    echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
    echo '<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Source+Sans+3:wght@400;500;600;700&display=swap" rel="stylesheet">';
    echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">';
    echo '<link rel="stylesheet" href="assets/app.css">';
    echo '</head>';
    echo '<body>';
    echo '<div class="orb-bg"></div>';
    echo '<aside class="sidebar">';
    echo '<div class="brand-block">';
    echo '<div class="brand-logo-wrap">';
    echo '<img src="' . htmlspecialchars($sidebarLogo) . '" alt="Brand Logo">';
    echo '</div>';
    echo '<div class="brand-text">';
    echo '<h2>' . htmlspecialchars($brandTitle) . '</h2>';
    echo '<p>' . htmlspecialchars($brandSubtitle) . '</p>';
    echo '</div>';
    echo '</div>';
    echo '<div class="role-strip">' . htmlspecialchars($roleLabel) . '</div>';
    echo '<nav class="nav-menu">';

    foreach ($navItems as $item) {
        $activeClass = $activeKey === $item['key'] ? 'active' : '';
        echo '<a class="nav-item ' . $activeClass . '" href="' . htmlspecialchars($item['href']) . '"><i class="fa-solid fa-circle-chevron-right"></i><span>' . htmlspecialchars($item['label']) . '</span></a>';
    }

    echo '</nav>';
    echo '</aside>';
    echo '<div class="sidebar-overlay" id="sidebarOverlay"></div>';

    echo '<header class="topbar">';
    echo '<div class="topbar-left">';
    echo '<button class="menu-toggle" id="sidebarToggle" type="button" aria-label="Toggle sidebar"><i class="fa-solid fa-bars"></i></button>';
    echo '<div class="page-heading"><div class="topbar-brand">Event Management</div><h1>' . htmlspecialchars($title) . '</h1><p>' . htmlspecialchars($schoolLabel) . ' | ' . htmlspecialchars($roleLabel) . '</p></div>';
    echo '</div>';
    echo '<div class="topbar-right">';
    echo '<a href="notifications.php" class="icon-chip" aria-label="Notifications"><i class="fa-solid fa-bell"></i><span class="count">' . (int) $notificationCount . '</span></a>';
    echo '<div class="profile-chip">';
    echo '<div class="profile-logo"><img src="' . htmlspecialchars($sidebarLogo) . '" alt="Profile Logo"></div>';
    echo '<div class="profile-meta"><strong>' . htmlspecialchars((string) $user['full_name']) . '</strong><span>' . htmlspecialchars($roleLabel) . '</span></div>';
    echo '</div>';
    echo '</div>';
    echo '</header>';

    echo '<main class="main-content">';
    if (!empty($unreadPopups)) {
        $popupPayload = [];
        foreach ($unreadPopups as $popup) {
            $popupPayload[] = [
                'title' => (string) ($popup['title'] ?: 'Notification'),
                'message' => (string) ($popup['message'] ?? ''),
                'type' => (string) ($popup['type'] ?? 'info'),
            ];
        }
        echo '<div id="notifToastStack" class="notif-toast-stack" data-items="' . htmlspecialchars((string) json_encode($popupPayload, JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') . '"></div>';
    }

    $flash = app_flash_take_all();
    if (!empty($flash)) {
        echo '<section class="flash-stack">';
        foreach ($flash as $type => $messages) {
            foreach ($messages as $message) {
                echo '<div class="flash ' . htmlspecialchars($type) . '">' . htmlspecialchars($message) . '</div>';
            }
        }
        echo '</section>';
    }
}

function layout_render_footer(): void
{
    echo '</main>';
    echo '<footer class="app-footer">&copy; ' . date('Y') . ' Kuchuru Sai Krishna Reddy - STME. All rights reserved.</footer>';
    echo '<script>';
    echo 'document.addEventListener("DOMContentLoaded", function () {';
    echo 'var toggle = document.getElementById("sidebarToggle");';
    echo 'var sidebar = document.querySelector(".sidebar");';
    echo 'var overlay = document.getElementById("sidebarOverlay");';
    echo 'if (toggle && sidebar) {';
    echo 'toggle.addEventListener("click", function () {';
    echo 'sidebar.classList.toggle("open");';
    echo 'if (overlay) { overlay.classList.toggle("active", sidebar.classList.contains("open")); }';
    echo '});';
    echo '}';
    echo 'if (overlay && sidebar) {';
    echo 'overlay.addEventListener("click", function () {';
    echo 'sidebar.classList.remove("open");';
    echo 'overlay.classList.remove("active");';
    echo '});';
    echo '}';
    echo 'window.addEventListener("resize", function () {';
    echo 'if (window.innerWidth > 1023 && sidebar) {';
    echo 'sidebar.classList.remove("open");';
    echo 'if (overlay) { overlay.classList.remove("active"); }';
    echo '}';
    echo '});';

    echo 'var toastStack = document.getElementById("notifToastStack");';
    echo 'if (toastStack && toastStack.dataset.items) {';
    echo 'try {';
    echo 'var items = JSON.parse(toastStack.dataset.items);';
    echo 'items.forEach(function (item, index) {';
    echo 'var toast = document.createElement("div");';
    echo 'toast.className = "notif-toast " + (item.type || "info");';
    echo 'var heading = document.createElement("strong");';
    echo 'heading.textContent = item.title || "Notification";';
    echo 'var body = document.createElement("p");';
    echo 'body.textContent = item.message || "";';
    echo 'toast.appendChild(heading);';
    echo 'toast.appendChild(body);';
    echo 'toastStack.appendChild(toast);';
    echo 'setTimeout(function () { toast.classList.add("show"); }, 140 * index);';
    echo 'setTimeout(function () { toast.classList.remove("show"); }, 5200 + (index * 300));';
    echo 'setTimeout(function () { if (toast.parentNode) { toast.parentNode.removeChild(toast); } }, 6200 + (index * 300));';
    echo '});';
    echo '} catch (e) {}';
    echo '}';

    echo '});';
    echo '</script>';
    echo '</body>';
    echo '</html>';
}
