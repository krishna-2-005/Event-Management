<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/layout.php';

$user = app_require_login();

$monthInput = (string) ($_GET['month'] ?? date('Y-m'));
if (!preg_match('/^\d{4}-\d{2}$/', $monthInput)) {
    $monthInput = date('Y-m');
}

$monthStart = DateTimeImmutable::createFromFormat('Y-m-d', $monthInput . '-01');
if (!$monthStart) {
    $monthStart = new DateTimeImmutable(date('Y-m-01'));
}

$monthStart = $monthStart->setTime(0, 0, 0);
$monthEnd = $monthStart->modify('last day of this month');
$startDate = $monthStart->format('Y-m-d');
$endDate = $monthEnd->format('Y-m-d');

$calendarItems = [];

if (app_table_exists('events')) {
    $eventStatusExpr = app_column_exists('events', 'event_status') ? 'e.event_status' : '"upcoming"';

    $eventVenueJoin = '';
    $eventVenueSelect = ', NULL AS venue_name';
    if (app_table_exists('venues') && app_column_exists('events', 'venue_id')) {
        $eventVenueJoin = ' LEFT JOIN venues v ON v.id = e.venue_id';
        $eventVenueSelect = ', v.venue_name';
    }

    $eventClubSelect = ', NULL AS club_name';
    $eventSchoolSelect = ', NULL AS school_name';
    $eventClubSchoolJoin = '';

    if (app_table_exists('clubs') && app_column_exists('events', 'club_id')) {
        $eventClubSchoolJoin .= ' LEFT JOIN clubs c ON c.id = e.club_id';
        $eventClubSelect = ', c.club_name';
    } elseif (
        app_table_exists('proposals')
        && app_column_exists('events', 'proposal_id')
        && app_column_exists('proposals', 'id')
        && app_column_exists('proposals', 'club_id')
        && app_table_exists('clubs')
    ) {
        $eventClubSchoolJoin .= ' LEFT JOIN proposals ep ON ep.id = e.proposal_id LEFT JOIN clubs c ON c.id = ep.club_id';
        $eventClubSelect = ', c.club_name';

        if (app_table_exists('schools') && app_column_exists('proposals', 'school_id')) {
            $eventClubSchoolJoin .= ' LEFT JOIN schools s ON s.id = ep.school_id';
            $eventSchoolSelect = ', s.school_name';
        }
    }

    if ($eventSchoolSelect === ', NULL AS school_name' && app_table_exists('schools') && app_column_exists('events', 'school_id')) {
        $eventClubSchoolJoin .= ' LEFT JOIN schools s ON s.id = e.school_id';
        $eventSchoolSelect = ', s.school_name';
    }

    $eventSql = 'SELECT e.id, e.event_name, e.event_date, e.start_time, e.end_time, '
        . $eventStatusExpr . ' AS status'
        . $eventVenueSelect
        . $eventClubSelect
        . $eventSchoolSelect
        . ' FROM events e'
        . $eventVenueJoin
        . $eventClubSchoolJoin
        . ' WHERE e.event_date BETWEEN ? AND ? ORDER BY e.event_date ASC, e.start_time ASC';

    $stmt = $conn->prepare($eventSql);
    $stmt->bind_param('ss', $startDate, $endDate);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($rows as $row) {
        $calendarItems[] = [
            'source' => 'event',
            'id' => (int) ($row['id'] ?? 0),
            'event_name' => (string) ($row['event_name'] ?? 'Event'),
            'event_date' => (string) ($row['event_date'] ?? ''),
            'start_time' => (string) ($row['start_time'] ?? ''),
            'end_time' => (string) ($row['end_time'] ?? ''),
            'status' => strtolower((string) ($row['status'] ?? 'approved')),
            'venue_name' => (string) ($row['venue_name'] ?? ''),
            'club_name' => (string) ($row['club_name'] ?? ''),
            'school_name' => (string) ($row['school_name'] ?? ''),
        ];
    }
}

