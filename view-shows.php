<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/config.php';
$conn = getDBConnection();

$success_message = '';
$error_message = '';

// Handle delete action
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $type = $_GET['delete']; // 'movie' or 'schedule'
    
    if ($type === 'movie') {
        // First, delete all schedules for this movie
        $deleteSchedules = $conn->prepare("DELETE FROM MOVIE_SCHEDULE WHERE movie_show_id = ?");
        $deleteSchedules->bind_param("i", $id);
        $deleteSchedules->execute();
        $deleteSchedules->close();
        
        // Then delete the movie
        $stmt = $conn->prepare("DELETE FROM MOVIE WHERE movie_show_id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $success_message = "Movie and all its schedules deleted successfully!";
        } else {
            $error_message = "Error deleting movie: " . $conn->error;
        }
        $stmt->close();
    } elseif ($type === 'schedule') {
        // Delete schedule
        $stmt = $conn->prepare("DELETE FROM MOVIE_SCHEDULE WHERE schedule_id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $success_message = "Schedule deleted successfully!";
        } else {
            $error_message = "Error deleting schedule: " . $conn->error;
        }
        $stmt->close();
    }
}

// Fetch all movies with their schedules
$movies_query = $conn->query("
    SELECT 
        m.movie_show_id,
        m.title,
        m.genre,
        m.duration,
        m.rating,
        m.movie_descrp,
        m.image_poster,
        ms.schedule_id,
        ms.show_date,
        ms.show_hour
    FROM MOVIE m
    LEFT JOIN MOVIE_SCHEDULE ms ON m.movie_show_id = ms.movie_show_id
    ORDER BY m.title, ms.show_date, ms.show_hour
");

$movies = [];
if ($movies_query) {
    while ($row = $movies_query->fetch_assoc()) {
        $movie_id = $row['movie_show_id'];
        if (!isset($movies[$movie_id])) {
            $movies[$movie_id] = [
                'movie_show_id' => $row['movie_show_id'],
                'title' => $row['title'],
                'genre' => $row['genre'],
                'duration' => $row['duration'],
                'rating' => $row['rating'],
                'movie_descrp' => $row['movie_descrp'],
                'image_poster' => $row['image_poster'],
                'schedules' => []
            ];
        }
        if ($row['schedule_id']) {
            $movies[$movie_id]['schedules'][] = [
                'schedule_id' => $row['schedule_id'],
                'show_date' => $row['show_date'],
                'show_hour' => $row['show_hour']
            ];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>View Shows - Admin Panel</title>
    <link rel="icon" type="image/png" href="images/brand x.png" />
    <link rel="stylesheet" href="css/admin-panel.css" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet" />
    <style>
        .shows-container {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .movie-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 4px solid #00BFFF;
        }
        
        .movie-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }
        
        .movie-info h3 {
            color: #333;
            margin-bottom: 10px;
        }
        
        .movie-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px;
            margin-bottom: 15px;
            color: #666;
            font-size: 14px;
        }
        
        .schedules-section {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
        }
        
        .schedules-section h4 {
            color: #333;
            margin-bottom: 10px;
        }
        
        .schedule-item {
            background: white;
            padding: 10px 15px;
            border-radius: 6px;
            margin-bottom: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid #e0e0e0;
        }
        
        .schedule-info {
            color: #333;
        }
        
        .btn-delete {
            background: #dc3545;
            color: white;
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            transition: background 0.3s;
        }
        
        .btn-delete:hover {
            background: #c82333;
        }
        
        .btn-add-schedule {
            background: #28a745;
            color: white;
            padding: 8px 15px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            margin-top: 10px;
        }
        
        .btn-add-schedule:hover {
            background: #218838;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .no-schedules {
            color: #999;
            font-style: italic;
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
            <a href="view-shows.php" class="active">List Shows</a>
            <a href="view-bookings.php">List Bookings</a>
        </nav>
    </aside>
    <main class="main-content">
        <header>
            <h1>View <span class="highlight">Shows</span></h1>
        </header>

        <div class="shows-container">
            <?php if ($success_message): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error_message) ?></div>
            <?php endif; ?>

            <?php if (empty($movies)): ?>
                <p style="color: #666; text-align: center; padding: 40px;">No shows found. <a href="add-show.php">Add a show</a> to get started.</p>
            <?php else: ?>
                <?php foreach ($movies as $movie): ?>
                    <div class="movie-card">
                        <div class="movie-header">
                            <div class="movie-info">
                                <h3><?= htmlspecialchars($movie['title']) ?></h3>
                                <div class="movie-details">
                                    <span><strong>Genre:</strong> <?= htmlspecialchars($movie['genre']) ?></span>
                                    <span><strong>Duration:</strong> <?= htmlspecialchars($movie['duration']) ?> min</span>
                                    <span><strong>Rating:</strong> <?= htmlspecialchars($movie['rating']) ?></span>
                                </div>
                                <?php if ($movie['movie_descrp']): ?>
                                    <p style="color: #666; margin-top: 10px;"><?= htmlspecialchars($movie['movie_descrp']) ?></p>
                                <?php endif; ?>
                            </div>
                            <a href="?delete=movie&id=<?= $movie['movie_show_id'] ?>" 
                               class="btn-delete" 
                               onclick="return confirm('Are you sure you want to delete this movie and all its schedules?')">
                                Delete Movie
                            </a>
                        </div>

                        <div class="schedules-section">
                            <h4>Show Schedules:</h4>
                            <?php if (empty($movie['schedules'])): ?>
                                <p class="no-schedules">No schedules added yet.</p>
                            <?php else: ?>
                                <?php foreach ($movie['schedules'] as $schedule): ?>
                                    <div class="schedule-item">
                                        <div class="schedule-info">
                                            <strong><?= date('M d, Y', strtotime($schedule['show_date'])) ?></strong> 
                                            at <?= date('g:i A', strtotime($schedule['show_hour'])) ?>
                                        </div>
                                        <a href="?delete=schedule&id=<?= $schedule['schedule_id'] ?>" 
                                           class="btn-delete" 
                                           onclick="return confirm('Are you sure you want to delete this schedule?')">
                                            Delete
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <a href="add-schedule.php?movie_id=<?= $movie['movie_show_id'] ?>" class="btn-add-schedule">+ Add Schedule</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>

