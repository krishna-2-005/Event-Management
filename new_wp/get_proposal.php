<?php
include 'config.php';
header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['error' => 'No proposal ID provided']);
    exit();
}

$proposal_id = (int)$_GET['id'];
$stmt = $conn->prepare("SELECT p.*, u.full_name AS submitted_by 
                        FROM proposals p 
                        JOIN users u ON p.user_id = u.id 
                        WHERE p.id = ?");
$stmt->bind_param("i", $proposal_id);
$stmt->execute();
$result = $stmt->get_result();
$proposal = $result->fetch_assoc();

if ($proposal) {
    // Rename 'event_description' to 'description' to match JavaScript
    $proposal['description'] = $proposal['event_description'];
    echo json_encode($proposal);
} else {
    echo json_encode(['error' => 'Proposal not found']);
}

$stmt->close();
$conn->close();
?>