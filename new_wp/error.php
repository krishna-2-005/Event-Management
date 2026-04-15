<?php
session_start();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Error</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <h2>Error</h2>
    <p style="color:red"><?php echo isset($_SESSION['error']) ? htmlspecialchars($_SESSION['error']) : 'An unexpected error occurred.'; ?></p>
    <p><a href="login.php">Back to Login</a></p>
    <?php unset($_SESSION['error']); ?>
</body>
</html>