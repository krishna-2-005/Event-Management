<?php
include 'config.php';

$month = (int)$_GET['month'];
$year = (int)$_GET['year'];
$club_id = (int)$_GET['club_id'];

$stmt = $conn->prepare("SELECT event_name, event_date FROM proposals WHERE faculty_mentor_status = 'Approved' AND program_chair_status = 'Approved' AND club_id = ? AND MONTH(event_date) = ? AND YEAR(event_date) = ?");
$stmt->bind_param("iii", $club_id, $month, $year);
$stmt->execute();
$result = $stmt->get_result();

$events = [];
while ($row = $result->fetch_assoc()) {
    $events[] = $row;
}

header('Content-Type: application/json');
echo json_encode($events);

$stmt->close();
$conn->close();
?>