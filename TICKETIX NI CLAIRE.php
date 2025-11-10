<?php
session_start();
require_once 'config.php';

// Fetch movies from database
$conn = getDBConnection();
$today = date('Y-m-d');

// Check if now_showing column exists
$columns_check = $conn->query("SHOW COLUMNS FROM MOVIE LIKE 'now_showing'");
$has_now_showing = $columns_check && $columns_check->num_rows > 0;

// Fetch Now Showing movies
if ($has_now_showing) {
    // If now_showing column exists, include movies marked as now_showing OR with schedules >= today
    // BUT EXCLUDE movies marked as coming_soon (coming_soon takes priority)
    $nowShowingQuery = $conn->query("
        SELECT DISTINCT m.*
        FROM MOVIE m
        LEFT JOIN MOVIE_SCHEDULE ms ON m.movie_show_id = ms.movie_show_id
        WHERE (m.coming_soon = FALSE OR m.coming_soon IS NULL)
        AND (m.now_showing = TRUE OR (ms.show_date >= '$today' AND ms.show_date IS NOT NULL))
        GROUP BY m.movie_show_id
        ORDER BY m.title ASC
        LIMIT 10
    ");
} else {
    // If column doesn't exist, use schedules only
    $nowShowingQuery = $conn->query("
        SELECT DISTINCT m.*
        FROM MOVIE m
        INNER JOIN MOVIE_SCHEDULE ms ON m.movie_show_id = ms.movie_show_id
        WHERE ms.show_date >= '$today'
        GROUP BY m.movie_show_id
        ORDER BY m.title ASC
        LIMIT 10
    ");
}

$nowShowingMovies = [];
if ($nowShowingQuery) {
    while ($row = $nowShowingQuery->fetch_assoc()) {
        $nowShowingMovies[] = $row;
    }
}

// If no movies found and now_showing column exists, show all movies marked as now_showing (excluding coming_soon)
if (empty($nowShowingMovies) && $has_now_showing) {
    $fallbackQuery = $conn->query("SELECT * FROM MOVIE WHERE now_showing = TRUE AND (coming_soon = FALSE OR coming_soon IS NULL) ORDER BY title ASC LIMIT 10");
    if ($fallbackQuery) {
        while ($row = $fallbackQuery->fetch_assoc()) {
            $nowShowingMovies[] = $row;
        }
    }
}

// Final fallback: if still no movies, show all movies EXCEPT coming_soon (for testing - remove in production)
if (empty($nowShowingMovies)) {
    if ($has_now_showing) {
        $allMoviesQuery = $conn->query("SELECT * FROM MOVIE WHERE (coming_soon = FALSE OR coming_soon IS NULL) ORDER BY title ASC LIMIT 10");
    } else {
        $allMoviesQuery = $conn->query("SELECT * FROM MOVIE ORDER BY title ASC LIMIT 10");
    }
    if ($allMoviesQuery) {
        while ($row = $allMoviesQuery->fetch_assoc()) {
            $nowShowingMovies[] = $row;
        }
    }
}

// Fetch Coming Soon movies
if ($has_now_showing) {
    // If coming_soon column exists, include movies marked as coming_soon OR with schedules > today
    $comingSoonQuery = $conn->query("
        SELECT DISTINCT m.*, MIN(ms.show_date) AS earliest_date
        FROM MOVIE m
        LEFT JOIN MOVIE_SCHEDULE ms ON m.movie_show_id = ms.movie_show_id
        WHERE (m.coming_soon = TRUE OR (ms.show_date > '$today' AND ms.show_date IS NOT NULL))
        AND (m.now_showing = FALSE OR m.now_showing IS NULL)
        GROUP BY m.movie_show_id
        ORDER BY m.title ASC
        LIMIT 10
    ");
} else {
    // If column doesn't exist, use schedules only
    $comingSoonQuery = $conn->query("
        SELECT DISTINCT m.*, MIN(ms.show_date) AS earliest_date
        FROM MOVIE m
        INNER JOIN MOVIE_SCHEDULE ms ON m.movie_show_id = ms.movie_show_id
        WHERE ms.show_date > '$today'
        AND m.movie_show_id NOT IN (
            SELECT DISTINCT movie_show_id 
            FROM MOVIE_SCHEDULE 
            WHERE show_date = '$today'
        )
        GROUP BY m.movie_show_id
        ORDER BY m.title ASC
        LIMIT 10
    ");
}

$comingSoonMovies = [];
if ($comingSoonQuery) {
    while ($row = $comingSoonQuery->fetch_assoc()) {
        $comingSoonMovies[] = $row;
    }
}

// If no movies found and coming_soon column exists, show all movies marked as coming_soon
if (empty($comingSoonMovies) && $has_now_showing) {
    $fallbackQuery = $conn->query("SELECT * FROM MOVIE WHERE coming_soon = TRUE AND (now_showing = FALSE OR now_showing IS NULL) ORDER BY title ASC LIMIT 10");
    if ($fallbackQuery) {
        while ($row = $fallbackQuery->fetch_assoc()) {
            $comingSoonMovies[] = $row;
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Ticketix</title>
  <link rel="icon" type="image/png" href="images/brand x.png" />
  <link rel="stylesheet" href="css/style.css?v=<?php echo time(); ?>">
  <link rel="stylesheet" href="css/ticketix-main.css?v=<?php echo time(); ?>">
</head>

<body>
  <header>
  <div class="left-section">
    <div class="logo">
      <img src="images/brand x.png" alt="images/Ticketix Logo">
    </div>

    <nav>
      <a href="#home" class="active">Home</a>
      <a href="#now-showing">Now Showing</a>
      <a href="#coming-soon">Coming Soon</a>
      <a href="#contact">Contact Us</a>
    </nav>

      
      <form class="nav-search-form" method="GET" action="search.php">
      <label for="nav-search" class="nav-search-label">Search Movies:</label>
        <input type="text" id="nav-search" name="q" placeholder="Search..." class="nav-search-input" required>
        <button type="submit" class="nav-search-btn">🔍</button>
    </form>
    </nav>
  </div>

  <div class="right-section">
    <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']): ?>
      <a href="seat-reservation.php" class="ticket-btn" style="text-decoration: none; display: inline-block; color: white; padding: 10px 25px; border-radius: 25px; cursor: pointer;">Buy Tickets</a>
    <?php else: ?>
      <a href="login.php" class="ticket-btn" style="text-decoration: none; display: inline-block; color: white; padding: 10px 25px; border-radius: 25px; cursor: pointer;">Buy Tickets</a>
    <?php endif; ?>
    <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']): ?>
      <div class="user-profile">
        <button class="profile-button" onclick="toggleProfileDropdown()" aria-label="User Profile">
          <?php 
            $userName = htmlspecialchars($_SESSION['user_name']);
            $initials = '';
            $nameParts = explode(' ', $userName);
            if (count($nameParts) >= 2) {
              $initials = strtoupper(substr($nameParts[0], 0, 1) . substr($nameParts[count($nameParts) - 1], 0, 1));
            } else {
              $initials = strtoupper(substr($userName, 0, 2));
            }
          ?>
          <span class="profile-initials"><?php echo $initials; ?></span>
        </button>
        <div class="profile-dropdown" id="profileDropdown">
          <div class="dropdown-header">
            <div class="dropdown-header-initials"><?php echo $initials; ?></div>
            <div class="dropdown-header-name"><?php echo $userName; ?></div>
          </div>
          <div class="dropdown-divider"></div>
          <a href="account-settings.php" class="dropdown-item">
            <class="dropdown-icon">⚙️ Account Settings
          </a>
          <a href="my-bookings.php" class="dropdown-item">
            <class="dropdown-icon">📅 My Bookings
          </a>
          <a href="profile.php" class="dropdown-item">
            <class="dropdown-icon">👤 My Profile
          </a>
          <div class="dropdown-divider"></div>
          <a href="logout.php" class="dropdown-item">
            <class="dropdown-icon">➜] Logout
          </a>
        </div>
      </div>
    <?php else: ?>
      <a href="login.php" class="login-link"><i class="user-icon"></i> Log In / Sign Up</a>
    <?php endif; ?>
  </div>
  </header>

  <section id="home" class="hero">
  <button class="arrow left" onclick="changeSlide(-1)">&#10094;</button>

  <div class="hero-slides">
    <!-- Slide 1: Tron: Ares -->
    <div class="hero-slide active">
      <div class="hero-background" style="background-image: url('images/TRON.png');"></div>
      <div class="hero-content">
        <h1>Tron: Ares</h1>
        <p>Now Showing</p>
      </div>
    </div>

    <!-- Slide 2: Chainsaw Man -->
    <div class="hero-slide">
      <div class="hero-background" style="background-image: url('images/chainsawmanbd.png');"></div>
      <div class="hero-content">
        <h1>Chainsaw Man</h1>
        <p>Now Showing</p>
      </div>
    </div>

    <!-- Slide 3: Black Phone -->
    <div class="hero-slide">
      <div class="hero-background" style="background-image: url('images/blackphonebd.png');"></div>
      <div class="hero-content">
        <h1>Black Phone</h1>
        <p>Now Showing</p>
      </div>
    </div>

    <!-- Slide 4: Goodboy -->
    <div class="hero-slide">
      <div class="hero-background" style="background-image: url('images/goodboybd.png');"></div>
      <div class="hero-content">
        <h1>Good Boy</h1>
        <p>Now Showing</p>
      </div>
    </div>

    <!-- Slide 5: Quezon -->
    <div class="hero-slide">
      <div class="hero-background" style="background-image: url('images/QUEZON.jpg');"></div>
      <div class="hero-content">
        <h1>Quezon</h1>
        <p>Now Showing</p>
      </div>
    </div>
  </div>

  <button class="arrow right" onclick="changeSlide(1)">&#10095;</button>
  
  <!-- Slide indicators -->
  <div class="slide-indicators">
    <span class="indicator active" onclick="currentSlide(1)"></span>
    <span class="indicator" onclick="currentSlide(2)"></span>
    <span class="indicator" onclick="currentSlide(3)"></span>
    <span class="indicator" onclick="currentSlide(4)"></span>
    <span class="indicator" onclick="currentSlide(5)"></span>
  </div>
</section>


  <section id="now-showing">
    <h2>Now Showing</h2>
    <div class="movie-grid">
      <?php if (count($nowShowingMovies) > 0): ?>
        <?php foreach ($nowShowingMovies as $movie): 
          // Format duration: convert minutes to hours format
          $duration_min = intval($movie['duration'] ?? 0);
          $hours = floor($duration_min / 60);
          $minutes = $duration_min % 60;
          $duration_formatted = $hours > 0 ? $hours . 'h ' . $minutes . 'm' : $minutes . 'm';
          
          $title = htmlspecialchars($movie['title']);
          $genre = htmlspecialchars($movie['genre']);
          $rating = htmlspecialchars($movie['rating'] ?: 'N/A');
          $image = htmlspecialchars($movie['image_poster'] ?: 'images/default.png');
        ?>
          <div class="movie" onclick="openMovieModal('<?= $title ?>', '<?= $genre ?>', '<?= $duration_formatted ?>', '<?= $rating ?>', '<?= $image ?>')">
            <img src="<?= $image ?>" alt="<?= $title ?>">
            <div class="movie-overlay">
              <div class="movie-info">
                <h3><?= $title ?></h3>
                <p><?= $genre ?> • <?= $duration_formatted ?> • <?= $rating ?></p>
                <div class="movie-actions">
                  <button class="action-btn trailer-btn" onclick="event.stopPropagation(); openTrailer('<?= $title ?>')">▶ Trailer</button>
                  <a href="seat-reservation.php?movie=<?= urlencode($title) ?>" class="action-btn ticket-btn" style="text-decoration: none; display: inline-block;" onclick="event.stopPropagation(); return true;">🎟 Buy Tickets</a>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <p style="text-align: center; color: #ccc; grid-column: 1 / -1; padding: 40px;">No movies currently showing. Check back soon!</p>
      <?php endif; ?>
    </div>
  </section>

  <section id="coming-soon">
    <h2>Coming Soon</h2>
    <div class="movie-grid">
      <?php if (count($comingSoonMovies) > 0): ?>
        <?php foreach ($comingSoonMovies as $movie): 
          // Format duration: convert minutes to hours format
          $duration_min = intval($movie['duration'] ?? 0);
          $hours = floor($duration_min / 60);
          $minutes = $duration_min % 60;
          $duration_formatted = $hours > 0 ? $hours . 'h ' . $minutes . 'm' : $minutes . 'm';
          
          $title = htmlspecialchars($movie['title']);
          $genre = htmlspecialchars($movie['genre']);
          $rating = htmlspecialchars($movie['rating'] ?: 'N/A');
          $image = htmlspecialchars($movie['image_poster'] ?: 'images/default.png');
          
          // Get release date from query result
          $releaseDate = 'Coming Soon';
          if (!empty($movie['earliest_date'])) {
            $releaseDate = date('F d, Y', strtotime($movie['earliest_date']));
          }
        ?>
          <div class="movie">
            <img src="<?= $image ?>" alt="<?= $title ?>">
            <div class="movie-info">
              <h3><?= $title ?></h3>
              <p><?= $genre ?> • <?= $duration_formatted ?> • <?= $rating ?></p>
              <p class="release-date"><?= $releaseDate ?></p>
              <button class="notify-btn" onclick="alert('We will notify you when <?= $title ?> becomes available!')">Notify Me</button>
            </div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <p style="text-align: center; color: #ccc; grid-column: 1 / -1; padding: 40px;">No upcoming movies. Check back soon!</p>
      <?php endif; ?>
    </div>
  </section>

  <section id="contact">
    <h2>Contact Us</h2>
    <div class="contact-content">
      <div class="contact-info">
        <h3>Get in Touch</h3>
        <p><strong>Address:</strong>&nbsp;504 J. P. Rizal St Marikina City, 1808, Metro Manila</p>
        <p><strong>Phone:</strong>&nbsp;+63 994 931 9562</p>
        <p><strong>Email:</strong>&nbsp;ticketix0@gmail.com</p>
        <p><strong>Business Hours:</strong>&nbsp;Monday - Sunday: 9:00 AM - 11:00 PM</p>
        
        <div class="social-links">
          <h4>Follow Us:</h4>
          <a href="https://www.facebook.com/photo?fbid=110530527255536&set=a.110530550588867">Facebook</a>
          <a href="https://www.facebook.com/photo?fbid=110530527255536&set=a.110530550588867">Instagram</a>
          <a href="https://www.facebook.com/photo?fbid=110530527255536&set=a.110530550588867">Twitter</a>
          <a href="https://www.facebook.com/photo?fbid=110530527255536&set=a.110530550588867">TikTok</a>
          <a href="https://www.facebook.com/photo?fbid=110530527255536&set=a.110530550588867">YouTube</a>
        </div>
      </div>

      <div class="contact-form">
        <h3>Send us a Message</h3>
        <form action="TICKETIX NI CLAIRE.php" method="POST">
          <input type="text" name="name" placeholder="Your Name" required>
          <input type="email" name="email" placeholder="Your Email" required>
          <input type="text" name="subject" placeholder="Subject" required>
          <textarea name="message" placeholder="Your Message" required></textarea>
          <button type="submit">Send Message</button>
        </form>
        
        <?php
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            $name = $_POST['name'] ?? '';
            $email = $_POST['email'] ?? '';
            $subject = $_POST['subject'] ?? '';
            $message = $_POST['message'] ?? '';
            
            if (!empty($name) && !empty($email) && !empty($subject) && !empty($message)) {
                echo '<div style="color: white; margin-top: 15px; padding: 10px; background-color: rgba(0,0,0,0.3); border-radius: 5px;">';
                echo 'Thank you for your message, ' . htmlspecialchars($name) . '! We will get back to you soon.';
                echo '</div>';
            }
        }
        ?>
      </div>
    </div>
    
    <!-- Search Bar Section -->
    <div class="search-section">
      <div class="search-container">
        <h3>Search Movies</h3>
        <p>Find your favorite movies and discover new ones</p>
        <form class="search-form" method="GET" action="search.php">
          <input type="text" name="q" class="search-input" placeholder="Search for movies, genres, or descriptions..." required>
          <button type="submit" class="search-btn">🔍 Search</button>
        </form>
      </div>
    </div>
  </section>

  <!-- Trailer Modal -->
  <div id="trailerModal" class="modal">
    <div class="modal-content trailer-modal">
      <span class="close" onclick="closeTrailer()">&times;</span>
      <h2 id="trailerTitle">Movie Trailer</h2>
      <div id="trailerContainer">
        <div class="trailer-placeholder" id="trailerPlaceholder">
          <div class="trailer-icon">🎬</div>
          <p>Trailer for <span id="trailerMovieName"></span> will be available soon!</p>
          <p>In the meantime, you can watch trailers on our official YouTube channel.</p>
          <button class="btn" onclick="window.open('https://youtube.com', '_blank')">Visit YouTube</button>
        </div>
        <div id="youtubePlayer" style="display: none;">
          <iframe id="trailerVideo" width="100%" height="400" src="" frameborder="0" allowfullscreen></iframe>
        </div>
      </div>
    </div>
  </div>

  <!-- Movie Detail Modal -->
<div id="movieModal" class="modal">
  <div class="modal-content movie-detail-modal">
    <span class="close" onclick="closeMovieModal()">&times;</span>
    <div class="movie-detail-content">
      <div class="movie-poster">
        <img id="modalMoviePoster" src="" alt="Movie Poster">
      </div>
      <div class="movie-details">
        <h2 id="modalMovieTitle">Movie Title</h2>
        <p id="modalMovieGenre">Genre</p>
        <p id="modalMovieDuration">Duration</p>
        <p id="modalMovieRating">Rating</p>
        <div class="movie-description">
          <p>Experience the ultimate cinematic adventure with stunning visuals and an unforgettable story.</p>
        </div>
        <div class="modal-actions">
          <!-- Removed the Watch Trailer button -->
          <a href="#" class="action-btn ticket-btn" onclick="goToSeatReservation(document.getElementById('modalMovieTitle').textContent); return false;" style="text-decoration: none; display: inline-block;">
            🎟 Buy Tickets
          </a>
        </div>
      </div>
    </div>
  </div>
</div>


  <!-- Booking Modal -->
  <div id="bookingModal" class="modal">
    <div class="modal-content booking-modal">
      <span class="close" onclick="closeBooking()">&times;</span>
      <h2>Book Tickets for <span id="bookingMovieName"></span></h2>
      
      <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']): ?>
        <div class="booking-form">
          <div class="form-group">
            <label for="showtime">Select Showtime:</label>
            <select id="showtime" required>
              <option value="">Choose a showtime</option>
              <option value="10:00 AM">10:00 AM</option>
              <option value="1:00 PM">1:00 PM</option>
              <option value="4:00 PM">4:00 PM</option>
              <option value="7:00 PM">7:00 PM</option>
              <option value="10:00 PM">10:00 PM</option>
            </select>
          </div>
          
          <div class="form-group">
            <label for="tickets">Number of Tickets:</label>
            <select id="tickets" required>
              <option value="">Select quantity</option>
              <option value="1">1 Ticket</option>
              <option value="2">2 Tickets</option>
              <option value="3">3 Tickets</option>
              <option value="4">4 Tickets</option>
              <option value="5">5 Tickets</option>
            </select>
          </div>
          
          <div class="form-group">
            <label for="seatType">Seat Type:</label>
            <select id="seatType" required>
              <option value="">Choose seat type</option>
              <option value="regular">Regular - ₱250</option>
              <option value="vip">VIP - ₱350</option>
            </select>
          </div>
          
          <div class="price-display">
            <p>Total Price: <span id="totalPrice">₱0</span></p>
          </div>
          
          <button class="btn book-now-btn" onclick="processBooking()">Book Now</button>
        </div>
      <?php else: ?>
        <div class="login-required">
          <p>Please log in to book tickets.</p>
          <a href="login.php" class="btn">Login</a>
          <a href="signup.html" class="btn">Sign Up</a>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <footer>
  <div class="footer-left">
    <img src="images/logo sha.png" alt="images/Ticketix Logo">
  </div>

  <div class="footer-center">
    <nav>
      <a href="#">About</a>
      <a href="#">Contact</a>
      <a href="#">Privacy Policy</a>
    </nav>
    <p>© 2025 Ticketix. All Rights Reserved.</p>
  </div>

  <div class="footer-right">
    <p class="follow-title">FOLLOW US</p>
    <div class="social-icons">
      <a href="https://www.facebook.com/photo?fbid=110530527255536&set=a.110530550588867"><img src="images/facebook.png" alt="Facebook"></a>
      <a href="https://www.facebook.com/photo?fbid=110530527255536&set=a.110530550588867"><img src="images/instagram.png" alt="Instagram"></a>
      <a href="https://www.facebook.com/photo?fbid=110530527255536&set=a.110530550588867"><img src="images/x.png" alt="X"></a>
      <a href="https://www.facebook.com/photo?fbid=110530527255536&set=a.110530550588867"><img src="images/tiktok.png" alt="TikTok"></a>
      <a href="https://www.facebook.com/photo?fbid=110530527255536&set=a.110530550588867"><img src="images/youtube.png" alt="YouTube"></a>
    </div>
  </div>
</footer>

<script>
let currentSlideIndex = 0;
const slides = document.querySelectorAll('.hero-slide');
const indicators = document.querySelectorAll('.indicator');

function showSlide(index) {
    // Remove all classes from slides
    slides.forEach(slide => {
        slide.classList.remove('active', 'prev');
    });
    
    // Remove active class from all indicators
    indicators.forEach(indicator => indicator.classList.remove('active'));
    
    // Add active class to current slide and indicator
    slides[index].classList.add('active');
    indicators[index].classList.add('active');
    
    // Add prev class to previous slide for sliding effect
    const prevIndex = index === 0 ? slides.length - 1 : index - 1;
    slides[prevIndex].classList.add('prev');
}

function changeSlide(direction) {
    currentSlideIndex += direction;
    
    // Handle wrap-around
    if (currentSlideIndex >= slides.length) {
        currentSlideIndex = 0;
    } else if (currentSlideIndex < 0) {
        currentSlideIndex = slides.length - 1;
    }
    
    showSlide(currentSlideIndex);
}

function currentSlide(index) {
    currentSlideIndex = index - 1; // Convert to 0-based index
    showSlide(currentSlideIndex);
}

// Auto-play functionality (optional)
let autoPlayInterval;

function startAutoPlay() {
    autoPlayInterval = setInterval(() => {
        changeSlide(1);
    }, 5000); // Change slide every 5 seconds
}

function stopAutoPlay() {
    clearInterval(autoPlayInterval);
}

// Start auto-play when page loads
document.addEventListener('DOMContentLoaded', function() {
    // startAutoPlay(); // DISABLED - Automatic transitions turned off
    
    // Pause auto-play when user hovers over carousel
    const hero = document.querySelector('.hero');
    hero.addEventListener('mouseenter', stopAutoPlay);
    hero.addEventListener('mouseleave', startAutoPlay);
    
    // Pause auto-play when user clicks arrows or indicators
    const arrows = document.querySelectorAll('.arrow');
    const indicators = document.querySelectorAll('.indicator');
    
    arrows.forEach(arrow => {
        arrow.addEventListener('click', () => {
            stopAutoPlay();
            // setTimeout(startAutoPlay, 10000); // DISABLED - No auto-resume
        });
    });
    
    indicators.forEach(indicator => {
        indicator.addEventListener('click', () => {
            stopAutoPlay();
            // setTimeout(startAutoPlay, 10000); // DISABLED - No auto-resume
        });
    });
});

// Keyboard navigation
document.addEventListener('keydown', function(event) {
    if (event.key === 'ArrowLeft') {
        changeSlide(-1);
        stopAutoPlay();
        // setTimeout(startAutoPlay, 10000); // DISABLED - No auto-resume
    } else if (event.key === 'ArrowRight') {
        changeSlide(1);
        stopAutoPlay();
        // setTimeout(startAutoPlay, 10000); // DISABLED - No auto-resume
    }
});

// Movie trailer data - YouTube video IDs (using publicly available trailers)
const movieTrailers = {
    'Tron: Ares': 'YShVEXb7-ic', // Placeholder - replace with actual Tron: Ares trailer
    'Chainsaw Man': 'VfoZp7CmOkE', // Placeholder - replace with actual Chainsaw Man trailer
    'Black Phone': 'DdR-gzFZoDk', // Placeholder - replace with actual Black Phone trailer
    'Good Boy': 'q4-CRkd_74g', // Placeholder - replace with actual Good Boy trailer
    'Quezon': 'vgr-ABdgy9c', // Placeholder - replace with actual Quezon trailer
    'One in a Million': 'dQw4w9WgXcQ', // Placeholder - replace with actual One in a Million trailer
    'Shelby': 'dQw4w9WgXcQ', // Placeholder - replace with actual Shelby trailer
    'Now You See Me 3': 'dQw4w9WgXcQ', // Placeholder - replace with actual Now You See Me 3 trailer
    'Predator: The Hunt': 'dQw4w9WgXcQ', // Placeholder - replace with actual Predator trailer
    'Meet Greet Bye': 'dQw4w9WgXcQ' // Placeholder - replace with actual Meet Greet Bye trailer
};

// Trailer Modal Functions
function openTrailer(movieName) {
    console.log('Opening trailer for:', movieName); // Debug log
    document.getElementById('trailerMovieName').textContent = movieName;
    document.getElementById('trailerTitle').textContent = movieName + ' - Trailer';
    
    // Check if we have a trailer for this movie
    const trailerId = movieTrailers[movieName];
    
    if (trailerId) {
        // Show YouTube player
        document.getElementById('trailerPlaceholder').style.display = 'none';
        document.getElementById('youtubePlayer').style.display = 'block';
        
        // Load the trailer video
        const videoUrl = `https://www.youtube.com/embed/${trailerId}?autoplay=1&rel=0&modestbranding=1`;
        document.getElementById('trailerVideo').src = videoUrl;
    } else {
        // Show placeholder
        document.getElementById('trailerPlaceholder').style.display = 'block';
        document.getElementById('youtubePlayer').style.display = 'none';
    }
    
    document.getElementById('trailerModal').style.display = 'block';
    stopAutoPlay(); // Pause carousel when modal opens
}

function closeTrailer() {
    // Stop video playback when closing modal
    const video = document.getElementById('trailerVideo');
    if (video) {
        video.src = ''; // This stops the video
    }
    
    document.getElementById('trailerModal').style.display = 'none';
    // startAutoPlay(); // DISABLED - No auto-resume when modal closes
}

// Direct navigation to seat reservation
function goToSeatReservation(movieName) {
    // Close any open modals
    closeMovieModal();
    closeBooking();
    
    <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']): ?>
        window.location.href = 'seat-reservation.php?movie=' + encodeURIComponent(movieName);
    <?php else: ?>
        window.location.href = 'login.php';
    <?php endif; ?>
}

// Booking Modal Functions (kept for backward compatibility, but not used anymore)
function openBooking(movieName) {
    // Redirect directly to seat reservation instead of showing modal
    goToSeatReservation(movieName);
}

function closeBooking() {
    document.getElementById('bookingModal').style.display = 'none';
    // startAutoPlay(); // DISABLED - No auto-resume when modal closes
    resetBookingForm(); // Reset form
}

// Price calculation
function updatePrice() {
    const tickets = parseInt(document.getElementById('tickets').value) || 0;
    const seatType = document.getElementById('seatType').value;
    const priceDisplay = document.getElementById('totalPrice');
    
    let pricePerTicket = 0;
    if (seatType === 'regular') {
        pricePerTicket = 250;
    } else if (seatType === 'vip') {
        pricePerTicket = 350;
    }
    
    const totalPrice = tickets * pricePerTicket;
    priceDisplay.textContent = `₱${totalPrice}`;
}

// Process booking
function processBooking() {
    const movieName = document.getElementById('bookingMovieName').textContent;
    const showtime = document.getElementById('showtime').value;
    const tickets = document.getElementById('tickets').value;
    const seatType = document.getElementById('seatType').value;
    const totalPrice = document.getElementById('totalPrice').textContent;
    
    if (!showtime || !tickets || !seatType) {
        alert('Please fill in all fields');
        return;
    }
    
    // Redirect to seat reservation page with booking details
    const params = new URLSearchParams({
        movie: movieName,
        showtime: showtime,
        tickets: tickets,
        seatType: seatType,
        totalPrice: totalPrice.replace('₱', '')
    });
    
    window.location.href = 'seat-reservation.php?' + params.toString();
}

// Reset booking form
function resetBookingForm() {
    document.getElementById('showtime').value = '';
    document.getElementById('tickets').value = '';
    document.getElementById('seatType').value = '';
    document.getElementById('totalPrice').textContent = '₱0';
}

// Add event listeners for price calculation
document.addEventListener('DOMContentLoaded', function() {
    const ticketsSelect = document.getElementById('tickets');
    const seatTypeSelect = document.getElementById('seatType');
    
    if (ticketsSelect) {
        ticketsSelect.addEventListener('change', updatePrice);
    }
    
    if (seatTypeSelect) {
        seatTypeSelect.addEventListener('change', updatePrice);
    }
});

// Movie Detail Modal Functions
function openMovieModal(title, genre, duration, rating, posterSrc) {
    document.getElementById('modalMovieTitle').textContent = title;
    document.getElementById('modalMovieGenre').textContent = 'Genre: ' + genre;
    document.getElementById('modalMovieDuration').textContent = 'Duration: ' + duration;
    document.getElementById('modalMovieRating').textContent = 'Rating: ' + rating;
    document.getElementById('modalMoviePoster').src = posterSrc;
    document.getElementById('modalMoviePoster').alt = title + ' Poster';
    document.getElementById('movieModal').style.display = 'block';
    stopAutoPlay(); // Pause carousel when modal opens
}

function closeMovieModal() {
    document.getElementById('movieModal').style.display = 'none';
    // startAutoPlay(); // DISABLED - No auto-resume when modal closes
}

// Close modals when clicking outside
window.onclick = function(event) {
    const trailerModal = document.getElementById('trailerModal');
    const bookingModal = document.getElementById('bookingModal');
    const movieModal = document.getElementById('movieModal');
    
    if (event.target === trailerModal) {
        closeTrailer();
    }
    if (event.target === bookingModal) {
        closeBooking();
    }
    if (event.target === movieModal) {
        closeMovieModal();
    }
}

// Movie Grid Functions - Now Showing uses same layout as Coming Soon

// Search functionality
function performSearch() {
    const searchInput = document.querySelector('.search-input');
    const searchQuery = searchInput.value.trim();
    
    if (searchQuery.length < 2) {
        alert('Please enter at least 2 characters to search.');
        return;
    }
    
    // Redirect to search page with query
    window.location.href = `search.php?q=${encodeURIComponent(searchQuery)}`;
}

// Add search functionality to the search form
document.addEventListener('DOMContentLoaded', function() {
    const searchForm = document.querySelector('.search-form');
    if (searchForm) {
        searchForm.addEventListener('submit', function(e) {
            e.preventDefault();
            performSearch();
        });
    }
    
    // Add Enter key functionality to search input
    const searchInput = document.querySelector('.search-input');
    if (searchInput) {
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                performSearch();
            }
        });
    }
});

// Test function to verify JavaScript is working
function testFunction() {
    alert('JavaScript is working!');
    console.log('Test function called');
}

// Add click event listeners as backup
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, adding event listeners');
    
    // Add click listeners to all trailer buttons
    const trailerButtons = document.querySelectorAll('button[onclick*="openTrailer"]');
    trailerButtons.forEach(button => {
        button.addEventListener('click', function() {
            const movieName = this.getAttribute('onclick').match(/'([^']+)'/)[1];
            openTrailer(movieName);
        });
    });
    
    // Add click listeners to all booking buttons
    const bookingButtons = document.querySelectorAll('button[onclick*="openBooking"]');
    bookingButtons.forEach(button => {
        button.addEventListener('click', function() {
            const movieName = this.getAttribute('onclick').match(/'([^']+)'/)[1];
            openBooking(movieName);
        });
    });
    
    console.log('Event listeners added to', trailerButtons.length, 'trailer buttons and', bookingButtons.length, 'booking buttons');
});

