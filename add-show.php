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

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = trim($_POST['title'] ?? '');
    $genre = trim($_POST['genre'] ?? '');
    $duration = intval($_POST['duration'] ?? 0);
    $rating = trim($_POST['rating'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $image_poster = trim($_POST['image_poster'] ?? '');
    $show_date = $_POST['show_date'] ?? '';
    $show_hour = $_POST['show_hour'] ?? '';
    // Make checkboxes mutually exclusive - if coming_soon is checked, now_showing must be false
    $coming_soon = isset($_POST['coming_soon']) ? 1 : 0;
    $now_showing = ($coming_soon == 1) ? 0 : (isset($_POST['now_showing']) ? 1 : 0);
    
    if (empty($title) || empty($genre) || empty($duration) || empty($rating)) {
        $error_message = "Please fill in all required fields (Title, Genre, Duration, Rating).";
    } else {
        // Truncate genre to 20 characters (current database limit) until column is updated
        // After running: ALTER TABLE MOVIE MODIFY COLUMN genre VARCHAR(100);
        // Change this to: substr($genre, 0, 100)
        $genre = substr($genre, 0, 20);
        
        // Check if now_showing and coming_soon columns exist
        $columns_check = $conn->query("SHOW COLUMNS FROM MOVIE LIKE 'now_showing'");
        $has_now_showing = $columns_check && $columns_check->num_rows > 0;
        
        // Insert movie - use appropriate query based on column existence
        if ($has_now_showing) {
            $stmt = $conn->prepare("INSERT INTO MOVIE (title, genre, duration, rating, movie_descrp, image_poster, now_showing, coming_soon) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("ssisssii", $title, $genre, $duration, $rating, $description, $image_poster, $now_showing, $coming_soon);
                $execute_result = $stmt->execute();
            } else {
                $execute_result = false;
                $error_message = "Error preparing statement: " . $conn->error;
            }
        } else {
            // Insert without now_showing/coming_soon columns
            $stmt = $conn->prepare("INSERT INTO MOVIE (title, genre, duration, rating, movie_descrp, image_poster) VALUES (?, ?, ?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("ssisss", $title, $genre, $duration, $rating, $description, $image_poster);
                $execute_result = $stmt->execute();
            } else {
                $execute_result = false;
                $error_message = "Error preparing statement: " . $conn->error;
            }
        }
        
        if ($execute_result) {
            $movie_id = $conn->insert_id;
            $success_message = "Movie added successfully!";
            
            // If show date and time are provided, add schedule
            if (!empty($show_date) && !empty($show_hour)) {
                $schedule_stmt = $conn->prepare("INSERT INTO MOVIE_SCHEDULE (movie_show_id, show_date, show_hour) VALUES (?, ?, ?)");
                $schedule_stmt->bind_param("iss", $movie_id, $show_date, $show_hour);
                
                if ($schedule_stmt->execute()) {
                    $success_message .= " Show schedule added successfully!";
                } else {
                    $error_message = "Movie added but schedule failed: " . $conn->error;
                }
                $schedule_stmt->close();
            }
        } else {
            $error_message = "Error adding movie: " . $conn->error;
        }
        $stmt->close();
    }
}

// Fetch all movies for reference
$movies_query = $conn->query("SELECT movie_show_id, title FROM MOVIE ORDER BY title");
$movies = [];
if ($movies_query) {
    while ($row = $movies_query->fetch_assoc()) {
        $movies[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Add Show - Admin Panel</title>
    <link rel="icon" type="image/png" href="images/brand x.png" />
    <link rel="stylesheet" href="css/admin-panel.css" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet" />
    <style>
        .form-container {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            max-width: 800px;
            margin: 0 auto;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            font-family: 'Poppins', sans-serif;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #00BFFF;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .btn-submit {
            background: linear-gradient(135deg, #00BFFF, #3C50B2);
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
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
        
        .add-schedule-section {
            margin-top: 30px;
            padding-top: 30px;
            border-top: 2px solid #e0e0e0;
        }
        
        .add-schedule-section h3 {
            margin-bottom: 20px;
            color: #333;
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
            <a href="add-show.php" class="active">Add Shows</a>
            <a href="view-shows.php">List Shows</a>
            <a href="view-bookings.php">List Bookings</a>
        </nav>
    </aside>
    <main class="main-content">
        <header>
            <h1>Add <span class="highlight">Show</span></h1>
        </header>

        <div class="form-container">
            <?php if ($success_message): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error_message) ?></div>
            <?php endif; ?>

            <form method="POST" action="add-show.php">
                <div class="form-group">
                    <label for="title">Movie Title *</label>
                    <input type="text" id="title" name="title" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="genre">Genre *</label>
                        <input type="text" id="genre" name="genre" required>
                    </div>
                    <div class="form-group">
                        <label for="duration">Duration (minutes) *</label>
                        <input type="number" id="duration" name="duration" min="1" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="rating">Rating *</label>
                        <select id="rating" name="rating" required>
                            <option value="">Select Rating</option>
                            <option value="G">G</option>
                            <option value="PG">PG</option>
                            <option value="PG-13">PG-13</option>
                            <option value="R">R</option>
                            <option value="NC-17">NC-17</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="image_poster">Image Poster URL</label>
                        <input type="text" id="image_poster" name="image_poster" placeholder="images/movie.jpg">
                    </div>
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" placeholder="Enter movie description..."></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" id="now_showing" name="now_showing" value="1" style="width: auto; cursor: pointer;" onchange="handleNowShowingChange()">
                            <span>Mark as Now Showing</span>
                        </label>
                    </div>
                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" id="coming_soon" name="coming_soon" value="1" style="width: auto; cursor: pointer;" onchange="handleComingSoonChange()">
                            <span>Mark as Coming Soon</span>
                        </label>
                    </div>
                </div>
                
                <script>
                    function handleNowShowingChange() {
                        const nowShowing = document.getElementById('now_showing');
                        const comingSoon = document.getElementById('coming_soon');
                        if (nowShowing.checked) {
                            comingSoon.checked = false;
                        }
                    }
                    
                    function handleComingSoonChange() {
                        const nowShowing = document.getElementById('now_showing');
                        const comingSoon = document.getElementById('coming_soon');
                        if (comingSoon.checked) {
                            nowShowing.checked = false;
                        }
                    }
                </script>

                <div class="add-schedule-section">
                    <h3>Add Show Schedule (Optional)</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="show_date">Show Date</label>
                            <input type="date" id="show_date" name="show_date">
                        </div>
                        <div class="form-group">
                            <label for="show_hour">Show Time</label>
                            <input type="time" id="show_hour" name="show_hour">
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn-submit">Add Show</button>
            </form>
        </div>
    </main>
</body>
</html>

