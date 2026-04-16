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

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin' || $_SESSION['sub_role'] !== 'faculty-mentor') {
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

$stmt = $conn->prepare("SELECT p.*, u.full_name AS submitted_by, c.club_name FROM proposals p JOIN users u ON p.user_id = u.id JOIN clubs c ON p.club_id = c.id WHERE p.club_id = ? ORDER BY p.created_at DESC");
$stmt->bind_param("i", $club_id);
$stmt->execute();
$proposals_query = $stmt->get_result();

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $proposal_id = (int)$_POST['proposal_id'];
    if (isset($_POST['approve'])) {
        $stmt = $conn->prepare("UPDATE proposals SET faculty_mentor_status = 'Approved' WHERE id = ? AND club_id = ?");
        $stmt->bind_param("ii", $proposal_id, $club_id);
        $stmt->execute();
        $stmt->close();
    } elseif (isset($_POST['reject'])) {
        $reason = filter_var($_POST['reject_reason'], FILTER_SANITIZE_STRING);
        $stmt = $conn->prepare("UPDATE proposals SET faculty_mentor_status = 'Rejected' WHERE id = ? AND club_id = ?");
        $stmt->bind_param("ii", $proposal_id, $club_id);
        $stmt->execute();
        $stmt2 = $conn->prepare("SELECT user_id, event_name FROM proposals WHERE id = ?");
        $stmt2->bind_param("i", $proposal_id);
        $stmt2->execute();
        $stmt2->bind_result($submitter_id, $event_name);
        $stmt2->fetch();
        $stmt2->close();
        $stmt3 = $conn->prepare("INSERT INTO notifications (user_id, message, related_proposal_id) VALUES (?, ?, ?)");
        $message = "Your proposal \"$event_name\" was rejected by Faculty Mentor: $reason";
        $stmt3->bind_param("isi", $submitter_id, $message, $proposal_id);
        $stmt3->execute();
        $stmt3->close();
    } elseif (isset($_POST['query'])) {
        $details = filter_var($_POST['query_details'], FILTER_SANITIZE_STRING);
        $deadline = $_POST['query_deadline'];
        $stmt = $conn->prepare("UPDATE proposals SET faculty_mentor_status = 'Under Review', query_details = ?, query_deadline = ? WHERE id = ? AND club_id = ?");
        $stmt->bind_param("ssii", $details, $deadline, $proposal_id, $club_id);
        $stmt->execute();
        $stmt2 = $conn->prepare("SELECT user_id, event_name FROM proposals WHERE id = ?");
        $stmt2->bind_param("i", $proposal_id);
        $stmt2->execute();
        $stmt2->bind_result($submitter_id, $event_name);
        $stmt2->fetch();
        $stmt2->close();
        $stmt3 = $conn->prepare("INSERT INTO notifications (user_id, message, related_proposal_id) VALUES (?, ?, ?)");
        $message = "Query raised for your proposal \"$event_name\": $details";
        $stmt3->bind_param("isi", $submitter_id, $message, $proposal_id);
        $stmt3->execute();
        $stmt3->close();
    }
    header("Location: admin-faculty-mentor.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NMIMS EventHub - Faculty Mentor Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #c52240;
            --secondary: #333333;
            --light: #f8f9fa;
            --dark: #212529;
            --gray: #6c757d;
            --light-gray: #e9ecef;
            --success: #38b000;
            --danger: #e74c3c;
            --warning: #f39c12;
            --info: #3498db;
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
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 250px;
            background-color: var(--dark);
            color: #fff;
            padding: 20px;
            position: fixed;
            height: 100%;
        }

        .sidebar h2 {
            font-size: 1.5rem;
            margin-bottom: 20px;
        }

        .sidebar ul {
            list-style: none;
        }

        .sidebar ul li {
            margin: 15px 0;
        }

        .sidebar ul li a {
            color: #fff;
            text-decoration: none;
            font-size: 1rem;
            display: flex;
            align-items: center;
        }

        .sidebar ul li a i {
            margin-right: 10px;
        }

        .sidebar ul li a:hover {
            color: var(--primary);
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
        }

        .notification-bell {
            position: relative;
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

        .section {
            display: none;
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .section.active {
            display: block;
        }

        h2 {
            font-size: 1.5rem;
            margin-bottom: 15px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid var(--light-gray);
        }

        th {
            background-color: var(--light-gray);
        }

        .btn {
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            color: #fff;
            margin-right: 5px;
        }

        .btn-success {
            background-color: var(--success);
        }

        .btn-danger {
            background-color: var(--danger);
        }

        .btn-info {
            background-color: var(--info);
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid var(--light-gray);
            border-radius: 5px;
        }

        .status {
            padding: 5px 10px;
            border-radius: 5px;
            color: #fff;
            font-size: 0.9rem;
        }

        .status-pending {
            background-color: var(--warning);
        }

        .status-approved {
            background-color: var(--success);
        }

        .status-rejected {
            background-color: var(--danger);
        }

        .status-under-review {
            background-color: var(--info);
        }

        .success {
            color: var(--success);
            margin-bottom: 15px;
        }

        .error {
            color: var(--danger);
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2>Faculty Mentor</h2>
        <ul>
            <li><a href="#dashboard" class="nav-link active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="#approve-reject" class="nav-link"><i class="fas fa-check-circle"></i> Approve/Reject</a></li>
            <li><a href="#raise-queries" class="nav-link"><i class="fas fa-question-circle"></i> Raise Queries</a></li>
            <li><a href="#notifications" class="nav-link"><i class="fas fa-bell"></i> Notifications</a></li>
            <li><a href="logout.php" id="logout-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>

    <div class="main-content">
        <div class="header">
            <h1>Faculty Mentor Dashboard</h1>
            <div class="notification-bell">
                <i class="fas fa-bell"></i>
                <span class="notification-count"><?php echo $notification_count; ?></span>
            </div>
        </div>

        <div id="dashboard" class="section active">
            <?php if (isset($_SESSION['success'])) { echo "<p class='success'>" . $_SESSION['success'] . "</p>"; unset($_SESSION['success']); } ?>
            <?php if (isset($_SESSION['error'])) { echo "<p class='error'>" . $_SESSION['error'] . "</p>"; unset($_SESSION['error']); } ?>
            <h2>Welcome, <?php echo htmlspecialchars($full_name); ?></h2>
            <p>You have <?php echo $notification_count; ?> unread notifications.</p>
        </div>

        <div id="approve-reject" class="section">
            <h2>Approve/Reject Proposals</h2>
            <table>
                <thead>
                    <tr>
                        <th>Club</th>
                        <th>Proposal Title</th>
                        <th>Submitted By</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($proposal = $proposals_query->fetch_assoc()) { ?>
                        <tr>
                            <td><?php echo htmlspecialchars($proposal['club_name']); ?></td>
                            <td><?php echo htmlspecialchars($proposal['event_name']); ?></td>
                            <td><?php echo htmlspecialchars($proposal['submitted_by']); ?></td>
                            <td><?php echo htmlspecialchars($proposal['event_date']); ?></td>
                            <td><span class="status status-<?php echo strtolower(str_replace(' ', '-', $proposal['faculty_mentor_status'])); ?>"><?php echo htmlspecialchars($proposal['faculty_mentor_status']); ?></span></td>
                            <td>
                                <a class="btn btn-info" href="view-white-paper.php?proposal_id=<?php echo (int)$proposal['id']; ?>">View White Paper</a>
                                <?php if ($proposal['faculty_mentor_status'] === 'Pending' || $proposal['faculty_mentor_status'] === 'Under Review') { ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="proposal_id" value="<?php echo $proposal['id']; ?>">
                                        <button type="submit" name="approve" class="btn btn-success">Approve</button>
                                        <button type="submit" name="reject" class="btn btn-danger" onclick="return confirm('Enter rejection reason:'); document.getElementById('reject-reason-<?php echo $proposal['id']; ?>').style.display='block'; return false;">Reject</button>
                                    </form>
                                    <form method="POST" id="reject-reason-<?php echo $proposal['id']; ?>" style="display:none; margin-top:10px;">
                                        <input type="hidden" name="proposal_id" value="<?php echo $proposal['id']; ?>">
                                        <textarea name="reject_reason" placeholder="Reason for rejection" required></textarea>
                                        <button type="submit" name="reject" class="btn btn-danger">Submit Rejection</button>
                                    </form>
                                <?php } ?>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>

        <div id="raise-queries" class="section">
            <h2>Raise Queries</h2>
            <table>
                <thead>
                    <tr>
                        <th>Club</th>
                        <th>Proposal Title</th>
                        <th>Submitted By</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $proposals_query->data_seek(0);
                    while ($proposal = $proposals_query->fetch_assoc()) { 
                        if ($proposal['faculty_mentor_status'] === 'Pending' || $proposal['faculty_mentor_status'] === 'Under Review') {
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars($proposal['club_name']); ?></td>
                            <td><?php echo htmlspecialchars($proposal['event_name']); ?></td>
                            <td><?php echo htmlspecialchars($proposal['submitted_by']); ?></td>
                            <td><?php echo htmlspecialchars($proposal['event_date']); ?></td>
                            <td><span class="status status-<?php echo strtolower(str_replace(' ', '-', $proposal['faculty_mentor_status'])); ?>"><?php echo htmlspecialchars($proposal['faculty_mentor_status']); ?></span></td>
                            <td>
                                <form method="POST">
                                    <input type="hidden" name="proposal_id" value="<?php echo $proposal['id']; ?>">
                                    <div class="form-group">
                                        <label>Query Details:</label>
                                        <textarea name="query_details" required></textarea>
                                    </div>
                                    <div class="form-group">
                                        <label>Deadline:</label>
                                        <input type="date" name="query_deadline" required>
                                    </div>
                                    <button type="submit" name="query" class="btn btn-info">Raise Query</button>
                                </form>
                            </td>
                        </tr>
                    <?php } } ?>
                </tbody>
            </table>
        </div>

        <div id="notifications" class="section">
            <h2>Notifications</h2>
            <ul>
                <?php 
                $notifications_query->data_seek(0);
                while ($notification = $notifications_query->fetch_assoc()) { 
                ?>
                    <li><?php echo htmlspecialchars($notification['message']); ?> (<?php echo date('M j, Y, g:i A', strtotime($notification['created_at'])); ?>)</li>
                <?php } ?>
            </ul>
        </div>
    </div>

    <script>
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const sectionId = link.getAttribute('href').substring(1);
                document.querySelectorAll('.section').forEach(section => section.classList.remove('active'));
                document.getElementById(sectionId).classList.add('active');
                document.querySelectorAll('.nav-link').forEach(nav => nav.classList.remove('active'));
                link.classList.add('active');
            });
        });

        document.getElementById('logout-link').addEventListener('click', () => {
            window.location.href = 'logout.php';
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>