if (app_table_exists('proposals')) {
    $submitterColumn = app_column_exists('proposals', 'submitted_by') ? 'submitted_by' : 'user_id';
    $statusColumn = app_column_exists('proposals', 'overall_status') ? 'overall_status' : 'faculty_mentor_status';

    $hasSchoolJoin = app_table_exists('schools') && app_column_exists('proposals', 'school_id');
    $schoolJoin = $hasSchoolJoin ? ' LEFT JOIN schools s ON s.id = p.school_id' : '';
    $schoolSelect = $hasSchoolJoin ? ', s.school_name' : ', NULL AS school_name';

    $hasVenueJoin = app_table_exists('venues') && app_column_exists('proposals', 'venue_id');
    $venueJoin = $hasVenueJoin ? ' LEFT JOIN venues v ON v.id = p.venue_id' : '';
    $venueSelect = $hasVenueJoin ? ', v.venue_name' : ', NULL AS venue_name';

    $hasTime = app_column_exists('proposals', 'start_time') && app_column_exists('proposals', 'end_time');
    $timeSelect = $hasTime ? ', p.start_time, p.end_time' : ', NULL AS start_time, NULL AS end_time';

    $sql = 'SELECT p.id, p.event_name, p.event_date, p.' . $statusColumn . ' AS status' . $timeSelect . $venueSelect . ', c.club_name' . $schoolSelect . ' FROM proposals p LEFT JOIN clubs c ON c.id = p.club_id' . $venueJoin . $schoolJoin . ' LEFT JOIN users u ON u.id = p.' . $submitterColumn . ' WHERE p.event_date BETWEEN ? AND ?';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $startDate, $endDate);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($rows as $row) {
        $status = strtolower(str_replace(' ', '_', (string) ($row['status'] ?? 'submitted')));
        $calendarItems[] = [
            'source' => 'proposal',
            'id' => (int) ($row['id'] ?? 0),
            'event_name' => (string) ($row['event_name'] ?? 'Proposal'),
            'event_date' => (string) ($row['event_date'] ?? ''),
            'start_time' => (string) ($row['start_time'] ?? ''),
            'end_time' => (string) ($row['end_time'] ?? ''),
            'status' => $status,
            'venue_name' => (string) ($row['venue_name'] ?? ''),
            'club_name' => (string) ($row['club_name'] ?? ''),
            'school_name' => (string) ($row['school_name'] ?? ''),
        ];
    }
}

if (app_table_exists('blocked_dates')) {
    $stmt = $conn->prepare('SELECT id, title, block_date, block_type FROM blocked_dates WHERE block_date BETWEEN ? AND ? ORDER BY block_date ASC');
    $stmt->bind_param('ss', $startDate, $endDate);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($rows as $row) {
        $calendarItems[] = [
            'source' => 'blocked',
            'id' => (int) ($row['id'] ?? 0),
            'event_name' => (string) ($row['title'] ?? 'Blocked Date'),
            'event_date' => (string) ($row['block_date'] ?? ''),
            'start_time' => '',
            'end_time' => '',
            'status' => 'blocked',
            'venue_name' => '',
            'club_name' => '',
            'school_name' => '',
        ];
    }
}

$itemsByDate = [];
foreach ($calendarItems as $item) {
    $date = $item['event_date'];
    if ($date === '') {
        continue;
    }
    if (!isset($itemsByDate[$date])) {
        $itemsByDate[$date] = [];
    }
    $itemsByDate[$date][] = $item;
}

$monthLabel = $monthStart->format('F Y');
$weekdayOffset = (int) $monthStart->format('N');
$daysInMonth = (int) $monthStart->format('t');
$prevMonth = $monthStart->modify('-1 month')->format('Y-m');
$nextMonth = $monthStart->modify('+1 month')->format('Y-m');

