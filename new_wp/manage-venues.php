<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/layout.php';

$user = app_require_login();
$role = app_normalize_role((string) $user['role'], $user['sub_role'] ?? null);

if ($role !== 'super_admin') {
    app_flash_set('error', 'This page is for super admin only.');
    app_redirect(app_role_dashboard($role));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $venueName = app_clean_text((string) ($_POST['venue_name'] ?? ''));
    $venueType = app_clean_text((string) ($_POST['venue_type'] ?? ''));
    $capacity = (int) ($_POST['capacity'] ?? 0);
    $location = app_clean_text((string) ($_POST['location_details'] ?? ''));
    $managedBy = app_clean_text((string) ($_POST['managed_by_role'] ?? 'admin_office'));
    if ($venueName !== '') {
        $stmt = $conn->prepare('INSERT INTO venues (venue_name, venue_type, capacity, location_details, managed_by_role) VALUES (?, ?, ?, ?, ?)');
        $stmt->bind_param('ssiss', $venueName, $venueType, $capacity, $location, $managedBy);
        $stmt->execute();
        $stmt->close();
        app_flash_set('success', 'Venue added successfully.');
    }
    app_redirect('manage-venues.php');
}

$venues = [];
if (app_table_exists('venues')) {
    $result = $conn->query('SELECT * FROM venues ORDER BY venue_name ASC');
    if ($result) {
        $venues = $result->fetch_all(MYSQLI_ASSOC);
    }
}

layout_render_header('Manage Venues', $user, 'manage_venues');
?>
<section class="panel">
    <div class="panel-header"><h3>Add Venue</h3></div>
    <form method="post" class="form-grid">
        <div class="field"><label>Venue Name</label><input name="venue_name" required></div>
        <div class="field"><label>Venue Type</label><input name="venue_type"></div>
        <div class="field"><label>Capacity</label><input type="number" name="capacity" min="0" value="0"></div>
        <div class="field"><label>Managed By Role</label><input name="managed_by_role" value="admin_office"></div>
        <div class="field field-span"><label>Location Details</label><textarea name="location_details"></textarea></div>
        <div class="field" style="justify-content:end;"><label>&nbsp;</label><button class="btn" type="submit">Save Venue</button></div>
    </form>
</section>

<section class="panel">
    <div class="panel-header"><h3>Venues</h3></div>
    <div class="card-grid">
        <?php foreach ($venues as $venue) { ?>
            <div class="card">
                <p><?php echo htmlspecialchars($venue['venue_type'] ?? ''); ?></p>
                <h3><?php echo htmlspecialchars($venue['venue_name']); ?></h3>
                <p>Capacity: <?php echo (int) ($venue['capacity'] ?? 0); ?></p>
            </div>
        <?php } ?>
    </div>
</section>
<?php layout_render_footer(); ?>
