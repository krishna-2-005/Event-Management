<?php
// Database connection
$host = 'localhost';
$dbname = 'event';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    $pdo = null;
}

// Fetch upcoming approved events
$upcomingEvents = [];
$featuredEvent = null;
$totalEvents = 0;
$totalClubs = 0;
$totalSchools = 5;

if ($pdo) {
    // Helper function to check if column exists using INFORMATION_SCHEMA
    function column_exists($pdo, $table, $column) {
        try {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = ? 
                AND COLUMN_NAME = ?
            ");
            $stmt->execute([$table, $column]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    // Build status filter for events/proposals
    $statusFilter = "";
    if (column_exists($pdo, 'events', 'status')) {
        $statusFilter = "WHERE e.status IN ('approved','upcoming')";
    } elseif (column_exists($pdo, 'proposals', 'proposal_status')) {
        $statusFilter = "WHERE p.proposal_status IN ('approved','upcoming')";
    } else {
        $statusFilter = ""; // No status filtering available
    }

    // Total counts (with fallback)
    try {
        $countQuery = column_exists($pdo, 'events', 'status') 
            ? "SELECT COUNT(*) FROM events WHERE status IN ('approved','upcoming')"
            : "SELECT COUNT(*) FROM events";
        $totalEvents = $pdo->query($countQuery)->fetchColumn();
    } catch (Exception $e) {
        $totalEvents = 0;
    }

    try {
        $clubQuery = column_exists($pdo, 'clubs', 'status')
            ? "SELECT COUNT(*) FROM clubs WHERE status='active'"
            : "SELECT COUNT(*) FROM clubs";
        $totalClubs = $pdo->query($clubQuery)->fetchColumn();
    } catch (Exception $e) {
        $totalClubs = 0;
    }

    // Featured event - latest approved upcoming event
    try {
        $dateFilter = "e.event_date >= CURDATE() OR e.event_date IS NOT NULL";
        $query = "
            SELECT e.*, c.club_name, s.school_name, v.venue_name,
                   p.start_time, p.end_time, p.event_details, p.proposal_status
            FROM events e
            LEFT JOIN clubs c ON c.id = e.club_id
            LEFT JOIN schools s ON s.id = e.school_id
            LEFT JOIN venues v ON v.id = e.venue_id
            LEFT JOIN proposals p ON p.id = e.proposal_id
            WHERE ($dateFilter)
            ORDER BY e.event_date ASC
            LIMIT 1
        ";
        $featuredStmt = $pdo->query($query);
        $featuredEvent = $featuredStmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $featuredEvent = null;
    }

    // Upcoming events
    try {
        $query = "
            SELECT e.*, c.club_name, s.school_name, v.venue_name,
                   p.start_time, p.end_time, p.event_details,
                   cl.club_logo, p.proposal_status
            FROM events e
            LEFT JOIN clubs c ON c.id = e.club_id
            LEFT JOIN schools s ON s.id = e.school_id
            LEFT JOIN venues v ON v.id = e.venue_id
            LEFT JOIN proposals p ON p.id = e.proposal_id
            LEFT JOIN clubs cl ON cl.id = e.club_id
            WHERE (e.event_date >= CURDATE() OR e.event_date IS NOT NULL)
            ORDER BY e.event_date ASC
            LIMIT 6
        ";
        $eventsStmt = $pdo->query($query);
        $upcomingEvents = $eventsStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $upcomingEvents = [];
    }
}

function formatEventDate($date) {
    return date('d M', strtotime($date));
}

function formatDay($date) {
    return date('d', strtotime($date));
}

function formatMonth($date) {
    return date('M', strtotime($date));
}

function formatTime($time) {
    if (!$time) return 'TBA';
    return date('h:i A', strtotime($time));
}

function getStatusColor($status) {
    $statusMap = [
        'approved' => '#22c55e',
        'upcoming' => '#3b82f6',
        'ongoing'  => '#f59e0b',
        'completed'=> '#6b7280',
        'pending' => '#f59e0b',
    ];
    return $statusMap[strtolower($status)] ?? '#6b7280';
}

function getStatusLabel($status) {
    $statusMap = [
        'approved' => 'Approved',
        'upcoming' => 'Upcoming',
        'ongoing'  => 'Ongoing',
        'completed'=> 'Completed',
        'pending' => 'Pending',
    ];
    return $statusMap[strtolower($status)] ?? ucfirst($status);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NMIMS EventHub — Discover Campus Events</title>
    <meta name="description" content="Discover, explore and register for the best campus events at NMIMS Hyderabad.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        /* ======================================
           ROOT VARIABLES
        ====================================== */
        :root {
            --primary: #c21834;
            --primary-hover: #a01429;
            --primary-soft: #fce7eb;
            --primary-light: #ef4444;
            --dark-bg: #1a1a2e;
            --dark-card: #16213e;
            --dark-border: #0f3460;
            --text-dark: #1e293b;
            --text-muted: #64748b;
            --text-light: #f8fafc;
            --bg-light: #f8fafc;
            --bg-section: #f1f5f9;
            --white: #ffffff;
            --border: #e2e8f0;
            --success: #22c55e;
            --warning: #f59e0b;
            --info: #3b82f6;
            --shadow-sm: 0 2px 8px rgba(0,0,0,0.06);
            --shadow-md: 0 8px 24px rgba(0,0,0,0.08);
            --shadow-lg: 0 20px 48px rgba(0,0,0,0.12);
            --shadow-xl: 0 32px 64px rgba(0,0,0,0.16);
            --radius-sm: 8px;
            --radius-md: 14px;
            --radius-lg: 20px;
            --radius-xl: 28px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* ======================================
           RESET & BASE
        ====================================== */
        *, *::before, *::after {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html { scroll-behavior: smooth; }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-light);
            color: var(--text-dark);
            line-height: 1.6;
            overflow-x: hidden;
        }

        a { text-decoration: none; color: inherit; }
        ul { list-style: none; }
        img { max-width: 100%; height: auto; }

        .container {
            width: 100%;
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 24px;
        }

        /* ======================================
           ANNOUNCEMENT BAR
        ====================================== */
        .announcement-bar {
            background: linear-gradient(90deg, var(--primary), #7c0d1e);
            color: white;
            text-align: center;
            padding: 10px 24px;
            font-size: 14px;
            font-weight: 500;
            position: relative;
        }

        .announcement-bar span {
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .announcement-bar .pulse-dot {
            width: 8px;
            height: 8px;
            background: #fde68a;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(1.4); }
        }

        /* ======================================
           NAVBAR
        ====================================== */
        .navbar {
            background: var(--white);
            border-bottom: 1px solid var(--border);
            position: sticky;
            top: 0;
            z-index: 999;
            box-shadow: var(--shadow-sm);
        }

        .nav-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 76px;
            gap: 32px;
        }

        /* Logo */
        .nav-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-shrink: 0;
        }

        .nav-brand img {
            height: 54px;
            width: auto;
            object-fit: contain;
        }

        .nav-brand-text {
            display: flex;
            flex-direction: column;
        }

        .nav-brand-text strong {
            font-size: 18px;
            font-weight: 800;
            color: var(--primary);
            letter-spacing: -0.5px;
            line-height: 1;
        }

        .nav-brand-text small {
            font-size: 12px;
            color: var(--text-muted);
            font-weight: 500;
        }

        /* Nav Links */
        .nav-links {
            display: flex;
            align-items: center;
            gap: 8px;
            flex: 1;
            justify-content: center;
        }

        .nav-links a {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            border-radius: var(--radius-sm);
            font-size: 15px;
            font-weight: 500;
            color: var(--text-muted);
            transition: var(--transition);
        }

        .nav-links a:hover,
        .nav-links a.active {
            color: var(--primary);
            background: var(--primary-soft);
        }

        /* Nav Actions */
        .nav-actions {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-shrink: 0;
        }

        .nav-search-btn {
            width: 42px;
            height: 42px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            background: var(--white);
            color: var(--text-muted);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: var(--transition);
            font-size: 16px;
        }

        .nav-search-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        .btn-nav-login {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 22px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: var(--radius-sm);
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
        }

        .btn-nav-login:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
            box-shadow: 0 8px 20px rgba(194,24,52,0.3);
        }

        .hamburger {
            display: none;
            flex-direction: column;
            gap: 5px;
            cursor: pointer;
            padding: 8px;
            border-radius: var(--radius-sm);
            background: var(--bg-light);
            border: 1px solid var(--border);
        }

        .hamburger span {
            width: 22px;
            height: 2px;
            background: var(--text-dark);
            border-radius: 99px;
            transition: var(--transition);
        }

        /* ======================================
           HERO SECTION
        ====================================== */
        .hero {
            background: linear-gradient(135deg, #1a0a0d 0%, #2d1520 40%, #1a0a0d 100%);
            position: relative;
            overflow: hidden;
            padding: 90px 0 80px;
            color: white;
        }

        /* Animated background particles */
        .hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(circle at 20% 50%, rgba(194,24,52,0.3) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(194,24,52,0.2) 0%, transparent 40%),
                radial-gradient(circle at 60% 80%, rgba(194,24,52,0.15) 0%, transparent 40%);
        }

        .hero-grid {
            position: relative;
            z-index: 1;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
            align-items: center;
        }

        .hero-content {}

        .hero-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 99px;
            padding: 8px 18px;
            font-size: 13px;
            font-weight: 600;
            color: rgba(255,255,255,0.85);
            letter-spacing: 0.08em;
            text-transform: uppercase;
            margin-bottom: 24px;
        }

        .hero-eyebrow i {
            color: #fde68a;
        }

        .hero h1 {
            font-size: clamp(36px, 5vw, 56px);
            font-weight: 900;
            line-height: 1.1;
            letter-spacing: -2px;
            margin-bottom: 20px;
        }

        .hero h1 .highlight {
            color: var(--primary-light);
            position: relative;
        }

        .hero-desc {
            font-size: 17px;
            color: rgba(255,255,255,0.72);
            line-height: 1.7;
            margin-bottom: 36px;
            max-width: 520px;
        }

        .hero-search {
            display: flex;
            background: rgba(255,255,255,0.07);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: var(--radius-md);
            overflow: hidden;
            backdrop-filter: blur(10px);
            margin-bottom: 40px;
        }

        .hero-search input {
            flex: 1;
            padding: 18px 20px;
            background: transparent;
            border: none;
            outline: none;
            color: white;
            font-size: 15px;
            font-family: 'Inter', sans-serif;
        }

        .hero-search input::placeholder {
            color: rgba(255,255,255,0.45);
        }

        .hero-search-btn {
            padding: 14px 24px;
            background: var(--primary);
            border: none;
            color: white;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
        }

        .hero-search-btn:hover {
            background: var(--primary-hover);
        }

        .hero-stats {
            display: flex;
            gap: 32px;
        }

        .hero-stat-item {}

        .hero-stat-item strong {
            display: block;
            font-size: 32px;
            font-weight: 800;
            color: white;
            line-height: 1;
            margin-bottom: 4px;
        }

        .hero-stat-item span {
            font-size: 13px;
            color: rgba(255,255,255,0.55);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        /* Hero Right - Featured Event Card */
        .hero-featured-card {
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: var(--radius-xl);
            padding: 32px;
            backdrop-filter: blur(20px);
            position: relative;
        }

        .hero-featured-label {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: var(--primary);
            color: white;
            font-size: 12px;
            font-weight: 700;
            padding: 6px 14px;
            border-radius: 99px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            margin-bottom: 20px;
        }

        .hero-featured-title {
            font-size: 24px;
            font-weight: 800;
            color: white;
            margin-bottom: 12px;
            line-height: 1.3;
        }

        .hero-featured-desc {
            font-size: 14px;
            color: rgba(255,255,255,0.6);
            margin-bottom: 24px;
            line-height: 1.7;
        }

        .hero-event-meta {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-bottom: 28px;
        }

        .hero-event-meta-item {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
            color: rgba(255,255,255,0.8);
        }

        .hero-event-meta-item i {
            width: 32px;
            height: 32px;
            background: rgba(255,255,255,0.08);
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-light);
            flex-shrink: 0;
            font-size: 13px;
        }

        .hero-event-date-badge {
            display: flex;
            align-items: center;
            gap: 16px;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: var(--radius-md);
            padding: 16px 20px;
            margin-bottom: 20px;
        }

        .date-box {
            width: 56px;
            height: 56px;
            background: var(--primary);
            border-radius: var(--radius-sm);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .date-box-day {
            font-size: 22px;
            font-weight: 800;
            color: white;
            line-height: 1;
        }

        .date-box-month {
            font-size: 11px;
            color: rgba(255,255,255,0.8);
            text-transform: uppercase;
            font-weight: 600;
        }

        .date-info {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .date-info strong {
            font-size: 15px;
            color: white;
        }

        .date-info span {
            font-size: 13px;
            color: rgba(255,255,255,0.55);
        }

        .hero-cta-row {
            display: flex;
            gap: 12px;
        }

        .btn-hero-primary {
            flex: 1;
            padding: 14px 20px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: var(--radius-md);
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition);
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-hero-primary:hover {
            background: var(--primary-hover);
            transform: translateY(-2px);
        }

        .btn-hero-ghost {
            padding: 14px 20px;
            background: transparent;
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: var(--radius-md);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-hero-ghost:hover {
            background: rgba(255,255,255,0.08);
            border-color: rgba(255,255,255,0.35);
        }

        /* No featured event state */
        .hero-no-event {
            text-align: center;
            padding: 40px 20px;
        }

        .hero-no-event i {
            font-size: 48px;
            color: rgba(255,255,255,0.2);
            margin-bottom: 16px;
        }

        .hero-no-event p {
            color: rgba(255,255,255,0.45);
            font-size: 15px;
        }

        /* ======================================
           CATEGORIES
        ====================================== */
        .section {
            padding: 80px 0;
        }

        .section-label {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--primary-soft);
            color: var(--primary);
            font-size: 13px;
            font-weight: 700;
            padding: 6px 14px;
            border-radius: 99px;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            margin-bottom: 14px;
        }

        .section-title {
            font-size: clamp(26px, 4vw, 38px);
            font-weight: 800;
            color: var(--text-dark);
            letter-spacing: -1px;
            margin-bottom: 10px;
        }

        .section-desc {
            font-size: 16px;
            color: var(--text-muted);
            max-width: 540px;
            margin-bottom: 48px;
        }

        .section-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-bottom: 48px;
        }

        .view-all-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: var(--primary);
            font-size: 14px;
            font-weight: 600;
            transition: var(--transition);
            flex-shrink: 0;
        }

        .view-all-link:hover {
            gap: 10px;
        }

        /* Category chips */
        .category-chips {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 48px;
        }

        .category-chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 22px;
            background: white;
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            font-size: 14px;
            font-weight: 600;
            color: var(--text-dark);
            cursor: pointer;
            transition: var(--transition);
            box-shadow: var(--shadow-sm);
        }

        .category-chip i {
            font-size: 16px;
            color: var(--primary);
        }

        .category-chip:hover,
        .category-chip.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
            transform: translateY(-3px);
            box-shadow: 0 12px 24px rgba(194,24,52,0.2);
        }

        .category-chip:hover i,
        .category-chip.active i {
            color: white;
        }

        /* ======================================
           LIVE TICKER
        ====================================== */
        .live-ticker {
            background: var(--dark-bg);
            color: white;
            padding: 14px 0;
            overflow: hidden;
        }

        .ticker-inner {
            display: flex;
            align-items: center;
            gap: 0;
        }

        .ticker-label {
            background: var(--primary);
            color: white;
            padding: 6px 18px;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            border-radius: 4px;
            flex-shrink: 0;
            margin-right: 24px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .ticker-track {
            overflow: hidden;
            flex: 1;
        }

        .ticker-content {
            display: flex;
            gap: 48px;
            animation: ticker 30s linear infinite;
            white-space: nowrap;
        }

        .ticker-item {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            color: rgba(255,255,255,0.8);
        }

        .ticker-item i {
            color: var(--primary-light);
        }

        @keyframes ticker {
            0% { transform: translateX(0); }
            100% { transform: translateX(-50%); }
        }

        /* ======================================
           EVENT CARDS
        ====================================== */
        .events-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 28px;
        }

        .event-card {
            background: white;
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            overflow: hidden;
            transition: var(--transition);
            display: flex;
            flex-direction: column;
            box-shadow: var(--shadow-sm);
        }

        .event-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-lg);
            border-color: transparent;
        }

        .event-card-img {
            height: 200px;
            background: linear-gradient(135deg, #1a0a0d, #3d1520);
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .event-card-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .event-card-img-placeholder {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #2d0f18 0%, #1a0a0d 100%);
        }

        .event-card-img-placeholder i {
            font-size: 48px;
            color: rgba(255,255,255,0.15);
        }

        .event-card-badges {
            position: absolute;
            top: 14px;
            left: 14px;
            right: 14px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .event-badge-status {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 12px;
            border-radius: 99px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            backdrop-filter: blur(4px);
        }

        .event-badge-approved {
            background: rgba(34,197,94,0.9);
            color: white;
        }

        .event-badge-upcoming {
            background: rgba(59,130,246,0.9);
            color: white;
        }

        .event-badge-ongoing {
            background: rgba(245,158,11,0.9);
            color: white;
        }

        .event-date-chip {
            background: rgba(0,0,0,0.6);
            backdrop-filter: blur(4px);
            color: white;
            padding: 6px 12px;
            border-radius: 99px;
            font-size: 12px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .event-card-body {
            padding: 24px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .event-club-tag {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            font-weight: 600;
            color: var(--primary);
            background: var(--primary-soft);
            padding: 4px 10px;
            border-radius: 99px;
            margin-bottom: 12px;
        }

        .event-card-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-dark);
            line-height: 1.4;
            margin-bottom: 10px;
        }

        .event-card-desc {
            font-size: 13px;
            color: var(--text-muted);
            line-height: 1.6;
            margin-bottom: 18px;
            flex: 1;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .event-card-meta {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 20px;
        }

        .event-meta-row {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            color: var(--text-muted);
        }

        .event-meta-row i {
            width: 18px;
            color: var(--primary);
            flex-shrink: 0;
        }

        .event-card-footer {
            display: flex;
            gap: 10px;
        }

        .btn-event-primary {
            flex: 1;
            padding: 12px 16px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: var(--radius-sm);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .btn-event-primary:hover {
            background: var(--primary-hover);
        }

        .btn-event-ghost {
            padding: 12px 14px;
            background: var(--bg-light);
            color: var(--text-muted);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .btn-event-ghost:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        /* No events */
        .no-events {
            grid-column: 1/-1;
            text-align: center;
            padding: 60px 20px;
            background: white;
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
        }

        .no-events i {
            font-size: 56px;
            color: var(--border);
            margin-bottom: 20px;
        }

        .no-events h3 {
            font-size: 22px;
            font-weight: 700;
            color: var(--text-muted);
            margin-bottom: 10px;
        }

        .no-events p {
            font-size: 15px;
            color: var(--text-muted);
        }

        /* ======================================
           SCHOOLS SECTION
        ====================================== */
        .schools-section {
            background: var(--dark-bg);
            padding: 80px 0;
            position: relative;
            overflow: hidden;
        }

        .schools-section::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at 30% 50%, rgba(194,24,52,0.15) 0%, transparent 60%);
        }

        .schools-section .section-title,
        .schools-section .section-label {
            color: white;
        }

        .schools-section .section-label {
            background: rgba(255,255,255,0.08);
            color: rgba(255,255,255,0.8);
        }

        .schools-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 16px;
            position: relative;
            z-index: 1;
        }

        .school-card {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: var(--radius-lg);
            padding: 24px 16px;
            text-align: center;
            transition: var(--transition);
            cursor: pointer;
        }

        .school-card:hover {
            background: rgba(255,255,255,0.1);
            border-color: rgba(194,24,52,0.5);
            transform: translateY(-6px);
        }

        .school-card-icon {
            width: 56px;
            height: 56px;
            background: linear-gradient(135deg, var(--primary), #7c0d1e);
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            font-size: 22px;
            color: white;
            box-shadow: 0 8px 20px rgba(194,24,52,0.3);
        }

        .school-card-code {
            font-size: 16px;
            font-weight: 800;
            color: white;
            margin-bottom: 6px;
        }

        .school-card-name {
            font-size: 11px;
            color: rgba(255,255,255,0.5);
            line-height: 1.4;
        }

        /* ======================================
           STATS SECTION
        ====================================== */
        .stats-section {
            padding: 80px 0;
            background: var(--bg-section);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 28px;
        }

        .stat-card {
            background: white;
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 32px 24px;
            display: flex;
            align-items: center;
            gap: 20px;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
        }

        .stat-card:hover {
            transform: translateY(-6px);
            box-shadow: var(--shadow-md);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            flex-shrink: 0;
        }

        .stat-icon.red {
            background: var(--primary-soft);
            color: var(--primary);
        }

        .stat-icon.blue {
            background: #eff6ff;
            color: #3b82f6;
        }

        .stat-icon.green {
            background: #f0fdf4;
            color: #22c55e;
        }

        .stat-icon.amber {
            background: #fffbeb;
            color: #f59e0b;
        }

        .stat-value {
            font-size: 36px;
            font-weight: 800;
            color: var(--text-dark);
            letter-spacing: -1px;
            line-height: 1;
            margin-bottom: 4px;
        }

        .stat-label {
            font-size: 14px;
            color: var(--text-muted);
            font-weight: 500;
        }

        /* ======================================
           TESTIMONIALS
        ====================================== */
        .testimonials-section {
            padding: 80px 0;
        }

        .testimonials-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 28px;
        }

        .testimonial-card {
            background: white;
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 30px;
            position: relative;
            transition: var(--transition);
            box-shadow: var(--shadow-sm);
        }

        .testimonial-card:hover {
            transform: translateY(-6px);
            box-shadow: var(--shadow-md);
        }

        .testimonial-quote-icon {
            position: absolute;
            top: 24px;
            right: 24px;
            font-size: 40px;
            color: var(--primary-soft);
            line-height: 1;
        }

        .testimonial-stars {
            display: flex;
            gap: 3px;
            margin-bottom: 16px;
        }

        .testimonial-stars i {
            color: #f59e0b;
            font-size: 15px;
        }

        .testimonial-text {
            font-size: 15px;
            color: var(--text-muted);
            line-height: 1.7;
            margin-bottom: 24px;
        }

        .testimonial-author {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .testimonial-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), #7c0d1e);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 18px;
            font-weight: 700;
            flex-shrink: 0;
        }

        .testimonial-name {
            font-size: 15px;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 2px;
        }

        .testimonial-role {
            font-size: 13px;
            color: var(--text-muted);
        }

        /* ======================================
           CTA BANNER
        ====================================== */
        .cta-section {
            background: linear-gradient(135deg, var(--primary) 0%, #7c0d1e 100%);
            padding: 80px 0;
            position: relative;
            overflow: hidden;
        }

        .cta-section::before {
            content: '';
            position: absolute;
            inset: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }

        .cta-inner {
            position: relative;
            z-index: 1;
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 40px;
            align-items: center;
        }

        .cta-text h2 {
            font-size: 36px;
            font-weight: 800;
            color: white;
            margin-bottom: 12px;
            letter-spacing: -1px;
        }

        .cta-text p {
            font-size: 16px;
            color: rgba(255,255,255,0.75);
            line-height: 1.6;
        }

        .cta-actions {
            display: flex;
            gap: 14px;
            flex-shrink: 0;
        }

        .btn-cta-white {
            padding: 16px 28px;
            background: white;
            color: var(--primary);
            border: none;
            border-radius: var(--radius-md);
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-cta-white:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 28px rgba(0,0,0,0.2);
        }

        .btn-cta-outline {
            padding: 16px 28px;
            background: transparent;
            color: white;
            border: 2px solid rgba(255,255,255,0.4);
            border-radius: var(--radius-md);
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-cta-outline:hover {
            border-color: white;
            background: rgba(255,255,255,0.08);
        }

        /* ======================================
           FOOTER
        ====================================== */
        .footer {
            background: var(--dark-bg);
            color: white;
            padding: 70px 0 0;
            position: relative;
        }

        .footer::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary) 0%, #ef4444 50%, var(--primary) 100%);
        }

        .footer-grid {
            display: grid;
            grid-template-columns: 1.5fr 1fr 1fr 1fr;
            gap: 48px;
            margin-bottom: 60px;
        }

        .footer-brand img {
            height: 56px;
            margin-bottom: 20px;
            filter: brightness(0) invert(1);
            opacity: 0.85;
        }

        .footer-brand p {
            font-size: 14px;
            color: rgba(255,255,255,0.55);
            line-height: 1.7;
            margin-bottom: 24px;
        }

        .footer-socials {
            display: flex;
            gap: 10px;
        }

        .footer-social-btn {
            width: 40px;
            height: 40px;
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: var(--radius-sm);
            background: rgba(255,255,255,0.04);
            color: rgba(255,255,255,0.6);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            transition: var(--transition);
            cursor: pointer;
        }

        .footer-social-btn:hover {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
            transform: translateY(-3px);
        }

        .footer-col-title {
            font-size: 15px;
            font-weight: 700;
            color: white;
            margin-bottom: 20px;
            position: relative;
            padding-bottom: 10px;
        }

        .footer-col-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 28px;
            height: 2px;
            background: var(--primary);
            border-radius: 99px;
        }

        .footer-links-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .footer-links-list a {
            font-size: 14px;
            color: rgba(255,255,255,0.55);
            display: flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
        }

        .footer-links-list a i {
            font-size: 12px;
            color: var(--primary);
        }

        .footer-links-list a:hover {
            color: white;
            padding-left: 4px;
        }

        .footer-newsletter-form {
            display: flex;
            margin-top: 16px;
            border-radius: var(--radius-sm);
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.1);
        }

        .footer-newsletter-form input {
            flex: 1;
            padding: 12px 16px;
            background: rgba(255,255,255,0.05);
            border: none;
            outline: none;
            color: white;
            font-size: 14px;
            font-family: 'Inter', sans-serif;
        }

        .footer-newsletter-form input::placeholder {
            color: rgba(255,255,255,0.35);
        }

        .footer-newsletter-form button {
            padding: 12px 18px;
            background: var(--primary);
            border: none;
            color: white;
            cursor: pointer;
            transition: var(--transition);
            font-size: 14px;
        }

        .footer-newsletter-form button:hover {
            background: var(--primary-hover);
        }

        .footer-bottom {
            border-top: 1px solid rgba(255,255,255,0.07);
            padding: 24px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
        }

        .footer-bottom-copy {
            font-size: 14px;
            color: rgba(255,255,255,0.4);
        }

        .footer-bottom-links {
            display: flex;
            gap: 20px;
        }

        .footer-bottom-links a {
            font-size: 13px;
            color: rgba(255,255,255,0.4);
            transition: var(--transition);
        }

        .footer-bottom-links a:hover {
            color: rgba(255,255,255,0.8);
        }

        /* ======================================
           BACK TO TOP
        ====================================== */
        .back-top {
            position: fixed;
            bottom: 28px;
            right: 28px;
            width: 48px;
            height: 48px;
            background: var(--primary);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            cursor: pointer;
            z-index: 888;
            box-shadow: 0 8px 20px rgba(194,24,52,0.35);
            transition: var(--transition);
            opacity: 0;
            transform: translateY(20px);
            pointer-events: none;
        }

        .back-top.show {
            opacity: 1;
            transform: translateY(0);
            pointer-events: all;
        }

        .back-top:hover {
            background: var(--primary-hover);
            transform: translateY(-4px);
        }

        /* ======================================
           MOBILE MENU
        ====================================== */
        .mobile-menu {
            display: none;
            flex-direction: column;
            gap: 4px;
            padding: 16px 24px 20px;
            border-top: 1px solid var(--border);
            background: white;
        }

        .mobile-menu.open {
            display: flex;
        }

        .mobile-menu a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            border-radius: var(--radius-sm);
            font-size: 15px;
            font-weight: 500;
            color: var(--text-dark);
            transition: var(--transition);
        }

        .mobile-menu a:hover {
            background: var(--primary-soft);
            color: var(--primary);
        }

        .mobile-menu .mobile-login {
            margin-top: 8px;
            background: var(--primary);
            color: white;
            border-radius: var(--radius-sm);
            padding: 14px 16px;
            font-weight: 700;
            text-align: center;
            justify-content: center;
        }

        .mobile-menu .mobile-login:hover {
            background: var(--primary-hover);
            color: white;
        }

        /* ======================================
           RESPONSIVE
        ====================================== */
        @media (max-width: 1100px) {
            .events-grid { grid-template-columns: repeat(2, 1fr); }
            .schools-grid { grid-template-columns: repeat(3, 1fr); }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 900px) {
            .hero-grid { grid-template-columns: 1fr; }
            .hero-featured-card { display: none; }
            .footer-grid { grid-template-columns: repeat(2, 1fr); }
            .cta-inner { grid-template-columns: 1fr; gap: 24px; }
            .testimonials-grid { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 768px) {
            .nav-links { display: none; }
            .hamburger { display: flex; }
            .btn-nav-login { display: none; }
            .events-grid { grid-template-columns: 1fr; }
            .schools-grid { grid-template-columns: repeat(2, 1fr); }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .hero { padding: 60px 0 56px; }
            .hero h1 { font-size: 36px; }
        }

        @media (max-width: 576px) {
            .schools-grid { grid-template-columns: repeat(2, 1fr); }
            .stats-grid { grid-template-columns: 1fr; }
            .footer-grid { grid-template-columns: 1fr; }
            .testimonials-grid { grid-template-columns: 1fr; }
            .cta-actions { flex-direction: column; }
            .footer-bottom { flex-direction: column; gap: 12px; text-align: center; }
            .section { padding: 60px 0; }
        }
    </style>
</head>
<body>

    <!-- Announcement Bar -->
    <div class="announcement-bar">
        <span>
            <span class="pulse-dot"></span>
            🎓 NMIMS Hyderabad Event Portal — Submit your event proposals and stay updated with campus events
        </span>
    </div>

    <!-- Navbar -->
    <nav class="navbar">
        <div class="container">
            <div class="nav-inner">
                <!-- Brand -->
                <a href="index.php" class="nav-brand">
                    <img src="SVKM's NMIMS.png" alt="NMIMS Logo">
                    <div class="nav-brand-text">
                        <strong>EventHub</strong>
                        <small>NMIMS Hyderabad</small>
                    </div>
                </a>

                <!-- Nav Links -->
                <div class="nav-links">
                    <a href="index.php" class="active"><i class="fas fa-home"></i> Home</a>
                    <a href="#events"><i class="fas fa-calendar-alt"></i> Events</a>
                    <a href="#categories"><i class="fas fa-th"></i> Categories</a>
                    <a href="#schools"><i class="fas fa-university"></i> Schools</a>
                    <a href="event-calendar.php"><i class="fas fa-calendar"></i> Calendar</a>
                </div>

                <!-- Actions -->
                <div class="nav-actions">
                    <button class="nav-search-btn" onclick="toggleSearch()">
                        <i class="fas fa-search"></i>
                    </button>
                    <a href="login.php">
                        <button class="btn-nav-login">
                            <i class="fas fa-sign-in-alt"></i>
                            Login / Register
                        </button>
                    </a>
                    <div class="hamburger" onclick="toggleMobileMenu()" id="hamburger">
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mobile Menu -->
        <div class="mobile-menu" id="mobileMenu">
            <a href="index.php"><i class="fas fa-home"></i> Home</a>
            <a href="#events"><i class="fas fa-calendar-alt"></i> Events</a>
            <a href="#categories"><i class="fas fa-th"></i> Categories</a>
            <a href="#schools"><i class="fas fa-university"></i> Schools</a>
            <a href="event-calendar.php"><i class="fas fa-calendar"></i> Calendar</a>
            <a href="login.php" class="mobile-login"><i class="fas fa-sign-in-alt"></i> Login / Register</a>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <div class="hero-grid">
                <!-- Left Content -->
                <div class="hero-content">
                    <div class="hero-eyebrow">
                        <i class="fas fa-bolt"></i>
                        NMIMS Hyderabad — Campus Events
                    </div>
                    <h1>
                        Discover <span class="highlight">Inspiring</span><br>
                        Campus Events
                    </h1>
                    <p class="hero-desc">
                        Find and participate in the best workshops, competitions, seminars, and cultural events happening across all schools at NMIMS Hyderabad.
                    </p>

                    <!-- Search -->
                    <div class="hero-search">
                        <input type="text" id="heroSearch" placeholder="Search events, clubs, schools..." onkeypress="searchEvent(event)">
                        <button class="hero-search-btn" onclick="doSearch()">
                            <i class="fas fa-search"></i>
                            Search
                        </button>
                    </div>

                    <!-- Stats -->
                    <div class="hero-stats">
                        <div class="hero-stat-item">
                            <strong><?= number_format($totalEvents) ?>+</strong>
                            <span>Events Hosted</span>
                        </div>
                        <div class="hero-stat-item">
                            <strong><?= number_format($totalClubs) ?>+</strong>
                            <span>Active Clubs</span>
                        </div>
                        <div class="hero-stat-item">
                            <strong><?= $totalSchools ?></strong>
                            <span>Schools</span>
                        </div>
                    </div>
                </div>

                <!-- Right: Featured Event Card -->
                <div class="hero-featured-card">
                    <?php if ($featuredEvent): ?>
                        <div class="hero-featured-label">
                            <i class="fas fa-star"></i>
                            Featured Event
                        </div>

                        <div class="hero-event-date-badge">
                            <div class="date-box">
                                <span class="date-box-day"><?= formatDay($featuredEvent['event_date']) ?></span>
                                <span class="date-box-month"><?= formatMonth($featuredEvent['event_date']) ?></span>
                            </div>
                            <div class="date-info">
                                <strong>
                                    <?= formatTime($featuredEvent['start_time']) ?>
                                    <?= $featuredEvent['end_time'] ? '— ' . formatTime($featuredEvent['end_time']) : '' ?>
                                </strong>
                                <span><?= htmlspecialchars(date('l, d F Y', strtotime($featuredEvent['event_date']))) ?></span>
                            </div>
                        </div>

                        <h3 class="hero-featured-title"><?= htmlspecialchars($featuredEvent['event_name']) ?></h3>

                        <p class="hero-featured-desc">
                            <?= strlen($featuredEvent['event_details']) > 120
                                ? htmlspecialchars(substr($featuredEvent['event_details'], 0, 120)) . '...'
                                : htmlspecialchars($featuredEvent['event_details'] ?? 'Exciting event coming up. Stay tuned for more details!') ?>
                        </p>

                        <div class="hero-event-meta">
                            <div class="hero-event-meta-item">
                                <i class="fas fa-map-marker-alt"></i>
                                <?= htmlspecialchars($featuredEvent['venue_name'] ?? 'Venue TBA') ?>
                            </div>
                            <div class="hero-event-meta-item">
                                <i class="fas fa-users"></i>
                                <?= htmlspecialchars($featuredEvent['club_name'] ?? 'NMIMS Club') ?> · <?= htmlspecialchars($featuredEvent['school_name'] ?? '') ?>
                            </div>
                            <div class="hero-event-meta-item">
                                <i class="fas fa-circle"></i>
                                <span style="color: <?= getStatusColor($featuredEvent['status']) ?>; font-weight: 600;">
                                    <?= getStatusLabel($featuredEvent['status']) ?>
                                </span>
                            </div>
                        </div>

                        <div class="hero-cta-row">
                            <a href="event-details.php?id=<?= $featuredEvent['id'] ?>" class="btn-hero-primary">
                                <i class="fas fa-ticket-alt"></i>
                                View Event
                            </a>
                            <a href="event-calendar.php" class="btn-hero-ghost">
                                <i class="fas fa-calendar"></i>
                                Calendar
                            </a>
                        </div>

                    <?php else: ?>
                        <div class="hero-no-event">
                            <i class="fas fa-calendar-day"></i>
                            <p>No upcoming featured events.<br>Check back soon!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Live Ticker -->
    <?php if (!empty($upcomingEvents)): ?>
    <div class="live-ticker">
        <div class="container">
            <div class="ticker-inner">
                <div class="ticker-label">
                    <span class="pulse-dot"></span>
                    LIVE
                </div>
                <div class="ticker-track">
                    <div class="ticker-content">
                        <?php foreach ($upcomingEvents as $te): ?>
                            <span class="ticker-item">
                                <i class="fas fa-calendar-check"></i>
                                <?= htmlspecialchars($te['event_name']) ?>
                                — <?= htmlspecialchars($te['club_name'] ?? '') ?>
                                · <?= formatEventDate($te['event_date']) ?>
                            </span>
                        <?php endforeach; ?>
                        <?php foreach ($upcomingEvents as $te): ?>
                            <span class="ticker-item">
                                <i class="fas fa-calendar-check"></i>
                                <?= htmlspecialchars($te['event_name']) ?>
                                — <?= htmlspecialchars($te['club_name'] ?? '') ?>
                                · <?= formatEventDate($te['event_date']) ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Categories Section -->
    <section class="section" id="categories">
        <div class="container">
            <div class="section-head">
                <div>
                    <div class="section-label">
                        <i class="fas fa-th"></i> Browse
                    </div>
                    <h2 class="section-title">Explore by Category</h2>
                    <p class="section-desc">Filter events by type to find exactly what interests you</p>
                </div>
            </div>

            <div class="category-chips">
                <div class="category-chip active" onclick="filterCategory('all', this)">
                    <i class="fas fa-globe"></i> All Events
                </div>
                <div class="category-chip" onclick="filterCategory('technical', this)">
                    <i class="fas fa-laptop-code"></i> Technical
                </div>
                <div class="category-chip" onclick="filterCategory('cultural', this)">
                    <i class="fas fa-music"></i> Cultural
                </div>
                <div class="category-chip" onclick="filterCategory('academic', this)">
                    <i class="fas fa-graduation-cap"></i> Academic
                </div>
                <div class="category-chip" onclick="filterCategory('sports', this)">
                    <i class="fas fa-futbol"></i> Sports
                </div>
                <div class="category-chip" onclick="filterCategory('social', this)">
                    <i class="fas fa-hands-helping"></i> Social
                </div>
                <div class="category-chip" onclick="filterCategory('workshop', this)">
                    <i class="fas fa-tools"></i> Workshop
                </div>
                <div class="category-chip" onclick="filterCategory('seminar', this)">
                    <i class="fas fa-chalkboard-teacher"></i> Seminar
                </div>
            </div>
        </div>
    </section>

    <!-- Upcoming Events Section -->
    <section class="section" id="events" style="padding-top: 0;">
        <div class="container">
            <div class="section-head">
                <div>
                    <div class="section-label">
                        <i class="fas fa-fire"></i> Live & Upcoming
                    </div>
                    <h2 class="section-title">Upcoming Events</h2>
                    <p class="section-desc">Don't miss out on the latest events happening across all schools</p>
                </div>
                <a href="all-events.php" class="view-all-link">
                    View All Events <i class="fas fa-arrow-right"></i>
                </a>
            </div>

            <div class="events-grid" id="eventsGrid">
                <?php if (!empty($upcomingEvents)): ?>
                    <?php foreach ($upcomingEvents as $event): ?>
                    <div class="event-card" data-category="technical">
                        <!-- Event Image / Placeholder -->
                        <div class="event-card-img">
                            <?php if (!empty($event['club_logo'])): ?>
                                <div class="event-card-img-placeholder" style="background: linear-gradient(135deg, #2d0f18, #1a0a0d);">
                                    <img src="<?= htmlspecialchars($event['club_logo']) ?>"
                                         style="width:80px;height:80px;object-fit:contain;opacity:0.6;"
                                         alt="Club Logo">
                                </div>
                            <?php else: ?>
                                <div class="event-card-img-placeholder">
                                    <i class="fas fa-calendar-star"></i>
                                </div>
                            <?php endif; ?>

                            <!-- Badges overlay -->
                            <div class="event-card-badges">
                                <span class="event-badge-status event-badge-<?= ($event['status'] ?? $event['proposal_status'] ?? 'upcoming') ?>">
                                    <i class="fas fa-circle" style="font-size:7px;"></i>
                                    <?= getStatusLabel($event['status'] ?? $event['proposal_status'] ?? 'upcoming') ?>
                                </span>
                                <span class="event-date-chip">
                                    <i class="far fa-calendar"></i>
                                    <?= formatEventDate($event['event_date']) ?>
                                </span>
                            </div>
                        </div>

                        <!-- Card Body -->
                        <div class="event-card-body">
                            <!-- Club Tag -->
                            <div class="event-club-tag">
                                <i class="fas fa-users"></i>
                                <?= htmlspecialchars($event['club_name'] ?? 'NMIMS Club') ?>
                            </div>

                            <!-- Title -->
                            <h3 class="event-card-title">
                                <?= htmlspecialchars($event['event_name']) ?>
                            </h3>

                            <!-- Description -->
                            <p class="event-card-desc">
                                <?= !empty($event['event_details'])
                                    ? htmlspecialchars(substr($event['event_details'], 0, 110)) . '...'
                                    : 'Join us for this exciting event at ' . htmlspecialchars($event['school_name'] ?? 'NMIMS') . '.' ?>
                            </p>

                            <!-- Meta Info -->
                            <div class="event-card-meta">
                                <!-- Date & Time -->
                                <div class="event-meta-row">
                                    <i class="fas fa-clock"></i>
                                    <span>
                                        <?= date('D, d M Y', strtotime($event['event_date'])) ?>
                                        <?php if ($event['start_time']): ?>
                                            · <?= formatTime($event['start_time']) ?>
                                            <?= $event['end_time'] ? '– ' . formatTime($event['end_time']) : '' ?>
                                        <?php endif; ?>
                                    </span>
                                </div>

                                <!-- Venue -->
                                <div class="event-meta-row">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <span><?= htmlspecialchars($event['venue_name'] ?? 'Venue TBA') ?></span>
                                </div>

                                <!-- School -->
                                <div class="event-meta-row">
                                    <i class="fas fa-university"></i>
                                    <span><?= htmlspecialchars($event['school_name'] ?? 'NMIMS Hyderabad') ?></span>
                                </div>
                            </div>

                            <!-- Footer Actions -->
                            <div class="event-card-footer">
                                <a href="event-details.php?id=<?= $event['id'] ?>" class="btn-event-primary">
                                    <i class="fas fa-eye"></i>
                                    View Details
                                </a>
                                <button class="btn-event-ghost" onclick="shareEvent(<?= $event['id'] ?>, '<?= htmlspecialchars(addslashes($event['event_name'])) ?>')">
                                    <i class="fas fa-share-alt"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>

                <?php else: ?>
                    <div class="no-events">
                        <i class="fas fa-calendar-times"></i>
                        <h3>No Upcoming Events</h3>
                        <p>No events have been approved yet. Check back soon or explore the calendar.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Load More -->
            <?php if (count($upcomingEvents) >= 6): ?>
            <div style="text-align: center; margin-top: 48px;">
                <a href="all-events.php" style="
                    display: inline-flex;
                    align-items: center;
                    gap: 10px;
                    padding: 16px 36px;
                    border: 2px solid var(--primary);
                    color: var(--primary);
                    border-radius: var(--radius-md);
                    font-size: 15px;
                    font-weight: 700;
                    transition: var(--transition);
                " onmouseover="this.style.background='var(--primary)';this.style.color='white';"
                   onmouseout="this.style.background='transparent';this.style.color='var(--primary)';">
                    <i class="fas fa-calendar-alt"></i>
                    View All Events
                </a>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Schools Section -->
    <section class="schools-section" id="schools">
        <div class="container">
            <div style="margin-bottom: 48px;">
                <div class="section-label" style="background: rgba(255,255,255,0.08); color: rgba(255,255,255,0.8);">
                    <i class="fas fa-university"></i> Our Schools
                </div>
                <h2 class="section-title" style="color: white; margin-bottom: 10px;">
                    5 Schools. 1 Campus.
                </h2>
                <p style="color: rgba(255,255,255,0.5); font-size: 16px;">
                    Explore events from each school at NMIMS Hyderabad
                </p>
            </div>

            <div class="schools-grid">
                <div class="school-card" onclick="window.location.href='all-events.php?school=SBM'">
                    <div class="school-card-icon">
                        <i class="fas fa-briefcase"></i>
                    </div>
                    <div class="school-card-code">SBM</div>
                    <div class="school-card-name">School of Business Management</div>
                </div>

                <div class="school-card" onclick="window.location.href='all-events.php?school=STME'">
                    <div class="school-card-icon">
                        <i class="fas fa-microchip"></i>
                    </div>
                    <div class="school-card-code">STME</div>
                    <div class="school-card-name">School of Technology Management & Engineering</div>
                </div>

                <div class="school-card" onclick="window.location.href='all-events.php?school=SPTM'">
                    <div class="school-card-icon">
                        <i class="fas fa-flask"></i>
                    </div>
                    <div class="school-card-code">SPTM</div>
                    <div class="school-card-name">School of Pharmacy & Technology Management</div>
                </div>

                <div class="school-card" onclick="window.location.href='all-events.php?school=SOL'">
                    <div class="school-card-icon">
                        <i class="fas fa-balance-scale"></i>
                    </div>
                    <div class="school-card-code">SOL</div>
                    <div class="school-card-name">School of Law</div>
                </div>

                <div class="school-card" onclick="window.location.href='all-events.php?school=SOC'">
                    <div class="school-card-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="school-card-code">SOC</div>
                    <div class="school-card-name">School of Commerce</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="stats-section">
        <div class="container">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon red">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div>
                        <div class="stat-value" id="statEvents">
                            <?= number_format($totalEvents) ?>+
                        </div>
                        <div class="stat-label">Events Conducted</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon blue">
                        <i class="fas fa-users"></i>
                    </div>
                    <div>
                        <div class="stat-value" id="statClubs">
                            <?= number_format($totalClubs) ?>+
                        </div>
                        <div class="stat-label">Active Student Clubs</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon green">
                        <i class="fas fa-university"></i>
                    </div>
                    <div>
                        <div class="stat-value">5</div>
                        <div class="stat-label">Schools & Departments</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon amber">
                        <i class="fas fa-award"></i>
                    </div>
                    <div>
                        <div class="stat-value">100%</div>
                        <div class="stat-label">Digital Workflow</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials -->
    <section class="testimonials-section">
        <div class="container">
            <div class="section-head">
                <div>
                    <div class="section-label">
                        <i class="fas fa-quote-left"></i> Testimonials
                    </div>
                    <h2 class="section-title">What Students Say</h2>
                    <p class="section-desc">Hear from students who have participated in NMIMS events</p>
                </div>
            </div>

            <div class="testimonials-grid">
                <div class="testimonial-card">
                    <div class="testimonial-quote-icon">
                        <i class="fas fa-quote-right"></i>
                    </div>
                    <div class="testimonial-stars">
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                    </div>
                    <p class="testimonial-text">
                        "The Hackathon organized by ELGE club was phenomenal. The entire white paper approval system made the event incredibly well-organized and professional."
                    </p>
                    <div class="testimonial-author">
                        <div class="testimonial-avatar">A</div>
                        <div>
                            <div class="testimonial-name">Ananya Sharma</div>
                            <div class="testimonial-role">2nd Year · STME</div>
                        </div>
                    </div>
                </div>

                <div class="testimonial-card">
                    <div class="testimonial-quote-icon">
                        <i class="fas fa-quote-right"></i>
                    </div>
                    <div class="testimonial-stars">
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                    </div>
                    <p class="testimonial-text">
                        "As a club head, submitting event proposals digitally has saved so much time. The approval tracking makes everything transparent and efficient."
                    </p>
                    <div class="testimonial-author">
                        <div class="testimonial-avatar">R</div>
                        <div>
                            <div class="testimonial-name">Rohit Patel</div>
                            <div class="testimonial-role">Club Head · SOC</div>
                        </div>
                    </div>
                </div>

                <div class="testimonial-card">
                    <div class="testimonial-quote-icon">
                        <i class="fas fa-quote-right"></i>
                    </div>
                    <div class="testimonial-stars">
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star-half-alt"></i>
                    </div>
                    <p class="testimonial-text">
                        "The event calendar and notification system keeps me updated about all upcoming events across all five schools. Love the platform!"
                    </p>
                    <div class="testimonial-author">
                        <div class="testimonial-avatar">P</div>
                        <div>
                            <div class="testimonial-name">Priya Desai</div>
                            <div class="testimonial-role">3rd Year · SBM</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Banner -->
    <section class="cta-section">
        <div class="container">
            <div class="cta-inner">
                <div class="cta-text">
                    <h2>Ready to Submit Your Event?</h2>
                    <p>
                        Log in to your club portal and start your digital white paper submission today.
                        Get instant approvals from Faculty Mentor, President, GS/Treasurer, School Head and more.
                    </p>
                </div>
                <div class="cta-actions">
                    <a href="login.php" class="btn-cta-white">
                        <i class="fas fa-sign-in-alt"></i>
                        Login to Portal
                    </a>
                    <a href="event-calendar.php" class="btn-cta-outline">
                        <i class="fas fa-calendar"></i>
                        View Calendar
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-grid">
                <!-- Brand -->
                <div class="footer-brand">
                    <img src="SVKM's NMIMS.png" alt="NMIMS Logo">
                    <p>
                        NMIMS EventHub is the official digital event management and white paper workflow platform
                        for NMIMS Hyderabad campus clubs and schools.
                    </p>
                    <div class="footer-socials">
                        <a class="footer-social-btn" href="#"><i class="fab fa-instagram"></i></a>
                        <a class="footer-social-btn" href="#"><i class="fab fa-linkedin-in"></i></a>
                        <a class="footer-social-btn" href="#"><i class="fab fa-youtube"></i></a>
                        <a class="footer-social-btn" href="#"><i class="fab fa-twitter"></i></a>
                        <a class="footer-social-btn" href="#"><i class="fab fa-facebook-f"></i></a>
                    </div>
                </div>

                <!-- Quick Links -->
                <div>
                    <h4 class="footer-col-title">Quick Links</h4>
                    <ul class="footer-links-list">
                        <li><a href="index.php"><i class="fas fa-chevron-right"></i> Home</a></li>
                        <li><a href="all-events.php"><i class="fas fa-chevron-right"></i> All Events</a></li>
                        <li><a href="event-calendar.php"><i class="fas fa-chevron-right"></i> Event Calendar</a></li>
                        <li><a href="login.php"><i class="fas fa-chevron-right"></i> Club Login</a></li>
                        <li><a href="login.php"><i class="fas fa-chevron-right"></i> Admin Login</a></li>
                    </ul>
                </div>

                <!-- Schools -->
                <div>
                    <h4 class="footer-col-title">Schools</h4>
                    <ul class="footer-links-list">
                        <li><a href="all-events.php?school=SBM"><i class="fas fa-chevron-right"></i> SBM</a></li>
                        <li><a href="all-events.php?school=STME"><i class="fas fa-chevron-right"></i> STME</a></li>
                        <li><a href="all-events.php?school=SPTM"><i class="fas fa-chevron-right"></i> SPTM</a></li>
                        <li><a href="all-events.php?school=SOL"><i class="fas fa-chevron-right"></i> SOL</a></li>
                        <li><a href="all-events.php?school=SOC"><i class="fas fa-chevron-right"></i> SOC</a></li>
                    </ul>
                </div>

                <!-- Newsletter -->
                <div>
                    <h4 class="footer-col-title">Stay Updated</h4>
                    <p style="font-size: 14px; color: rgba(255,255,255,0.5); margin-bottom: 10px; line-height: 1.6;">
                        Subscribe to get notified about upcoming events and approvals.
                    </p>
                    <form class="footer-newsletter-form" onsubmit="subscribeNewsletter(event)">
                        <input type="email" placeholder="Enter your email" required>
                        <button type="submit">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </form>

                    <div style="margin-top: 24px;">
                        <h4 class="footer-col-title">Contact</h4>
                        <ul class="footer-links-list">
                            <li>
                                <a href="mailto:events@nmims.edu">
                                    <i class="fas fa-envelope"></i>
                                    events@nmims.edu
                                </a>
                            </li>
                            <li>
                                <a href="#">
                                    <i class="fas fa-map-marker-alt"></i>
                                    NMIMS Hyderabad Campus
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Footer Bottom -->
            <div class="footer-bottom">
                <div class="footer-bottom-copy">
                    &copy; <?= date('Y') ?> Kuchuru Sai Krishna Reddy – STME. All rights reserved.
                </div>
                <div class="footer-bottom-links">
                    <a href="#">Privacy Policy</a>
                    <a href="#">Terms of Use</a>
                    <a href="#">Cookie Policy</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Back to Top -->
    <div class="back-top" id="backTop" onclick="window.scrollTo({top:0,behavior:'smooth'})">
        <i class="fas fa-arrow-up"></i>
    </div>

    <!-- ======================================
         JAVASCRIPT
    ====================================== -->
    <script>
        // ── Back to top ──
        const backTop = document.getElementById('backTop');
        window.addEventListener('scroll', () => {
            backTop.classList.toggle('show', window.scrollY > 300);
        });

        // ── Mobile menu toggle ──
        function toggleMobileMenu() {
            const menu = document.getElementById('mobileMenu');
            const ham  = document.getElementById('hamburger');
            menu.classList.toggle('open');
            ham.classList.toggle('open');
        }

        // ── Search ──
        function toggleSearch() {
            const el = document.getElementById('heroSearch');
            if (el) {
                document.querySelector('.hero').scrollIntoView({ behavior: 'smooth' });
                setTimeout(() => el.focus(), 600);
            }
        }

        function searchEvent(e) {
            if (e.key === 'Enter') doSearch();
        }

        function doSearch() {
            const q = document.getElementById('heroSearch').value.trim();
            if (q) window.location.href = 'all-events.php?search=' + encodeURIComponent(q);
        }

        // ── Category filter ──
        function filterCategory(cat, el) {
            // Update active chip
            document.querySelectorAll('.category-chip').forEach(c => c.classList.remove('active'));
            el.classList.add('active');

            if (cat === 'all') {
                window.location.href = 'all-events.php';
            } else {
                window.location.href = 'all-events.php?category=' + encodeURIComponent(cat);
            }
        }

        // ── Share event ──
        function shareEvent(id, name) {
            const url = window.location.origin + '/event-details.php?id=' + id;
            if (navigator.share) {
                navigator.share({ title: name, url: url });
            } else if (navigator.clipboard) {
                navigator.clipboard.writeText(url).then(() => showToast('Link copied!'));
            } else {
                showToast('Share: ' + url);
            }
        }

        // ── Newsletter ──
        function subscribeNewsletter(e) {
            e.preventDefault();
            showToast('Subscribed! You will receive event updates.');
            e.target.reset();
        }

        // ── Toast notification ──
        function showToast(msg, type = 'success') {
            const existing = document.querySelector('.toast-notif');
            if (existing) existing.remove();

            const toast = document.createElement('div');
            toast.className = 'toast-notif';
            toast.innerHTML = `<i class="fas fa-check-circle"></i> ${msg}`;
            toast.style.cssText = `
                position: fixed;
                bottom: 90px;
                right: 28px;
                background: ${type === 'success' ? '#22c55e' : '#ef4444'};
                color: white;
                padding: 14px 22px;
                border-radius: 12px;
                font-size: 14px;
                font-weight: 600;
                font-family: 'Inter', sans-serif;
                z-index: 9999;
                display: flex;
                align-items: center;
                gap: 8px;
                box-shadow: 0 12px 28px rgba(0,0,0,0.2);
                animation: slideInToast 0.3s ease;
            `;

            const style = document.createElement('style');
            style.textContent = `
                @keyframes slideInToast {
                    from { opacity: 0; transform: translateY(20px); }
                    to { opacity: 1; transform: translateY(0); }
                }
            `;
            document.head.appendChild(style);
            document.body.appendChild(toast);

            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transition = 'opacity 0.3s';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        // ── Animate numbers on scroll ──
        function animateNumber(el, target, duration = 1500) {
            let start = 0;
            const step = target / (duration / 16);
            const timer = setInterval(() => {
                start = Math.min(start + step, target);
                el.textContent = Math.floor(start) + (target > 10 ? '+' : '');
                if (start >= target) clearInterval(timer);
            }, 16);
        }

        // Intersection Observer for stats
        const statsObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const el = entry.target;
                    const val = parseInt(el.dataset.target || el.textContent.replace(/\D/g,''));
                    if (!isNaN(val) && val > 0) animateNumber(el, val);
                    statsObserver.unobserve(el);
                }
            });
        }, { threshold: 0.5 });

        document.querySelectorAll('.stat-value').forEach(el => {
            el.dataset.target = parseInt(el.textContent.replace(/\D/g,''));
            statsObserver.observe(el);
        });

        // ── Smooth anchor scrolling ──
        document.querySelectorAll('a[href^="#"]').forEach(a => {
            a.addEventListener('click', e => {
                const target = document.querySelector(a.getAttribute('href'));
                if (target) {
                    e.preventDefault();
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });

        // ── Event card hover glow ──
        document.querySelectorAll('.event-card').forEach(card => {
            card.addEventListener('mousemove', e => {
                const rect = card.getBoundingClientRect();
                const x = ((e.clientX - rect.left) / rect.width) * 100;
                const y = ((e.clientY - rect.top) / rect.height) * 100;
                card.style.setProperty('--glow-x', x + '%');
                card.style.setProperty('--glow-y', y + '%');
            });
        });
    </script>
</body>
</html>