layout_render_header('Event Calendar', $user, 'event_calendar');
?>
<section class="panel">
    <div class="panel-header">
        <h3>Event Calendar</h3>
        <div class="inline-actions">
            <a class="btn secondary" href="event-calendar.php?month=<?php echo htmlspecialchars($prevMonth); ?>">Previous</a>
            <span class="badge pending"><?php echo htmlspecialchars($monthLabel); ?></span>
            <a class="btn secondary" href="event-calendar.php?month=<?php echo htmlspecialchars($nextMonth); ?>">Next</a>
        </div>
    </div>

    <div class="chip-grid" style="margin-bottom:14px;">
        <span class="chip"><span class="badge submitted">Submitted</span></span>
        <span class="chip"><span class="badge pending">Pending</span></span>
        <span class="chip"><span class="badge approved">Approved</span></span>
        <span class="chip"><span class="badge ongoing">Ongoing</span></span>
        <span class="chip"><span class="badge completed">Completed</span></span>
        <span class="chip"><span class="badge cancelled">Cancelled</span></span>
        <span class="chip"><span class="badge blocked">Blocked</span></span>
    </div>

    <div class="split">
        <div class="panel" style="padding:16px;">
            <h4 style="margin-bottom:10px;">Date Status Grid</h4>
            <div style="display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:8px;margin-bottom:8px;font-weight:700;color:#64748b;">
                <div>Mon</div><div>Tue</div><div>Wed</div><div>Thu</div><div>Fri</div><div>Sat</div><div>Sun</div>
            </div>
            <div style="display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:8px;">
                <?php for ($i = 1; $i < $weekdayOffset; $i++) { ?>
                    <div class="card" style="min-height:92px;padding:8px;opacity:.35;"></div>
                <?php } ?>

                <?php for ($day = 1; $day <= $daysInMonth; $day++) {
                    $dateKey = $monthStart->setDate((int) $monthStart->format('Y'), (int) $monthStart->format('m'), $day)->format('Y-m-d');
                    $dayItems = $itemsByDate[$dateKey] ?? [];
                    ?>
                    <button
                        type="button"
                        class="card calendar-day"
                        data-date="<?php echo htmlspecialchars($dateKey); ?>"
                        data-items="<?php echo htmlspecialchars(json_encode($dayItems, JSON_UNESCAPED_UNICODE)); ?>"
                        style="min-height:92px;padding:8px;text-align:left;border:1px solid var(--line);cursor:pointer;"
                    >
                        <div style="font-weight:700;margin-bottom:6px;color:#25364f;font-size:1.1rem;"><?php echo $day; ?></div>
                        <?php if (!empty($dayItems)) {
                            $preview = array_slice($dayItems, 0, 2);
                            foreach ($preview as $previewItem) {
                                $statusClass = preg_replace('/[^a-z0-9_]/', '', strtolower((string) ($previewItem['status'] ?? 'pending')));
                                ?>
                                <div style="margin-bottom:4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?php echo htmlspecialchars((string) ($previewItem['event_name'] ?? 'Event')); ?></div>
                                <span class="badge <?php echo htmlspecialchars($statusClass); ?>" style="display:inline-block;margin-bottom:4px;"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $statusClass))); ?></span>
                            <?php }
                        } ?>
                    </button>
                <?php } ?>
            </div>
        </div>

        <div class="panel" id="calendar-day-details" style="padding:16px;">
            <h4 id="calendar-day-title">Select a date</h4>
            <p id="calendar-day-subtitle" style="color:#64748b;">Click any date to view event/proposal details with status and venue.</p>
            <div class="timeline" id="calendar-day-items"></div>
        </div>
    </div>
</section>

<script>
(() => {
    const dayCards = document.querySelectorAll('.calendar-day');
    const dayTitle = document.getElementById('calendar-day-title');
    const daySubtitle = document.getElementById('calendar-day-subtitle');
    const dayItemsWrap = document.getElementById('calendar-day-items');

    if (!dayCards.length || !dayTitle || !daySubtitle || !dayItemsWrap) {
        return;
    }

    const toStatusClass = (status) => String(status || 'pending').toLowerCase().replace(/[^a-z0-9_]/g, '');
    const toStatusLabel = (status) => {
        const normalized = toStatusClass(status);
        return normalized.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
    };

    dayCards.forEach((card) => {
        card.addEventListener('click', () => {
            const date = card.getAttribute('data-date') || '';
            const itemsJson = card.getAttribute('data-items') || '[]';
            let items = [];
            try {
                items = JSON.parse(itemsJson);
            } catch (error) {
                items = [];
            }

            dayTitle.textContent = date;
            daySubtitle.textContent = items.length ? `${items.length} item(s) found` : 'No events or proposals on this date.';

            if (!items.length) {
                dayItemsWrap.innerHTML = '';
                return;
            }

            dayItemsWrap.innerHTML = items.map((item) => {
                const statusClass = toStatusClass(item.status);
                const statusLabel = toStatusLabel(item.status);
                const sourceLabel = item.source === 'blocked' ? 'Blocked Date' : (item.source === 'proposal' ? 'Proposal' : 'Event');
                const venue = item.venue_name || 'N/A';
                const timeText = item.start_time && item.end_time ? `${item.start_time} - ${item.end_time}` : 'N/A';
                const club = item.club_name || 'N/A';
                const school = item.school_name || 'N/A';

                return `
                    <div class="timeline-item">
                        <strong>${item.event_name || 'Event'}</strong>
                        <p><span class="badge ${statusClass}">${statusLabel}</span> · ${sourceLabel}</p>
                        <p><strong>Venue:</strong> ${venue}</p>
                        <p><strong>Time:</strong> ${timeText}</p>
                        <p><strong>Club:</strong> ${club}</p>
                        <p><strong>School:</strong> ${school}</p>
                    </div>
                `;
            }).join('');
        });
    });
})();
</script>
<?php layout_render_footer(); ?>
