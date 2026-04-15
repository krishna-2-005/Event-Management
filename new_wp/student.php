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

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch user info
$stmt = $conn->prepare("SELECT full_name FROM users WHERE id = ?");
if ($stmt === false) {
    die("Prepare failed: " . $conn->error); // Debug: Show why prepare failed
}
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($full_name);
$stmt->fetch();
$stmt->close();

// Fetch approved events (assuming students see all approved events)
$stmt = $conn->prepare("SELECT p.event_name, p.event_date, p.event_location, c.club_name 
                        FROM proposals p 
                        JOIN clubs c ON p.club_id = c.id 
                        WHERE p.faculty_mentor_status = 'Approved' AND p.program_chair_status = 'Approved' 
                        ORDER BY p.event_date ASC");
if ($stmt === false) {
    die("Prepare failed: " . $conn->error); // Debug: Show why prepare failed
}
$stmt->execute();
$events_query = $stmt->get_result();

// Fetch notifications
$stmt = $conn->prepare("SELECT message, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
if ($stmt === false) {
    die("Prepare failed: " . $conn->error); // Debug: Show why prepare failed
}
$stmt->bind_param("i", $user_id);
$stmt->execute();
$notifications_query = $stmt->get_result();

$stmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
if ($stmt === false) {
    die("Prepare failed: " . $conn->error); // Debug: Show why prepare failed
}
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($notification_count);
$stmt->fetch();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NMIMS EventHub - Student Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #c52240;
            --primary-light: #c73c50;
            --primary-dark: #7a1526;
            --secondary: #333333;
            --light: #f8f9fa;
            --dark: #212529;
            --gray: #6c757d;
            --light-gray: #e9ecef;
            --success: #38b000;
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
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: var(--box-shadow);
            margin-bottom: 20px;
        }

        h2 {
            font-size: 1.5rem;
            margin-bottom: 15px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid var(--light-gray);
        }

        th {
            background-color: var(--light-gray);
        }

        .error {
            color: #e74c3c;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2>Student Dashboard</h2>
        <ul>
            <li><a href="#events" class="nav-link active"><i class="fas fa-calendar-alt"></i> Upcoming Events</a></li>
            <li><a href="#notifications" class="nav-link"><i class="fas fa-bell"></i> Notifications</a></li>
            <li><a href="logout.php" id="logout-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>

    <div class="main-content">
        <div class="header">
            <h1>Welcome, <?php echo htmlspecialchars($full_name); ?></h1>
            <div class="notification-bell">
                <i class="fas fa-bell"></i>
                <span class="notification-count"><?php echo $notification_count; ?></span>
            </div>
        </div>

        <div id="events" class="section active">
            <h2>Upcoming Events</h2>
            <table>
                <thead>
                    <tr>
                        <th>Event Name</th>
                        <th>Date</th>
                        <th>Location</th>
                        <th>Club</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($event = $events_query->fetch_assoc()) { ?>
                        <tr>
                            <td><?php echo htmlspecialchars($event['event_name']); ?></td>
                            <td><?php echo htmlspecialchars($event['event_date']); ?></td>
                            <td><?php echo htmlspecialchars($event['event_location']); ?></td>
                            <td><?php echo htmlspecialchars($event['club_name']); ?></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>

        <div id="notifications" class="section" style="display: none;">
            <h2>Notifications</h2>
            <ul>
                <?php while ($notification = $notifications_query->fetch_assoc()) { ?>
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
                document.querySelectorAll('.section').forEach(section => section.style.display = 'none');
                document.getElementById(sectionId).style.display = 'block';
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