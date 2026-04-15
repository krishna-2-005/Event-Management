<?php
include 'config.php';

$status = [
    'approved' => $conn->query("SELECT COUNT(*) FROM proposals WHERE program_chair_status = 'Approved'")->fetch_row()[0],
    'pending' => $conn->query("SELECT COUNT(*) FROM proposals WHERE program_chair_status = 'Pending'")->fetch_row()[0],
    'rejected' => $conn->query("SELECT COUNT(*) FROM proposals WHERE program_chair_status = 'Rejected'")->fetch_row()[0],
    'under_review' => $conn->query("SELECT COUNT(*) FROM proposals WHERE faculty_mentor_status = 'Under Review'")->fetch_row()[0]
];

$types = [];
$result = $conn->query("SELECT event_type, COUNT(*) as count FROM proposals GROUP BY event_type");
while ($row = $result->fetch_assoc()) {
    $types[$row['event_type']] = $row['count'];
}

$timeline = ['dates' => [], 'approved' => [], 'rejected' => []];
$result = $conn->query("SELECT DATE(created_at) as date, program_chair_status FROM proposals ORDER BY created_at");
while ($row = $result->fetch_assoc()) {
    $date = $row['date'];
    if (!in_array($date, $timeline['dates'])) {
        $timeline['dates'][] = $date;
        $timeline['approved'][$date] = 0;
        $timeline['rejected'][$date] = 0;
    }
    if ($row['program_chair_status'] === 'Approved') $timeline['approved'][$date]++;
    if ($row['program_chair_status'] === 'Rejected') $timeline['rejected'][$date]++;
}
$timeline['approved'] = array_values($timeline['approved']);
$timeline['rejected'] = array_values($timeline['rejected']);

header('Content-Type: application/json');
echo json_encode(['status' => $status, 'types' => $types, 'timeline' => $timeline]);

$conn->close();
?>