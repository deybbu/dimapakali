<?php
// --- PHP LOGIC START ---
$timings = ["06:30 AM", "09:30 AM", "12:00 PM", "04:30 PM", "08:00 PM"];
$selectedTime = $_GET['time'] ?? '06:30 AM'; // get selected time or default
// --- PHP LOGIC END ---
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Select Your Seat - Ticketix</title>
    <link rel="stylesheet" href="css/seats.css" />
    <style>
        /* Extra inline styles just for food buttons */
        .food-item {
            position: relative;
            background: rgba(0, 191, 255, 0.1);
            border-radius: 10px;
            padding: 10px;
            text-align: center;
        }
        .food-item img {
            width: 60px;
            height: 60px;
            object-fit: contain;
        }
        .food-controls {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 6px;
            margin-top: 6px;
        }
        .food-controls button {
            background: #00BFFF;
            border: none;
            color: white;
            font-weight: 700;
            font-size: 14px;
            width: 26px;
            height: 26px;
            border-radius: 6px;
            cursor: pointer;
            transition: background 0.3s ease;
        }
        .food-controls button:hover {
            background: #0099cc;
        }
        .food-controls span {
            font-size: 14px;
            font-weight: 600;
            width: 20px;
            text-align: center;
            color: #fff;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Left Sidebar -->
        <aside class="timings">
            <button class="back-btn" onclick="window.location.href='TICKETIX NI CLAIRE.php'">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                    <path fill-rule="evenodd" d="M15 8a.5.5 0 0 1-.5.5H3.707l3.147 3.146a.5.5 0 0 1-.708.708l-4-4a.5.5 0 0 1 0-.708l4-4a.5.5 0 1 1 .708.708L3.707 7.5H14.5A.5.5 0 0 1 15 8z"/>
                </svg>
                Back to Website
            </button>
            <h3>Available Timings</h3>
            <ul id="timing-list">
                <?php
                foreach ($timings as $time) {
                    $activeClass = ($time === $selectedTime) ? "active" : "";
                    echo "<li tabindex='0' role='button' class='$activeClass' data-time='$time'><span class='icon'>⏰</span> $time</li>";
                }
                ?>
            </ul>
        </aside>

        <!-- Seat Selection -->
        <main class="seat-selection">
            <h1>Select Your Seat</h1>
            <div class="screen"></div>

            <div class="seats-wrapper">
                <?php
                // --- RANDOM SEAT LOGIC START ---
                srand(crc32($selectedTime)); // same random layout per showtime

                $rows = ['A', 'B', 'C', 'D', 'E', 'F', 'G'];

                foreach ($rows as $row):
                    echo '<div class="row"><div class="row-label">' . $row . '</div>';
                    $totalSeats = 18;
                    for ($i = 1; $i <= $totalSeats; $i++):
                        if (($row === 'A' || $row === 'B') && $i > 9) {
                            if ($i == 10) echo '<div class="seat-gap"></div>';
                            continue;
                        }
                        if ($i == 10) echo '<div class="seat-gap"></div>';

                        // Random seat status
                        $isBooked = false;
                        $isSelected = false;

                        if (rand(1, 30) <= 5) $isBooked = true;
                        if (rand(1, 18) === 2) $isSelected = true;

                        $classes = "seat";
                        if ($isSelected) $classes .= " selected";
                        if ($isBooked) $classes .= " booked";

                        $disabled = $isBooked ? "aria-disabled='true' tabindex='-1'" : "tabindex='0' role='checkbox' aria-checked='" . ($isSelected ? "true" : "false") . "'";
                        $dataAttr = "data-seat='$row$i'";
                        $seatNumber = $row . '-' . $i;

                        echo "<div class='seat-container'>";
                        echo "<div class='$classes' $disabled $dataAttr></div>";
                        echo "<span class='seat-label'>$seatNumber</span>";
                        echo "</div>";
                    endfor;
                    echo '</div>';
                endforeach;
                // --- RANDOM SEAT LOGIC END ---
                ?>
            </div>

            <button id="proceed-btn" class="proceed-btn" disabled>
                Proceed to checkout <span>→</span>
            </button>
        </main>

        <!-- Right Movie Info Panel -->
        <aside class="movie-info">
            <h3>SM Mall of Asia</h3>
            <div class="movie-poster">
                <img src="img/toxic-avenger.jpg" alt="Movie Poster">
            </div>

            <div class="food-section">
                <h4>Food Selection</h4>
                <div class="food-grid">
                    <div class="food-item" data-item="All-In Combo">
                        <img src="images/all-in.png" alt="All-In Combo">
                        <div class="food-controls">
                            <button class="decrease">−</button>
                            <span class="count">0</span>
                            <button class="increase">+</button>
                        </div>
                    </div>
                    <div class="food-item" data-item="Hotdog & Coke">
                        <img src="images/hotdog-coke.png" alt="Hotdog & Coke">
                        <div class="food-controls">
                            <button class="decrease">−</button>
                            <span class="count">0</span>
                            <button class="increase">+</button>
                        </div>
                    </div>
                    <div class="food-item" data-item="Fries & Coke">
                        <img src="images/fries-coke.png" alt="Fries & Coke">
                        <div class="food-controls">
                            <button class="decrease">−</button>
                            <span class="count">0</span>
                            <button class="increase">+</button>
                        </div>
                    </div>
                    <div class="food-item" data-item="Fries">
                        <img src="images/fries-solo.png" alt="Fries">
                        <div class="food-controls">
                            <button class="decrease">−</button>
                            <span class="count">0</span>
                            <button class="increase">+</button>
                        </div>
                    </div>
                    <div class="food-item" data-item="Hotdog">
                        <img src="images/hotdog-solo.png" alt="Hotdog">
                        <div class="food-controls">
                            <button class="decrease">−</button>
                            <span class="count">0</span>
                            <button class="increase">+</button>
                        </div>
                    </div>
                    <div class="food-item" data-item="Coke">
                        <img src="images/coke-solo.png" alt="Coke">
                        <div class="food-controls">
                            <button class="decrease">−</button>
                            <span class="count">0</span>
                            <button class="increase">+</button>
                        </div>
                    </div>
                    <div class="food-item" data-item="Popcorn">
                        <img src="images/popcorn-solo.png" alt="Popcorn">
                        <div class="food-controls">
                            <button class="decrease">−</button>
                            <span class="count">0</span>
                            <button class="increase">+</button>
                        </div>
                    </div>
                </div>
            </div>
        </aside>
    </div>

    <script>
        // --- Timing Selection ---
        const timingItems = document.querySelectorAll('#timing-list li');
        timingItems.forEach(item => {
            item.addEventListener('click', () => {
                const selectedTime = item.dataset.time;
                window.location.href = `seat-reservation.php?time=${encodeURIComponent(selectedTime)}`;
            });
        });

        // --- Seat Selection ---
        const seats = document.querySelectorAll('.seat:not(.booked)');
        const proceedBtn = document.getElementById('proceed-btn');
        let selectedSeats = new Set();

        seats.forEach(seat => {
            seat.addEventListener('click', () => {
                const seatId = seat.getAttribute('data-seat');
                seat.classList.toggle('selected');
                seat.setAttribute('aria-checked', seat.classList.contains('selected'));
                if (seat.classList.contains('selected')) selectedSeats.add(seatId);
                else selectedSeats.delete(seatId);
                proceedBtn.disabled = selectedSeats.size === 0;
            });
        });

        // --- Food Quantity Controls ---
        const foodItems = document.querySelectorAll('.food-item');
        let foodSelections = {};

        foodItems.forEach(item => {
            const increaseBtn = item.querySelector('.increase');
            const decreaseBtn = item.querySelector('.decrease');
            const countDisplay = item.querySelector('.count');
            const itemName = item.dataset.item;
            let count = 0;

            increaseBtn.addEventListener('click', () => {
                count++;
                countDisplay.textContent = count;
                foodSelections[itemName] = count;
            });

            decreaseBtn.addEventListener('click', () => {
                if (count > 0) count--;
                countDisplay.textContent = count;
                if (count === 0) delete foodSelections[itemName];
                else foodSelections[itemName] = count;
            });
        });

        // --- Proceed Button ---
        proceedBtn.addEventListener('click', () => {
            const selectedTiming = document.querySelector('.timings .active').dataset.time;
            const selectedSeatNumbers = [...selectedSeats];
            let foodSummary = Object.entries(foodSelections)
                .map(([item, qty]) => `${item} (${qty})`)
                .join(', ');
            if (!foodSummary) foodSummary = "No food selected";

            alert(
                '🎬 Selected timing: ' + selectedTiming +
                '\n🪑 Selected Seats: ' + selectedSeatNumbers.join(', ') +
                '\n🍿 Food: ' + foodSummary
            );
        });
    </script>
</body>
</html>
