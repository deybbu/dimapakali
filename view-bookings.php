<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/config.php';
$conn = getDBConnection();

// Fetch all bookings with related information
$bookings_query = $conn->query("
    SELECT 
        r.reservation_id,
        r.reserve_date,
        r.ticket_amount,
        r.sum_price,
        u.firstName,
        u.lastName,
        u.email,
        m.title AS movie_title,
        ms.show_date,
        ms.show_hour,
        p.payment_status,
        p.amount_paid,
        p.payment_type,
        p.payment_date
    FROM RESERVE r
    LEFT JOIN USER_ACCOUNT u ON r.acc_id = u.acc_id
    LEFT JOIN MOVIE_SCHEDULE ms ON r.schedule_id = ms.schedule_id
    LEFT JOIN MOVIE m ON ms.movie_show_id = m.movie_show_id
    LEFT JOIN PAYMENT p ON r.reservation_id = p.reserve_id
    ORDER BY r.reserve_date DESC
");

$bookings = [];
if ($bookings_query) {
    while ($row = $bookings_query->fetch_assoc()) {
        $bookings[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>View Bookings - Admin Panel</title>
    <link rel="icon" type="image/png" href="images/brand x.png" />
    <link rel="stylesheet" href="css/admin-panel.css" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet" />
    <style>
        .bookings-container {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        th {
            background: #00BFFF;
            color: white;
            padding: 12px;
            text-align: left;
            font-weight: 600;
        }
        
        td {
            padding: 12px;
            border-bottom: 1px solid #e0e0e0;
            color: #333;
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        .status-badge {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }
        
        .status-paid {
            background: #d4edda;
            color: #155724;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-not-yet {
            background: #f8d7da;
            color: #721c24;
        }
        
        .no-bookings {
            text-align: center;
            padding: 40px;
            color: #666;
        }
    </style>
</head>
<body>
    <aside class="sidebar">
        <div class="profile-section">
            <img src="images/brand x.png" alt="Profile Picture" class="profile-pic" />
            <h2>Admin</h2>
        </div>
        <nav class="sidebar-nav">
            <a href="admin-panel.php">Dashboard</a>
            <a href="add-show.php">Add Shows</a>
            <a href="view-shows.php">List Shows</a>
            <a href="view-bookings.php" class="active">List Bookings</a>
        </nav>
    </aside>
    <main class="main-content">
        <header>
            <h1>View <span class="highlight">Bookings</span></h1>
        </header>

        <div class="bookings-container">
            <?php if (empty($bookings)): ?>
                <div class="no-bookings">
                    <p>No bookings found.</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Reservation ID</th>
                            <th>Customer</th>
                            <th>Email</th>
                            <th>Movie</th>
                            <th>Show Date & Time</th>
                            <th>Tickets</th>
                            <th>Total Price</th>
                            <th>Payment Status</th>
                            <th>Payment Type</th>
                            <th>Reservation Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings as $booking): ?>
                            <tr>
                                <td>#<?= $booking['reservation_id'] ?></td>
                                <td><?= htmlspecialchars(($booking['firstName'] ?? '') . ' ' . ($booking['lastName'] ?? '')) ?></td>
                                <td><?= htmlspecialchars($booking['email'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($booking['movie_title'] ?? 'N/A') ?></td>
                                <td>
                                    <?php if ($booking['show_date']): ?>
                                        <?= date('M d, Y', strtotime($booking['show_date'])) ?><br>
                                        <?= date('g:i A', strtotime($booking['show_hour'])) ?>
                                    <?php else: ?>
                                        N/A
                                    <?php endif; ?>
                                </td>
                                <td><?= $booking['ticket_amount'] ?? 0 ?></td>
                                <td>₱<?= number_format($booking['sum_price'] ?? 0, 2) ?></td>
                                <td>
                                    <?php 
                                    $status = $booking['payment_status'] ?? 'not-yet';
                                    $statusClass = 'status-' . str_replace('-', '-', $status);
                                    ?>
                                    <span class="status-badge <?= $statusClass ?>">
                                        <?= ucfirst(str_replace('-', ' ', $status)) ?>
                                    </span>
                                </td>
                                <td><?= ucfirst($booking['payment_type'] ?? 'N/A') ?></td>
                                <td><?= $booking['reserve_date'] ? date('M d, Y g:i A', strtotime($booking['reserve_date'])) : 'N/A' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>

