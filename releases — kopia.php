<?php
session_start();

// Initialize chat if it doesn't exist
if (!isset($_SESSION['chat_messages'])) {
    $_SESSION['chat_messages'] = [
        ['user' => 'System', 'message' => 'Welcome to the chat!', 'time' => date('H:i')]
    ];
}

// Processing chat messages
if (isset($_POST['chat_message']) && !empty(trim($_POST['chat_message']))) {
    $username = isset($_POST['username']) && !empty(trim($_POST['username'])) ? 
                htmlspecialchars(trim($_POST['username'])) : 'Anonymous';
    $message = htmlspecialchars(trim($_POST['chat_message']));
    
    $_SESSION['chat_messages'][] = [
        'user' => $username,
        'message' => $message,
        'time' => date('H:i')
    ];
    
    // Limit the number of messages (last 50)
    if (count($_SESSION['chat_messages']) > 50) {
        $_SESSION['chat_messages'] = array_slice($_SESSION['chat_messages'], -50);
    }
    
    // Redirect to prevent resubmission on refresh
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// Function to get a random anime
function getRandomAnime() {
    $random_page = rand(1, 100);
    $url = "https://api.jikan.moe/v4/top/anime?page=" . $random_page . "&limit=25";
    
    $max_attempts = 3;
    $attempt = 0;
    
    while ($attempt < $max_attempts) {
        $json = @file_get_contents($url);
        
        if ($json !== false) {
            $data = json_decode($json, true);
            
            if (isset($data['data']) && !empty($data['data'])) {
                $random_anime = $data['data'][array_rand($data['data'])];
                return $random_anime['mal_id'];
            }
        }
        
        $attempt++;
        $random_page = rand(1, 50);
        $url = "https://api.jikan.moe/v4/top/anime?page=" . $random_page . "&limit=25";
    }
    
    return 1; // Fallback
}

// Handling the "Random" button
if (isset($_GET['random']) && $_GET['random'] == '1') {
    $random_id = getRandomAnime();
    if ($random_id) {
        header('Location: player.php?id=' . $random_id);
        exit;
    }
}

// Pagination parameters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$items_per_page = 24; // Number of anime per page

// Forming a single URL to get all types of anime
$base_url = "https://api.jikan.moe/v4/anime?" . 
            "order_by=start_date&" . 
            "sort=desc&" .
            "page=" . $page . 
            "&limit=" . $items_per_page; // Requesting all 24 anime at once

// Add search parameter if provided
if (!empty($_GET['search'])) {
    $base_url .= "&q=" . urlencode($_GET['search']);
}

// Array for parameters
$params = [];

// Add filters if they are set
if (!empty($_GET['genre'])) {
    $genres = is_array($_GET['genre']) ? implode(',', $_GET['genre']) : $_GET['genre'];
    $params['genres'] = $genres;
}

if (!empty($_GET['type'])) {
    $params['type'] = $_GET['type'];
}

if (!empty($_GET['status'])) {
    $params['status'] = $_GET['status'];
}

if (!empty($_GET['rating'])) {
    if ($_GET['rating'] !== '') {  // Add rating only if it's not empty
        $params['rating'] = $_GET['rating'];
    }
}

// Add parameters to the URL
if (!empty($params)) {
    foreach ($params as $key => $value) {
        $base_url .= "&" . urlencode($key) . "=" . urlencode($value);
    }
}

// Function to fetch data with retries
function fetchWithRetry($url, $maxAttempts = 3) {
    $attempt = 1;
    $data = null;

    while ($attempt <= $maxAttempts) {
        // Add a delay before each attempt (increases with each attempt)
        $delay = $attempt * 1000000; // in microseconds (1 sec = 1,000,000 µs)
        usleep($delay);

        // Configure request context
        $opts = [
            'http' => [
                'method' => 'GET',
                'header' => [
                    'User-Agent: AnimHome/1.0',
                    'Accept: application/json'
                ]
            ]
        ];
        $context = stream_context_create($opts);

        // Trying to get data
        $response = @file_get_contents($url, false, $context);
        
        if ($response !== false) {
            $data = json_decode($response, true);
            if ($data !== null) {
                return $data;
            }
        }
        
        // If we get a 429 error, wait longer before the next attempt
        if (isset($http_response_header[0]) && strpos($http_response_header[0], '429') !== false) {
            usleep(2000000); // Wait 2 seconds before the next attempt
        }
        
        $attempt++;
    }
    
    // If all attempts fail, return an empty result
    return ['data' => [], 'pagination' => ['last_visible_page' => 1]];
}

// Get data with one request
$data = fetchWithRetry($base_url);

// Initialize anime array
$animes = [];

// Check and add data
if (isset($data['data'])) {
    $animes = $data['data'];
}

// Make sure we have an array even if no data arrived
if (empty($animes)) {
    $animes = [];
}

// Custom sort
usort($animes, function($a, $b) {
    $status_a = $a['status'] ?? '';
    $status_b = $b['status'] ?? '';
    $year_a = $a['year'] ?? null;
    $year_b = $b['year'] ?? null;

    // 1. "Not yet aired" to the very end
    $a_is_upcoming = ($status_a === 'Not yet aired');
    $b_is_upcoming = ($status_b === 'Not yet aired');

    if ($a_is_upcoming && !$b_is_upcoming) return 1;
    if (!$a_is_upcoming && $b_is_upcoming) return -1;

    // 2. Anime without a year after anime with a year
    $a_has_year = !is_null($year_a);
    $b_has_year = !is_null($year_b);

    if ($a_has_year && !$b_has_year) return -1;
    if (!$a_has_year && $b_has_year) return 1;

    // 3. Sort by year (new to old)
    if ($a_has_year && $b_has_year) {
        if ($year_a != $year_b) {
            return $year_b <=> $year_a;
        }
    }

    return 0; // If everything else is equal
});

// Get the total number of pages from pagination
$total_pages = isset($data['pagination']['last_visible_page']) ? 
               $data['pagination']['last_visible_page'] : 1;

// Make sure the value is reasonable
if ($total_pages < 1) {
    $total_pages = 1;
}

// Function to generate pagination URL
function getPaginationUrl($page_number) {
    $params = $_GET;
    $params['page'] = $page_number;
    
    // Remove unnecessary parameters
    unset($params['submit']);
    
    // Form the URL with parameters
    $query = http_build_query($params);
    return $_SERVER['PHP_SELF'] . '?' . $query;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AnimHome - RELEASES</title>
    <link rel="stylesheet" href="css/style_p.css">
</head>
<body>

    <!-- Top banner -->
    <div class="top-banner">
        <img src="images/Реклама1.png" alt="Advertisement">
    </div>

    <!-- Main content -->
    <div class="main-layout">
        <!-- Left side: navigation + cards + bottom ad -->
        <div class="left-side">
            <!-- Navigation -->
            <div class="navigation">
                <nav>
                    <ul>
                        <li><a href="index.php">HOME</a></li>
                        <li><a href="releases.php">RELEASES</a></li>
                        <li><a href="releases.php?random=1" class="random-link">RANDOM</a></li>
                        <li><a href="#">Support the project</a></li>
                        <li><a href="Pidpro.html">Help Ukraine</a></li>
                    </ul>
                </nav>
            </div>

            <!-- Anime grid -->
            <div class="card-grid">
                <?php foreach ($animes as $anime): ?>
                    <a href="player.php?id=<?= $anime['mal_id'] ?>" title="<?= htmlspecialchars($anime['title']) ?>">
                        <div class="card">
                            <img src="<?= $anime['images']['jpg']['image_url'] ?>" alt="poster">
                            <h3><?= htmlspecialchars(!empty($anime['title_english']) ? $anime['title_english'] : ($anime['title'] ?? '')) ?></h3>
                            <p>Type: <?= $anime['type'] ?? 'N/A' ?></p>
                            <p>Episodes: <?= $anime['episodes'] ?? 'N/A' ?></p>
                        </div>
                    </a>
                <?php endforeach; ?>
                <?php if (empty($animes)): ?>
                    <p>No anime available.</p>
                <?php endif; ?>
            </div>

            <div class="pagination-area">
                <div class="side-ad">
                    <img src="images/Реклама3.png" alt="Ad on the left">
                </div>

                <div class="pagination-controls">
                    <?php if ($page > 1): ?>
                        <a href="<?= getPaginationUrl($page - 2) ?>" <?= ($page <= 2) ? 'style="display:none"' : '' ?>>
                            <button>&laquo;&laquo;</button>
                        </a>
                        <a href="<?= getPaginationUrl($page - 1) ?>">
                            <button>&laquo;</button>
                        </a>
                    <?php else: ?>
                        <button disabled>&laquo;&laquo;</button>
                        <button disabled>&laquo;</button>
                    <?php endif; ?>

                    <?php
                    // Smart pagination with a nice display
                    
                    // Always show the first page
                    ?>
                    <a href="<?= getPaginationUrl(1) ?>">
                        <button <?= ($page == 1) ? 'class="active"' : '' ?>>1</button>
                    </a>
                    <?php
                    
                    if ($total_pages <= 7) {
                        // If there are few pages (≤7), show all
                        for ($i = 2; $i <= $total_pages; $i++): ?>
                            <a href="<?= getPaginationUrl($i) ?>">
                                <button <?= ($page == $i) ? 'class="active"' : '' ?>><?= $i ?></button>
                            </a>
                        <?php endfor;
                    } else {
                        // If there are many pages, use smart logic
                        
                        if ($page <= 4) {
                            // Show: 1 2 3 4 5 ... last
                            for ($i = 2; $i <= 5; $i++): ?>
                                <a href="<?= getPaginationUrl($i) ?>">
                                    <button <?= ($page == $i) ? 'class="active"' : '' ?>><?= $i ?></button>
                                </a>
                            <?php endfor; ?>
                            <span>...</span>
                            <a href="<?= getPaginationUrl($total_pages) ?>">
                                <button><?= $total_pages ?></button>
                            </a>
                            <?php
                        } elseif ($page >= $total_pages - 3) {
                            // Show: 1 ... (last-4) (last-3) (last-2) (last-1) last
                            ?>
                            <span>...</span>
                            <?php
                            for ($i = $total_pages - 4; $i <= $total_pages; $i++): ?>
                                <a href="<?= getPaginationUrl($i) ?>">
                                    <button <?= ($page == $i) ? 'class="active"' : '' ?>><?= $i ?></button>
                                </a>
                            <?php endfor;
                        } else {
                            // Show: 1 ... (page-1) page (page+1) ... last
                            ?>
                            <span>...</span>
                            <?php
                            for ($i = $page - 1; $i <= $page + 1; $i++): ?>
                                <a href="<?= getPaginationUrl($i) ?>">
                                    <button <?= ($page == $i) ? 'class="active"' : '' ?>><?= $i ?></button>
                                </a>
                            <?php endfor; ?>
                            <span>...</span>
                            <a href="<?= getPaginationUrl($total_pages) ?>">
                                <button><?= $total_pages ?></button>
                            </a>
                            <?php
                        }
                    }
                    ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="<?= getPaginationUrl($page + 1) ?>">
                            <button>&raquo;</button>
                        </a>
                        <a href="<?= getPaginationUrl($page + 2) ?>" <?= ($page >= $total_pages - 1) ? 'style="display:none"' : '' ?>>
                            <button>&raquo;&raquo;</button>
                        </a>
                    <?php else: ?>
                        <button disabled>&raquo;</button>
                        <button disabled>&raquo;&raquo;</button>
                    <?php endif; ?>
                </div>

                <script>
                    // Add loading effect for pagination buttons
                    document.querySelectorAll('.pagination-controls a').forEach(link => {
                        link.addEventListener('click', function(e) {
                            e.preventDefault();
                            const button = this.querySelector('button');
                            if (button) {
                                button.innerHTML = '<span class="loading">LOADING</span>';
                                button.style.pointerEvents = 'none';
                                button.classList.add('loading-active');
                            }
                            setTimeout(() => {
                                window.location.href = this.href;
                            }, 500);
                        });
                    });
                </script>
            </div>
        </div>
    
        <!-- Right side: Filters -->
        <div class="filter-box">
            <form method="GET" class="filter-form">
                <div class="search-section">
                    <input type="text" name="search" placeholder="Search anime..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                </div>

                <div class="filter-header">
                    <h3>Search Filters</h3>
                </div>
                <div class="filter-section">
                    <label for="genre">Genre</label>
                    <select name="genre" id="genre">
                        <option value="">All Genres</option>
                        <?php
                        // Get genre list from API
                        $genres_url = "https://api.jikan.moe/v4/genres/anime";
                        $genres_json = @file_get_contents($genres_url);
                        $genres_data = json_decode($genres_json, true);
                        
                        if (isset($genres_data['data'])) {
                            foreach ($genres_data['data'] as $genre) {
                                $selected = isset($_GET['genre']) && 
                                    (is_array($_GET['genre']) ? 
                                        in_array($genre['mal_id'], $_GET['genre']) : 
                                        $_GET['genre'] == $genre['mal_id']) ? 'selected' : '';
                                echo "<option value='{$genre['mal_id']}' {$selected}>{$genre['name']}</option>";
                            }
                        }
                        ?>
                    </select>
                </div>

                <div class="filter-section">
                    <label for="type">Type</label>
                    <select name="type" id="type">
                        <option value="">All Types</option>
                        <?php
                        $types = ['TV', 'Movie', 'OVA', 'ONA', 'Special', 'Music'];
                        foreach ($types as $type) {
                            $selected = isset($_GET['type']) && $_GET['type'] === $type ? 'selected' : '';
                            echo "<option value='{$type}' {$selected}>{$type}</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="filter-section">
                    <label for="status">Status</label>
                    <select name="status" id="status">
                        <option value="">All Statuses</option>
                        <?php
                        $statuses = [
                            'airing' => 'Currently Airing',
                            'complete' => 'Finished Airing',
                            'upcoming' => 'Not yet aired'
                        ];
                        foreach ($statuses as $value => $label) {
                            $selected = isset($_GET['status']) && $_GET['status'] === $value ? 'selected' : '';
                            echo "<option value='{$value}' {$selected}>{$label}</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="filter-section">
                    <label for="rating">Age Rating</label>
                    <select name="rating" id="rating">
                        <option value="">All Ratings</option>
                        <?php
                        $ratings = ['G', 'PG', 'PG-13', 'R', 'R+', 'Rx'];
                        foreach ($ratings as $rating) {
                            $selected = isset($_GET['rating']) && $_GET['rating'] === $rating ? 'selected' : '';
                            echo "<option value='{$rating}' {$selected}>{$rating}</option>";
                        }
                        ?>
                    </select>
                </div>

                <?php
                // Save search parameter if it exists
                if (!empty($_GET['search'])) {
                    echo "<input type='hidden' name='search' value='" . htmlspecialchars($_GET['search']) . "'>";
                }
                ?>

                <button type="submit" class="filter-submit">Apply Filters</button>
            </form>
        </div>
    </div>

    <!-- Footer -->
    <footer>
        <div class="footer-block">
            <img src="images/Реклама2.png" alt="Advertisement 4">
        </div>
        <div class="footer-block">
            <p><a href="#">Boosty</a></p>
        </div>
        <div class="footer-block">
            <p><a href="#">Socials</a></p>
        </div>
        <div class="footer-block">
            <p><a href="#">Profile</a></p>
            <p><a href="#">Rules</a></p>
        </div>
    </footer>

    <script>
        // Automatically scroll chat down
        function scrollChatToBottom() {
            const chatMessages = document.getElementById('chatMessages');
            if (chatMessages) {
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }
        }
        
        // Scroll down on page load
        window.addEventListener('load', scrollChatToBottom);
        
        // Add animation for navigation buttons
        document.querySelectorAll('.navigation nav a').forEach(link => {
            link.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-2px)';
            });
            
            link.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });

            // Add loading effect on click
            if (!link.href.includes('#')) { // Only for real links
                link.addEventListener('click', function(e) {
                    this.innerHTML = '<span class="loading">LOADING</span>';
                    this.style.pointerEvents = 'none';
                    this.classList.add('loading-active');
                    
                    e.preventDefault();
                    setTimeout(() => {
                        window.location.href = this.href;
                    }, 1000); 
                });
            }
        });
    </script>

</body>
</html>