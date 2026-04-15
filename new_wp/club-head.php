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

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'head') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT full_name, club_id FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($full_name, $club_id);
$stmt->fetch();
$stmt->close();

$stmt = $conn->prepare("SELECT p.*, c.club_name FROM proposals p JOIN clubs c ON p.club_id = c.id WHERE p.user_id = ? ORDER BY p.created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$proposals_query = $stmt->get_result();

$stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$notifications_query = $stmt->get_result();

$stmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($notification_count);
$stmt->fetch();
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['submit_proposal'])) {
        $event_name = filter_var($_POST['event_name'], FILTER_SANITIZE_STRING);
        $event_type = $_POST['event_type'];
        $event_date = $_POST['event_date'];
        $event_location = filter_var($_POST['event_location'], FILTER_SANITIZE_STRING);
        $event_description = filter_var($_POST['event_description'], FILTER_SANITIZE_STRING);
        $event_budget = filter_var($_POST['event_budget'], FILTER_VALIDATE_FLOAT);
        $collaboration = filter_var($_POST['collaboration'], FILTER_SANITIZE_STRING);

        $stmt = $conn->prepare("INSERT INTO proposals (user_id, club_id, event_name, event_type, event_date, event_location, event_description, event_budget, collaboration, faculty_mentor_status, program_chair_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', 'Pending')");
        $stmt->bind_param("iisssssds", $user_id, $club_id, $event_name, $event_type, $event_date, $event_location, $event_description, $event_budget, $collaboration);
        if ($stmt->execute()) {
            $_SESSION['success'] = "Proposal submitted successfully!";
        } else {
            $_SESSION['error'] = "Error submitting proposal: " . $stmt->error;
        }
        $stmt->close();
    } elseif (isset($_POST['submit_response'])) {
        $proposal_id = (int)$_POST['proposal_id'];
        $response = filter_var($_POST['query_response'], FILTER_SANITIZE_STRING);
        $stmt = $conn->prepare("UPDATE proposals SET query_response = ?, faculty_mentor_status = 'Pending', program_chair_status = 'Pending' WHERE id = ? AND user_id = ?");
        $stmt->bind_param("sii", $response, $proposal_id, $user_id);
        if ($stmt->execute()) {
            $_SESSION['success'] = "Response submitted successfully!";
        } else {
            $_SESSION['error'] = "Error submitting response: " . $stmt->error;
        }
        $stmt->close();
    }
    header("Location: club-head.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NMIMS EventHub - Club Head Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
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
            display: none;
            background-color: #fff;
            border-radius: 10px;
            padding: 20px;
            box-shadow: var(--box-shadow);
        }

        .page.active {
            display: block;
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

        .btn-secondary {
            background-color: var(--gray);
            color: #fff;
        }

        .btn-secondary:hover {
            background-color: var(--secondary-light);
        }

        .success {
            color: var(--success);
            margin-bottom: 15px;
        }

        .error {
            color: #e74c3c;
            margin-bottom: 15px;
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

        /* Dashboard-specific styles */
        .dashboard-cards {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }

        .card {
            flex: 1;
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: var(--box-shadow);
            text-align: center;
        }

        .card-icon {
            font-size: 2rem;
            color: var(--gray);
            margin-bottom: 10px;
        }

        .card h3 {
            font-size: 1.2rem;
            margin-bottom: 10px;
        }

        .card p {
            font-size: 0.9rem;
            color: var(--gray);
            margin-bottom: 15px;
        }

        .recent-activity {
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: var(--box-shadow);
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-user-tie"></i> Club Head Dashboard</h2>
            </div>
            <ul class="sidebar-menu">
                <li><a href="#dashboard" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="#submit-proposal"><i class="fas fa-plus-circle"></i> Submit Proposal</a></li>
                <li><a href="#my-proposals"><i class="fas fa-file-alt"></i> My Proposals</a></li>
                <li><a href="#notifications"><i class="fas fa-bell"></i> Notifications</a></li>
                <li><a href="logout.php" id="logout-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <main class="main-content">
            <div class="header">
                <h1>Club Head Dashboard</h1>
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
                <h2 class="section-title">Club Head Dashboard</h2>
                <p>Manage your club’s events, proposals, and collaborations with ease</p>
                <div class="dashboard-cards">
                    <div class="card">
                        <div class="card-icon">
                            <i class="fas fa-plus-circle"></i>
                        </div>
                        <h3>Submit Event Proposal</h3>
                        <p>Create and submit a new event proposal for approval with detailed info.</p>
                        <button class="btn btn-primary nav-link-btn" data-section="submit-proposal">Submit Proposal</button>
                    </div>
                    <div class="card">
                        <div class="card-icon">
                            <i class="fas fa-eye"></i>
                        </div>
                        <h3>Track Proposal Status</h3>
                        <p>Monitor the status of your submitted proposals and review feedback.</p>
                        <button class="btn btn-secondary nav-link-btn" data-section="my-proposals">View Proposals</button>
                    </div>
                    <div class="card">
                        <div class="card-icon">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <h3>Event Calendar</h3>
                        <p>View all approved events to plan and avoid scheduling conflicts.</p>
                        <button class="btn btn-secondary">View Calendar</button>
                    </div>
                </div>
                <div class="dashboard-cards">
                    <div class="card">
                        <div class="card-icon">
                            <i class="fas fa-edit"></i>
                        </div>
                        <h3>Modify Pending Proposals</h3>
                        <p>Edit or cancel pending proposals before final approval.</p>
                        <button class="btn btn-secondary nav-link-btn" data-section="my-proposals">Manage Proposals</button>
                    </div>
                    <div class="card">
                        <div class="card-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <h3>Collaborate</h3>
                        <p>Partner with other clubs for collaborative events and resource sharing.</p>
                        <button class="btn btn-primary">Start Collaboration</button>
                    </div>
                    <div class="card">
                        <div class="card-icon">
                            <i class="fas fa-bell"></i>
                        </div>
                        <h3>Notifications</h3>
                        <p>Stay updated with event-related alerts, proposals, and feedback.</p>
                        <button class="btn btn-secondary nav-link-btn" data-section="notifications">View Notifications</button>
                    </div>
                </div>
                <div class="recent-activity">
                    <h3 class="section-title">Your Event Proposals</h3>
                    <?php 
                    $proposals_query->data_seek(0);
                    if ($proposals_query->num_rows > 0) {
                        echo "<table>";
                        echo "<thead><tr><th>Club</th><th>Event Name</th><th>Event Date</th><th>Faculty Status</th><th>Program Chair Status</th></tr></thead>";
                        echo "<tbody>";
                        while ($proposal = $proposals_query->fetch_assoc()) {
                            echo "<tr>";
                            echo "<td>" . htmlspecialchars($proposal['club_name']) . "</td>";
                            echo "<td>" . htmlspecialchars($proposal['event_name']) . "</td>";
                            echo "<td>" . htmlspecialchars($proposal['event_date']) . "</td>";
                            echo "<td><span class='badge badge-" . strtolower(str_replace(' ', '-', $proposal['faculty_mentor_status'])) . "'>" . htmlspecialchars($proposal['faculty_mentor_status']) . "</span></td>";
                            echo "<td><span class='badge badge-" . strtolower(str_replace(' ', '-', $proposal['program_chair_status'])) . "'>" . htmlspecialchars($proposal['program_chair_status']) . "</span></td>";
                            echo "</tr>";
                        }
                        echo "</tbody></table>";
                    } else {
                        echo "<p>No proposals submitted yet.</p>";
                    }
                    ?>
                </div>
            </div>

            <div id="submit-proposal" class="page">
                <?php if (isset($_SESSION['success'])) { echo "<p class='success'>" . $_SESSION['success'] . "</p>"; unset($_SESSION['success']); } ?>
                <?php if (isset($_SESSION['error'])) { echo "<p class='error'>" . $_SESSION['error'] . "</p>"; unset($_SESSION['error']); } ?>
                <h2 class="section-title">Submit a New Proposal</h2>
                <form method="POST">
                    <div class="form-group">
                        <label>Event Name:</label>
                        <input type="text" name="event_name" required>
                    </div>
                    <div class="form-group">
                        <label>Event Type:</label>
                        <select name="event_type" required>
                            <option value="Workshop">Workshop</option>
                            <option value="Seminar">Seminar</option>
                            <option value="Competition">Competition</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Event Date:</label>
                        <input type="date" name="event_date" required>
                    </div>
                    <div class="form-group">
                        <label>Event Location:</label>
                        <input type="text" name="event_location">
                    </div>
                    <div class="form-group">
                        <label>Event Description:</label>
                        <textarea name="event_description" required></textarea>
                    </div>
                    <div class="form-group">
                        <label>Event Budget:</label>
                        <input type="number" name="event_budget" step="0.01">
                    </div>
                    <div class="form-group">
                        <label>Collaboration:</label>
                        <input type="text" name="collaboration">
                    </div>
                    <button type="submit" name="submit_proposal" class="btn btn-primary">Submit Proposal</button>
                </form>
            </div>

            <div id="my-proposals" class="page">
                <?php if (isset($_SESSION['success'])) { echo "<p class='success'>" . $_SESSION['success'] . "</p>"; unset($_SESSION['success']); } ?>
                <?php if (isset($_SESSION['error'])) { echo "<p class='error'>" . $_SESSION['error'] . "</p>"; unset($_SESSION['error']); } ?>
                <h2 class="section-title">My Proposals</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Club</th>
                            <th>Event Name</th>
                            <th>Event Date</th>
                            <th>Faculty Status</th>
                            <th>Program Chair Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $proposals_query->data_seek(0);
                        while ($proposal = $proposals_query->fetch_assoc()) { 
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($proposal['club_name']); ?></td>
                                <td><?php echo htmlspecialchars($proposal['event_name']); ?></td>
                                <td><?php echo htmlspecialchars($proposal['event_date']); ?></td>
                                <td><span class="badge badge-<?php echo strtolower(str_replace(' ', '-', $proposal['faculty_mentor_status'])); ?>"><?php echo htmlspecialchars($proposal['faculty_mentor_status']); ?></span></td>
                                <td><span class="badge badge-<?php echo strtolower(str_replace(' ', '-', $proposal['program_chair_status'])); ?>"><?php echo htmlspecialchars($proposal['program_chair_status']); ?></span></td>
                                <td>
                                    <?php if ($proposal['faculty_mentor_status'] === 'Under Review' && $proposal['query_details']) { ?>
                                        <form method="POST">
                                            <input type="hidden" name="proposal_id" value="<?php echo $proposal['id']; ?>">
                                            <textarea name="query_response" placeholder="Your response" required></textarea>
                                            <button type="submit" name="submit_response" class="btn btn-primary">Submit Response</button>
                                        </form>
                                    <?php } ?>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>

            <div id="notifications" class="page">
                <?php if (isset($_SESSION['success'])) { echo "<p class='success'>" . $_SESSION['success'] . "</p>"; unset($_SESSION['success']); } ?>
                <?php if (isset($_SESSION['error'])) { echo "<p class='error'>" . $_SESSION['error'] . "</p>"; unset($_SESSION['error']); } ?>
                <h2 class="section-title">Notifications</h2>
                <ul>
                    <?php 
                    $notifications_query->data_seek(0);
                    while ($notification = $notifications_query->fetch_assoc()) { 
                    ?>
                        <li><?php echo htmlspecialchars($notification['message']); ?> (<?php echo $notification['created_at']; ?>)</li>
                    <?php } ?>
                </ul>
            </div>
        </main>
    </div>

    <script>
        function showPage(pageId) {
            document.querySelectorAll('.page').forEach(page => page.classList.remove('active'));
            document.getElementById(pageId).classList.add('active');
            document.querySelectorAll('.sidebar-menu a').forEach(link => {
                link.classList.remove('active');
                if (link.getAttribute('href') === `#${pageId}`) link.classList.add('active');
            });
        }

        // Handle sidebar navigation
        document.querySelectorAll('.sidebar-menu a:not(#logout-link)').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                showPage(link.getAttribute('href').substring(1));
            });
        });

        // Handle dashboard card buttons
        document.querySelectorAll('.nav-link-btn').forEach(button => {
            button.addEventListener('click', () => {
                const sectionId = button.getAttribute('data-section');
                showPage(sectionId);
            });
        });

        document.getElementById('logout-link').addEventListener('click', () => {
            window.location.href = 'logout.php';
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>