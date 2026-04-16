<?php
session_start();

if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}
$_SESSION['last_activity'] = time();

include 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin' || $_SESSION['sub_role'] !== 'program-chair') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT full_name FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($full_name);
$stmt->fetch();
$stmt->close();

// Fetch proposals for approval
$stmt = $conn->prepare("SELECT p.*, u.full_name AS submitted_by, c.club_name FROM proposals p JOIN users u ON p.user_id = u.id JOIN clubs c ON p.club_id = c.id WHERE p.faculty_mentor_status = 'Approved' ORDER BY p.created_at DESC");
$stmt->execute();
$proposals_query = $stmt->get_result();

// Fetch notifications
$stmt = $conn->prepare("SELECT n.*, p.event_name FROM notifications n LEFT JOIN proposals p ON n.related_proposal_id = p.id WHERE n.user_id = ? ORDER BY n.created_at DESC LIMIT 5");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$notifications_query = $stmt->get_result();

$stmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($notification_count);
$stmt->fetch();
$stmt->close();

// Fetch all clubs for listing
$stmt = $conn->prepare("SELECT c.id, c.club_name, u.full_name AS club_head FROM clubs c LEFT JOIN users u ON c.id = u.club_id AND u.role = 'head'");
$stmt->execute();
$clubs_query = $stmt->get_result();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_club'])) {
        $club_name = filter_var($_POST['club_name'], FILTER_SANITIZE_STRING);
        $stmt = $conn->prepare("SELECT id FROM clubs WHERE club_name = ?");
        $stmt->bind_param("s", $club_name);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $_SESSION['error'] = "Club name already exists!";
        } else {
            $stmt = $conn->prepare("INSERT INTO clubs (club_name) VALUES (?)");
            $stmt->bind_param("s", $club_name);
            if ($stmt->execute()) {
                $_SESSION['success'] = "Club created successfully!";
            } else {
                $_SESSION['error'] = "Error creating club: " . $stmt->error;
            }
        }
        $stmt->close();
    } elseif (isset($_POST['approve'])) {
        $proposal_id = (int)$_POST['proposal_id'];
        $stmt = $conn->prepare("UPDATE proposals SET program_chair_status = 'Approved' WHERE id = ?");
        $stmt->bind_param("i", $proposal_id);
        $stmt->execute();
        $stmt->close();
    } elseif (isset($_POST['reject'])) {
        $proposal_id = (int)$_POST['proposal_id'];
        $reason = filter_var($_POST['reject_reason'], FILTER_SANITIZE_STRING);
        $stmt = $conn->prepare("UPDATE proposals SET program_chair_status = 'Rejected' WHERE id = ?");
        $stmt->bind_param("i", $proposal_id);
        $stmt->execute();
        $stmt2 = $conn->prepare("SELECT user_id, club_id, event_name FROM proposals WHERE id = ?");
        $stmt2->bind_param("i", $proposal_id);
        $stmt2->execute();
        $stmt2->bind_result($submitter_id, $club_id, $event_name);
        $stmt2->fetch();
        $stmt2->close();
        $stmt3 = $conn->prepare("SELECT id FROM users WHERE club_id = ? AND role = 'admin' AND sub_role = 'faculty-mentor'");
        $stmt3->bind_param("i", $club_id);
        $stmt3->execute();
        $stmt3->bind_result($mentor_id);
        $stmt3->fetch();
        $stmt3->close();
        $stmt4 = $conn->prepare("INSERT INTO notifications (user_id, message, related_proposal_id) VALUES (?, ?, ?)");
        $message = "Your proposal \"$event_name\" was rejected by Program Chair: $reason";
        $stmt4->bind_param("isi", $submitter_id, $message, $proposal_id);
        $stmt4->execute();
        if ($mentor_id) {
            $message = "Proposal \"$event_name\" was rejected by Program Chair: $reason";
            $stmt4->bind_param("isi", $mentor_id, $message, $proposal_id);
            $stmt4->execute();
        }
        $stmt4->close();
    }
    header("Location: admin-program-chair.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NMIMS EventHub - Program Chair Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary: #c52240;
            --primary-light: #c73c50;
            --primary-dark: #7a1526;
            --secondary: #333333;
            --secondary-light: #555555;
            --light: #f8f9fa;
            --dark: #212529;
            --gray: #6c757d;
            --light-gray: #e9ecef;
            --success: #38b000;
            --nmims-dark: #292929;
            --transition: all 0.3s ease;
            --box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background-color: var(--light);
            color: var(--dark);
            line-height: 1.6;
        }

        .dashboard {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 250px;
            background-color: var(--nmims-dark);
            color: #fff;
            padding: 20px;
            position: fixed;
            height: 100%;
            overflow-y: auto;
        }

        .sidebar-header {
            display: flex;
            align-items: center;
            margin-bottom: 30px;
        }

        .sidebar-header h2 {
            font-size: 1.2rem;
            font-weight: 600;
        }

        .sidebar-header i {
            margin-right: 10px;
        }

        .sidebar-menu {
            list-style: none;
        }

        .sidebar-menu li {
            margin-bottom: 10px;
        }

        .sidebar-menu a {
            color: #fff;
            text-decoration: none;
            display: flex;
            align-items: center;
            padding: 10px;
            border-radius: 5px;
            transition: var(--transition);
        }

        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background-color: var(--primary);
        }

        .sidebar-menu i {
            margin-right: 10px;
        }

        .main-content {
            margin-left: 250px;
            padding: 20px;
            flex: 1;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .header h1 {
            font-size: 1.8rem;
            font-weight: 600;
        }

        .user-actions {
            display: flex;
            align-items: center;
        }

        .notification-bell {
            position: relative;
            margin-right: 20px;
            cursor: pointer;
        }

        .notification-bell i {
            font-size: 1.2rem;
            color: var(--gray);
        }

        .notification-count {
            position: absolute;
            top: -5px;
            right: -5px;
            background-color: var(--primary);
            color: #fff;
            font-size: 0.7rem;
            padding: 2px 5px;
            border-radius: 50%;
        }

        .user-profile {
            display: flex;
            align-items: center;
        }

        .user-profile img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 10px;
        }

        .user-profile span {
            font-weight: 500;
        }

        .page {
            background-color: #fff;
            border-radius: 10px;
            padding: 20px;
            box-shadow: var(--box-shadow);
        }

        .section-title {
            font-size: 1.4rem;
            font-weight: 500;
            margin-bottom: 15px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            font-weight: 500;
            margin-bottom: 5px;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid var(--light-gray);
            border-radius: 5px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid var(--light-gray);
        }

        th {
            background-color: var(--light-gray);
            font-weight: 500;
        }

        .btn {
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 500;
            transition: var(--transition);
        }

        .btn-primary {
            background-color: var(--primary);
            color: #fff;
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
        }

        .btn-success {
            background-color: var(--success);
            color: #fff;
        }

        .btn-success:hover {
            background-color: #2e8b57;
        }

        .btn-danger {
            background-color: #e74c3c;
            color: #fff;
        }

        .btn-danger:hover {
            background-color: #c0392b;
        }

        .btn-secondary {
            background-color: var(--gray);
            color: #fff;
        }

        .btn-secondary:hover {
            background-color: var(--secondary);
        }

        .badge {
            padding: 5px 10px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .badge-pending {
            background-color: #f39c12;
            color: #fff;
        }

        .badge-approved {
            background-color: var(--success);
            color: #fff;
        }

        .badge-rejected {
            background-color: #e74c3c;
            color: #fff;
        }

        .badge-under-review {
            background-color: #9b59b6;
            color: #fff;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background-color: #fff;
            border-radius: 10px;
            width: 500px;
            max-width: 90%;
            padding: 20px;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .modal-title {
            font-size: 1.2rem;
            font-weight: 500;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .calendar {
            margin-top: 20px;
        }

        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 5px;
        }

        .calendar-day-header {
            font-weight: 500;
            text-align: center;
            padding: 10px;
            background-color: var(--light-gray);
            border-radius: 5px;
        }

        .calendar-day {
            padding: 10px;
            border: 1px solid var(--light-gray);
            border-radius: 5px;
            min-height: 100px;
            position: relative;
        }

        .calendar-day.empty {
            background-color: #f8f9fa;
        }

        .calendar-day-number {
            position: absolute;
            top: 5px;
            right: 5px;
            font-size: 0.9rem;
            color: var(--gray);
        }

        .calendar-event {
            background-color: var(--primary);
            color: #fff;
            padding: 5px;
            border-radius: 5px;
            margin-top: 20px;
            font-size: 0.8rem;
        }

        .success {
            color: var(--success);
            margin-bottom: 15px;
        }

        .error {
            color: #e74c3c;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-user-tie"></i> Program Chair Dashboard</h2>
            </div>
            <ul class="sidebar-menu">
                <li><a href="#dashboard" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="#approve-reject"><i class="fas fa-check-circle"></i> Approve/Reject</a></li>
                <li><a href="#manage-clubs"><i class="fas fa-users"></i> Manage Clubs</a></li>
                <li><a href="#reports"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="#event-calendar"><i class="fas fa-calendar-alt"></i> Event Calendar</a></li>
                <li><a href="#notifications"><i class="fas fa-bell"></i> Notifications</a></li>
                <li><a href="logout.php" id="logout-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <main class="main-content">
            <div class="header">
                <h1>Program Chair Dashboard</h1>
                <div class="user-actions">
                    <div class="notification-bell">
                        <i class="fas fa-bell"></i>
                        <span class="notification-count"><?php echo $notification_count; ?></span>
                    </div>
                    <div class="user-profile">
                        <img src="SVKM's NMIMS.png" alt="User Profile">
                        <span><?php echo htmlspecialchars($full_name); ?></span>
                    </div>
                </div>
            </div>

            <div id="dashboard" class="page active">
                <?php if (isset($_SESSION['success'])) { echo "<p class='success'>" . $_SESSION['success'] . "</p>"; unset($_SESSION['success']); } ?>
                <?php if (isset($_SESSION['error'])) { echo "<p class='error'>" . $_SESSION['error'] . "</p>"; unset($_SESSION['error']); } ?>
                <h2>Welcome, <?php echo htmlspecialchars($full_name); ?></h2>
                <p>Notifications: <?php echo $notification_count; ?> unread</p>
                <h3 class="section-title">Recent Activities</h3>
                <ul>
                    <?php while ($notification = $notifications_query->fetch_assoc()) { ?>
                        <li><?php echo htmlspecialchars($notification['message']); ?> (<?php echo date('M j, Y, g:i A', strtotime($notification['created_at'])); ?>)</li>
                    <?php } ?>
                </ul>
            </div>

            <div id="approve-reject" class="page" style="display: none;">
                <?php if (isset($_SESSION['success'])) { echo "<p class='success'>" . $_SESSION['success'] . "</p>"; unset($_SESSION['success']); } ?>
                <?php if (isset($_SESSION['error'])) { echo "<p class='error'>" . $_SESSION['error'] . "</p>"; unset($_SESSION['error']); } ?>
                <h2 class="section-title">Approve/Reject Proposals</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Club</th>
                            <th>Proposal Title</th>
                            <th>Submitted By</th>
                            <th>Date</th>
                            <th>Program Chair Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $proposals_query->data_seek(0);
                        while ($proposal = $proposals_query->fetch_assoc()) { 
                            $status_class = strtolower(str_replace(' ', '-', $proposal['program_chair_status']));
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($proposal['club_name']); ?></td>
                                <td><?php echo htmlspecialchars($proposal['event_name']); ?></td>
                                <td><?php echo htmlspecialchars($proposal['submitted_by']); ?></td>
                                <td><?php echo htmlspecialchars($proposal['event_date']); ?></td>
                                <td><span class="badge badge-<?php echo $status_class; ?>"><?php echo htmlspecialchars($proposal['program_chair_status']); ?></span></td>
                                <td>
                                    <a class="btn btn-info btn-sm" href="view-white-paper.php?proposal_id=<?php echo (int)$proposal['id']; ?>">View White Paper</a>
                                    <?php if ($proposal['program_chair_status'] === 'Pending') { ?>
                                        <button class="btn btn-success btn-sm" onclick="openActionModal(<?php echo $proposal['id']; ?>, 'approve')">Approve</button>
                                        <button class="btn btn-danger btn-sm" onclick="openActionModal(<?php echo $proposal['id']; ?>, 'reject')">Reject</button>
                                    <?php } ?>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>

            <div id="manage-clubs" class="page" style="display: none;">
                <?php if (isset($_SESSION['success'])) { echo "<p class='success'>" . $_SESSION['success'] . "</p>"; unset($_SESSION['success']); } ?>
                <?php if (isset($_SESSION['error'])) { echo "<p class='error'>" . $_SESSION['error'] . "</p>"; unset($_SESSION['error']); } ?>
                <h2 class="section-title">Manage Clubs</h2>
                <form method="POST">
                    <div class="form-group">
                        <label>Club Name:</label>
                        <input type="text" name="club_name" required>
                    </div>
                    <button type="submit" name="create_club" class="btn btn-primary">Create Club</button>
                </form>
                <h3 class="section-title" style="margin-top: 20px;">Existing Clubs</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Club Name</th>
                            <th>Club Head</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($club = $clubs_query->fetch_assoc()) { ?>
                            <tr>
                                <td><?php echo htmlspecialchars($club['club_name']); ?></td>
                                <td><?php echo htmlspecialchars($club['club_head'] ?: 'None Assigned'); ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>

            <div id="reports" class="page" style="display: none;">
                <?php if (isset($_SESSION['success'])) { echo "<p class='success'>" . $_SESSION['success'] . "</p>"; unset($_SESSION['success']); } ?>
                <?php if (isset($_SESSION['error'])) { echo "<p class='error'>" . $_SESSION['error'] . "</p>"; unset($_SESSION['error']); } ?>
                <h2 class="section-title">Reports & Analytics</h2>
                <canvas id="statusPieChart" style="max-height: 300px; margin-bottom: 20px;"></canvas>
                <canvas id="deptBarChart" style="max-height: 300px; margin-bottom: 20px;"></canvas>
                <canvas id="timelineChart" style="max-height: 300px;"></canvas>
            </div>

            <div id="event-calendar" class="page" style="display: none;">
                <?php if (isset($_SESSION['success'])) { echo "<p class='success'>" . $_SESSION['success'] . "</p>"; unset($_SESSION['success']); } ?>
                <?php if (isset($_SESSION['error'])) { echo "<p class='error'>" . $_SESSION['error'] . "</p>"; unset($_SESSION['error']); } ?>
                <h2 class="section-title">Event Calendar</h2>
                <div class="calendar">
                    <div class="calendar-header">
                        <button class="btn btn-secondary" onclick="prevMonth()"><i class="fas fa-chevron-left"></i></button>
                        <h3 id="calendar-title"><?php echo date('F Y'); ?></h3>
                        <button class="btn btn-secondary" onclick="nextMonth()"><i class="fas fa-chevron-right"></i></button>
                    </div>
                    <div class="calendar-grid" id="calendar-grid"></div>
                </div>
            </div>

            <div id="notifications" class="page" style="display: none;">
                <?php if (isset($_SESSION['success'])) { echo "<p class='success'>" . $_SESSION['success'] . "</p>"; unset($_SESSION['success']); } ?>
                <?php if (isset($_SESSION['error'])) { echo "<p class='error'>" . $_SESSION['error'] . "</p>"; unset($_SESSION['error']); } ?>
                <h2 class="section-title">Notifications</h2>
                <ul>
                    <?php 
                    $notifications_query->data_seek(0);
                    while ($notification = $notifications_query->fetch_assoc()) { 
                    ?>
                        <li><?php echo htmlspecialchars($notification['message']); ?> (<?php echo date('M j, Y, g:i A', strtotime($notification['created_at'])); ?>)</li>
                    <?php } ?>
                </ul>
            </div>

            <div id="action-modal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3 class="modal-title" id="action-modal-title"></h3>
                        <button class="modal-close" onclick="closeModal('action-modal')">×</button>
                    </div>
                    <form id="action-form" method="POST">
                        <input type="hidden" name="proposal_id" id="action-proposal-id">
                        <div class="modal-body" id="action-modal-body"></div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" onclick="closeModal('action-modal')">Cancel</button>
                            <button type="submit" class="btn" id="action-submit-btn">Submit</button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script>
        let currentDate = new Date();

        function showPage(pageId) {
            document.querySelectorAll('.page').forEach(page => page.style.display = 'none');
            document.getElementById(pageId).style.display = 'block';
            document.querySelectorAll('.sidebar-menu a').forEach(link => {
                link.classList.remove('active');
                if (link.getAttribute('href') === `#${pageId}`) link.classList.add('active');
            });
            if (pageId === 'event-calendar') renderCalendar();
            if (pageId === 'reports') loadCharts();
        }

        function openActionModal(proposalId, action) {
            const modal = document.getElementById('action-modal');
            const form = document.getElementById('action-form');
            const proposalIdInput = document.getElementById('action-proposal-id');
            const modalBody = document.getElementById('action-modal-body');
            const submitBtn = document.getElementById('action-submit-btn');

            proposalIdInput.value = proposalId;

            if (action === 'approve') {
                document.getElementById('action-modal-title').textContent = 'Approve Proposal';
                modalBody.innerHTML = `<p>Are you sure you want to approve this proposal?</p>`;
                submitBtn.textContent = 'Approve';
                submitBtn.className = 'btn btn-success';
                submitBtn.name = 'approve';
            } else if (action === 'reject') {
                document.getElementById('action-modal-title').textContent = 'Reject Proposal';
                modalBody.innerHTML = `
                    <div class="form-group">
                        <label class="form-label">Reason for Rejection</label>
                        <textarea class="form-control" name="reject_reason" required></textarea>
                    </div>`;
                submitBtn.textContent = 'Reject';
                submitBtn.className = 'btn btn-danger';
                submitBtn.name = 'reject';
            }
            modal.style.display = 'flex';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function renderCalendar() {
            const grid = document.getElementById('calendar-grid');
            const title = document.getElementById('calendar-title');
            const year = currentDate.getFullYear();
            const month = currentDate.getMonth();
            title.textContent = currentDate.toLocaleString('default', { month: 'long', year: 'numeric' });

            const firstDay = new Date(year, month, 1).getDay();
            const daysInMonth = new Date(year, month + 1, 0).getDate();

            grid.innerHTML = '';
            const days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            days.forEach(day => {
                grid.innerHTML += `<div class="calendar-day-header">${day}</div>`;
            });

            for (let i = 0; i < firstDay; i++) {
                grid.innerHTML += `<div class="calendar-day empty"></div>`;
            }

            fetch(`get_events.php?month=${month + 1}&year=${year}`)
                .then(response => response.json())
                .then(events => {
                    for (let day = 1; day <= daysInMonth; day++) {
                        const dayEvents = events.filter(e => new Date(e.event_date).getDate() === day);
                        let eventHTML = '';
                        dayEvents.forEach(event => {
                            eventHTML += `<div class="calendar-event">${event.event_name}</div>`;
                        });
                        grid.innerHTML += `
                            <div class="calendar-day">
                                <div class="calendar-day-number">${day}</div>
                                ${eventHTML}
                            </div>`;
                    }
                });
        }

        function prevMonth() {
            currentDate.setMonth(currentDate.getMonth() - 1);
            renderCalendar();
        }

        function nextMonth() {
            currentDate.setMonth(currentDate.getMonth() + 1);
            renderCalendar();
        }

        function loadCharts() {
            fetch('get_reports.php')
                .then(response => response.json())
                .then(data => {
                    new Chart(document.getElementById('statusPieChart').getContext('2d'), {
                        type: 'pie',
                        data: {
                            labels: ['Approved', 'Pending', 'Rejected', 'Under Review'],
                            datasets: [{
                                data: [data.status.approved, data.status.pending, data.status.rejected, data.status.under_review],
                                backgroundColor: ['#38b000', '#f39c12', '#e74c3c', '#9b59b6']
                            }]
                        }
                    });
                    new Chart(document.getElementById('deptBarChart').getContext('2d'), {
                        type: 'bar',
                        data: {
                            labels: Object.keys(data.types),
                            datasets: [{
                                label: 'Proposals by Type',
                                data: Object.values(data.types),
                                backgroundColor: '#c52240'
                            }]
                        },
                        options: { scales: { y: { beginAtZero: true } } }
                    });
                    new Chart(document.getElementById('timelineChart').getContext('2d'), {
                        type: 'line',
                        data: {
                            labels: data.timeline.dates,
                            datasets: [
                                { label: 'Approved', data: data.timeline.approved, borderColor: '#38b000', fill: true },
                                { label: 'Rejected', data: data.timeline.rejected, borderColor: '#e74c3c', fill: true }
                            ]
                        },
                        options: { scales: { y: { beginAtZero: true } } }
                    });
                });
        }

        document.querySelectorAll('.sidebar-menu a:not(#logout-link)').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                showPage(link.getAttribute('href').substring(1));
            });
        });

        document.getElementById('logout-link').addEventListener('click', (e) => {
            window.location.href = 'logout.php';
        });

        document.addEventListener('DOMContentLoaded', () => {
            showPage('dashboard');
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>