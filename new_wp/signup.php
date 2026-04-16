<?php
session_start();
require_once __DIR__ . '/inc/app.php';

$stmt = $conn->prepare("SELECT id, club_name FROM clubs ORDER BY club_name");
$stmt->execute();
$clubs_result = $stmt->get_result();
$clubs = [];
while ($row = $clubs_result->fetch_assoc()) {
    $clubs[] = $row;
}
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = filter_var($_POST['full_name'], FILTER_SANITIZE_STRING);
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'];
    $sub_role = isset($_POST['sub_role']) ? $_POST['sub_role'] : null;
    $club_id = in_array($role, ['club_head', 'faculty_mentor'], true) ? (int)$_POST['club_id'] : null;

    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $_SESSION['error'] = "Email already registered!";
    } else {
        $stmt = $conn->prepare("INSERT INTO users (full_name, email, password, role, sub_role, club_id) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssi", $full_name, $email, $password, $role, $sub_role, $club_id);
        try {
            if ($stmt->execute()) {
                $_SESSION['success'] = "Registration successful! Please log in.";
                header("Location: login.php");
                exit();
            }
        } catch (mysqli_sql_exception $e) {
            if (strpos($e->getMessage(), "Only one Club Head is allowed per club") !== false) {
                $_SESSION['error'] = "This club already has a Club Head!";
            } else {
                $_SESSION['error'] = "Error: " . $e->getMessage();
            }
        }
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NMIMS EventHub - Signup</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Same CSS as previous signup.php */
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
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .signup-container {
            background-color: #fff;
            padding: 40px;
            border-radius: 10px;
            box-shadow: var(--box-shadow);
            width: 100%;
            max-width: 400px;
        }

        h2 {
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 20px;
            text-align: center;
            color: var(--primary);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-weight: 500;
            margin-bottom: 5px;
            color: var(--secondary);
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--light-gray);
            border-radius: 5px;
            font-size: 1rem;
            transition: var(--transition);
        }

        .form-group input:focus,
        .form-group select:focus {
            border-color: var(--primary);
            outline: none;
        }

        .btn {
            width: 100%;
            padding: 12px;
            background-color: var(--primary);
            color: #fff;
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
        }

        .btn:hover {
            background-color: var(--primary-dark);
        }

        .success {
            color: var(--success);
            text-align: center;
            margin-bottom: 20px;
        }

        .error {
            color: #e74c3c;
            text-align: center;
            margin-bottom: 20px;
        }

        .login-link {
            text-align: center;
            margin-top: 20px;
        }

        .login-link a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
        }

        .login-link a:hover {
            text-decoration: underline;
        }

        #club-group {
            display: none;
        }
    </style>
</head>
<body>
    <div class="signup-container">
    <h2>Welcome to NMIMS EventHub</h2>
        <h3 align="center">Sign Up</h3>
        <?php if (isset($_SESSION['success'])) { echo "<p class='success'>" . $_SESSION['success'] . "</p>"; unset($_SESSION['success']); } ?>
        <?php if (isset($_SESSION['error'])) { echo "<p class='error'>" . $_SESSION['error'] . "</p>"; unset($_SESSION['error']); } ?>
        <form method="POST">
            <div class="form-group">
                <label>Full Name:</label>
                <input type="text" name="full_name" required>
            </div>
            <div class="form-group">
                <label>Email:</label>
                <input type="email" name="email" required>
            </div>
            <div class="form-group">
                <label>Password:</label>
                <input type="password" name="password" required>
            </div>
            <div class="form-group">
                <label>Role:</label>
                <select name="role" id="role" onchange="toggleSubRole()" required>
                    <option value="">Select Role</option>
                    <option value="student">Student</option>
                    <option value="club_head">Club Head</option>
                    <option value="faculty_mentor">Faculty Mentor</option>
                </select>
            </div>
            <div class="form-group" id="sub-role-group" style="display: none;">
                <label>Role Detail (for Faculty Mentor):</label>
                <select name="sub_role" id="sub_role" onchange="toggleClub()">
                    <option value="">Select Role Detail</option>
                    <option value="faculty-mentor">Faculty Mentor</option>
                </select>
            </div>
            <div class="form-group" id="club-group">
                <label>Club Name:</label>
                <select name="club_id" id="club_id" required>
                    <option value="">Select Club</option>
                    <?php foreach ($clubs as $club) { ?>
                        <option value="<?php echo $club['id']; ?>"><?php echo htmlspecialchars($club['club_name']); ?></option>
                    <?php } ?>
                </select>
            </div>
            <button type="submit" class="btn">Sign Up</button>
        </form>
        <div class="login-link">
            Already have an account? <a href="login.php">Log in</a>
        </div>
    </div>

    <script>
        function toggleSubRole() {
            const role = document.getElementById('role').value;
            const subRoleGroup = document.getElementById('sub-role-group');
            const clubGroup = document.getElementById('club-group');
            subRoleGroup.style.display = role === 'faculty_mentor' ? 'block' : 'none';
            clubGroup.style.display = role === 'club_head' || role === 'faculty_mentor' ? 'block' : 'none';
            if (role !== 'faculty_mentor' && role !== 'club_head') {
                document.getElementById('club_id').required = false;
            }
        }

        function toggleClub() {
            const subRole = document.getElementById('sub_role').value;
            const clubGroup = document.getElementById('club-group');
            clubGroup.style.display = subRole === 'faculty-mentor' ? 'block' : 'none';
            document.getElementById('club_id').required = subRole === 'faculty-mentor';
        }
    </script>
</body>
</html>
<?php $conn->close(); ?>