// Smooth scroll
document.querySelectorAll('nav a').forEach(link => {
  link.addEventListener('click', function(e) {
    if (this.hash) {
      e.preventDefault();
      const target = document.querySelector(this.hash);
      window.scrollTo({
        top: target.offsetTop - 60,
        behavior: 'smooth'
      });
    }
  });
});

// Highlight active section on scroll
const sections = document.querySelectorAll('section');
const navLinks = document.querySelectorAll('nav a');

window.addEventListener('scroll', () => {
  let current = '';
  sections.forEach(section => {
    const sectionTop = section.offsetTop - 70;
    const sectionHeight = section.clientHeight;
    if (pageYOffset >= sectionTop && pageYOffset < sectionTop + sectionHeight) {
      current = section.getAttribute('id');
    }
  });

  navLinks.forEach(link => {
    link.classList.remove('active');
    if (link.getAttribute('href') === `#${current}`) {
      link.classList.add('active');
    }
  });
});

// Profile Dropdown Functions
function toggleProfileDropdown() {
  const dropdown = document.getElementById('profileDropdown');
  const button = document.querySelector('.profile-button');
  
  if (dropdown && button) {
    const isShowing = dropdown.classList.contains('show');
    
    if (isShowing) {
      dropdown.classList.remove('show');
      button.classList.remove('active');
    } else {
      dropdown.classList.add('show');
      button.classList.add('active');
    }
  }
}

// Close dropdown when clicking outside
document.addEventListener('click', function(event) {
  const profile = document.querySelector('.user-profile');
  const dropdown = document.getElementById('profileDropdown');
  const button = document.querySelector('.profile-button');
  
  if (profile && dropdown && button) {
    if (!profile.contains(event.target)) {
      dropdown.classList.remove('show');
      button.classList.remove('active');
    }
  }
});

// Close dropdown when clicking on a dropdown item
document.addEventListener('DOMContentLoaded', function() {
  const dropdownItems = document.querySelectorAll('.dropdown-item');
  dropdownItems.forEach(item => {
    item.addEventListener('click', function() {
      const dropdown = document.getElementById('profileDropdown');
      const button = document.querySelector('.profile-button');
      
      if (dropdown && button) {
        // Small delay to allow navigation before closing
        setTimeout(() => {
          dropdown.classList.remove('show');
          button.classList.remove('active');
        }, 100);
      }
    });
  });
});

</script>


</body>
</html>