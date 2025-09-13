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

// Initialize $animes as array if not set
if (!isset($animes) || !is_array($animes)) {
    $animes = [];
}

// Add date range filter
if (!empty($_GET['min_year']) || !empty($_GET['max_year'])) {
    $min_year = !empty($_GET['min_year']) ? (int)$_GET['min_year'] : 1900;
    $max_year = !empty($_GET['max_year']) ? (int)$_GET['max_year'] : (int)date('Y') + 1;
    
    // Filter the anime array based on the year range
    if (is_array($animes)) {
        $animes = array_filter($animes, function($anime) use ($min_year, $max_year) {
            $anime_year = $anime['year'] ?? null;
            
            // If year is not set, keep the anime in the results
            if ($anime_year === null || $anime_year === '') {
                return true;
            }
            
            $anime_year = (int)$anime_year;
            return $anime_year >= $min_year && $anime_year <= $max_year;
        });
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

// Remove upcoming entries with Year = N/A or Year <= 2025
// Note: do NOT treat 'not yet' as an upcoming indicator here — user requested these remain visible
$animes = array_filter($animes, function($anime) {
    $status = strtolower((string)($anime['status'] ?? ''));
    // Only treat explicit 'upcoming' or 'to be' phrases as upcoming for year-based filtering
    $is_upcoming = strpos($status, 'upcoming') !== false || strpos($status, 'to be') !== false;
    $year = $anime['year'] ?? null;
    if ($is_upcoming) {
        if (!is_numeric($year)) return false; // remove if N/A
        if ((int)$year <= 2025) return false; // remove if year <= 2025
    }
    return true;
});

// Temporary debug output: expose count and first few entries (title, status, year) as an HTML comment for diagnosis
$debug_samples = array_slice($animes, 0, 10);
$debug_lines = [];
foreach ($debug_samples as $sample) {
    $debug_lines[] = sprintf("%s | status: %s | year: %s", ($sample['title'] ?? 'N/A'), ($sample['status'] ?? 'N/A'), var_export($sample['year'] ?? null, true));
}
echo "<!-- ANIME_COUNT:" . count($animes) . "\n" . implode("\n", $debug_lines) . " -->\n";

// Reindex array (usort expects indexed arrays)
$animes = array_values($animes);

// Custom sort
usort($animes, function($a, $b) {
    // Normalize and detect "upcoming" statuses robustly
    $normalize = function($s) {
        return trim(strtolower((string)($s ?? '')));
    };

    $s_a = $normalize($a['status'] ?? '');
    $s_b = $normalize($b['status'] ?? '');

    // Treat several variants as upcoming
    $upcoming_keywords = ['not yet aired', 'upcoming', 'not yet', 'to be aired'];
    $a_is_upcoming = false;
    $b_is_upcoming = false;

    foreach ($upcoming_keywords as $kw) {
        if ($s_a === $kw || strpos($s_a, $kw) !== false) { $a_is_upcoming = true; break; }
    }
    foreach ($upcoming_keywords as $kw) {
        if ($s_b === $kw || strpos($s_b, $kw) !== false) { $b_is_upcoming = true; break; }
    }

    // Rule 1: Any upcoming goes to the end
    if ($a_is_upcoming && !$b_is_upcoming) return 1;
    if (!$a_is_upcoming && $b_is_upcoming) return -1;
    if ($a_is_upcoming && $b_is_upcoming) return 0; // keep relative order for upcoming

    // Rule 2: Sort others by numeric year (newest -> oldest)
    $year_a = (is_numeric($a['year'] ?? null)) ? (int)$a['year'] : null;
    $year_b = (is_numeric($b['year'] ?? null)) ? (int)$b['year'] : null;

    if ($year_a !== null && $year_b === null) return -1;
    if ($year_a === null && $year_b !== null) return 1;
    if ($year_a !== null && $year_b !== null) {
        if ($year_a !== $year_b) return $year_b <=> $year_a;
    }

    // Deterministic fallback: compare titles
    $ta = strtolower($a['title'] ?? '');
    $tb = strtolower($b['title'] ?? '');
    if ($ta < $tb) return -1;
    if ($ta > $tb) return 1;
    return 0;
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
    <style>
        /* Date Range Slider Styles */
        .filter-section.date-range {
            margin: 15px 0;
        }
        .date-range-container {
            padding: 0 10px;
        }
        .date-range-slider {
            margin: 15px 0 5px;
            height: 4px;
            background: #e0e0e0;
            position: relative;
            border-radius: 2px;
        }
        .date-range-track {
            position: absolute;
            height: 100%;
            background: #4a90e2;
            border-radius: 2px;
        }
        .date-range-handle {
            width: 16px;
            height: 16px;
            background: #fff;
            border: 2px solid #4a90e2;
            border-radius: 50%;
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            z-index: 2;
        }
        .date-range-handle:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(74, 144, 226, 0.3);
        }
        .date-range-labels {
            display: flex;
            justify-content: space-between;
            margin-top: 8px;
            font-size: 13px;
            color: #666;
        }
        .date-range-inputs {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        .date-range-inputs input {
            width: 80px;
            padding: 5px 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 13px;
        }
    </style>
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
                <!-- 1. Genre -->
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

                <!-- 2. Type -->
                <div class="filter-section">
                    <label for="type">Type</label>
                    <select name="type" id="type">
                        <option value="">All Types</option>
                        <?php
                        $types = [
                            'tv' => 'TV Series',
                            'movie' => 'Movie',
                            'ova' => 'OVA',
                            'ona' => 'ONA',
                            'special' => 'Special',
                            'music' => 'Music'
                        ];
                        foreach ($types as $value => $label) {
                            $selected = isset($_GET['type']) && $_GET['type'] === $value ? 'selected' : '';
                            echo "<option value='{$value}' {$selected}>{$label}</option>";
                        }
                        ?>
                    </select>
                </div>

                <!-- 3. Status -->
                <div class="filter-section">
                    <label for="status">Status</label>
                    <select name="status" id="status">
                        <option value="">All Statuses</option>
                        <?php
                        $statuses = [
                            'airing' => 'Currently Airing',
                            'complete' => 'Finished Airing',
                            'upcoming' => 'Not Yet Aired'
                        ];
                        foreach ($statuses as $value => $label) {
                            $selected = isset($_GET['status']) && $_GET['status'] === $value ? 'selected' : '';
                            echo "<option value='{$value}' {$selected}>{$label}</option>";
                        }
                        ?>
                    </select>
                </div>

                <!-- 4. Sort By -->
                <div class="filter-section">
                    <label for="sort">Sort By</label>
                    <select name="sort" id="sort">
                        <?php
                        $sort_options = [
                            'start_date_desc' => 'Newest First',
                            'start_date_asc' => 'Oldest First',
                            'score_desc' => 'Highest Rated',
                            'score_asc' => 'Lowest Rated'
                        ];
                        $current_sort = $_GET['sort'] ?? 'start_date_desc';
                        foreach ($sort_options as $value => $label) {
                            $selected = $current_sort === $value ? 'selected' : '';
                            echo "<option value='{$value}' {$selected}>{$label}</option>";
                        }
                        ?>
                    </select>
                </div>

                <!-- 5. Release Period -->
                <div class="filter-section date-range">
                    <label>Release Year</label>
                    <div class="date-range-container">
                        <div class="date-range-slider" id="year-slider">
                            <div class="date-range-track" id="year-track"></div>
                            <div class="date-range-handle" id="year-handle-min"></div>
                            <div class="date-range-handle" id="year-handle-max"></div>
                        </div>
                        <div class="date-range-labels">
                            <span id="year-min-label">1900</span>
                            <span id="year-max-label"><?= date('Y') + 1 ?></span>
                        </div>
                        <div class="date-range-inputs">
                            <input type="number" id="min-year" name="min_year" min="1900" max="<?= date('Y') + 1 ?>" value="<?= $_GET['min_year'] ?? '1900' ?>" placeholder="From">
                            <input type="number" id="max-year" name="max_year" min="1900" max="<?= date('Y') + 1 ?>" value="<?= $_GET['max_year'] ?? (date('Y') + 1) ?>" placeholder="To">
                        </div>
                    </div>
                </div>

                <!-- 6. Age Rating -->
                <div class="filter-section">
                    <label for="rating">Age Rating</label>
                    <select name="rating" id="rating">
                        <option value="">Any Rating</option>
                        <?php
                        $ratings = [
                            'g' => 'G - All Ages',
                            'pg' => 'PG - Children',
                            'pg13' => 'PG-13 - Teens 13 or older',
                            'r17' => 'R - 17+ (violence & profanity)',
                            'r' => 'R+ - Mild Nudity',
                            'rx' => 'Rx - Hentai'
                        ];
                        foreach ($ratings as $value => $label) {
                            $selected = isset($_GET['rating']) && $_GET['rating'] === $value ? 'selected' : '';
                            echo "<option value='{$value}' {$selected}>{$label}</option>";
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
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const minYear = <?= (int)($_GET['min_year'] ?? 1900) ?>;
            const maxYear = <?= (int)($_GET['max_year'] ?? (date('Y') + 1)) ?>;
            const currentYear = new Date().getFullYear();
            const min = 1900;
            const max = currentYear + 1; // Include next year for upcoming anime
            
            const slider = document.getElementById('year-slider');
            const track = document.getElementById('year-track');
            const handleMin = document.getElementById('year-handle-min');
            const handleMax = document.getElementById('year-handle-max');
            const yearMinLabel = document.getElementById('year-min-label');
            const yearMaxLabel = document.getElementById('year-max-label');
            const inputMin = document.getElementById('min-year');
            const inputMax = document.getElementById('max-year');
            
            let isDraggingMin = false;
            let isDraggingMax = false;
            
            // Initialize positions
            updateSlider();
            
            // Event listeners for handles
            handleMin.addEventListener('mousedown', () => isDraggingMin = true);
            handleMax.addEventListener('mousedown', () => isDraggingMax = true);
            
            // Mouse move on document to handle dragging outside the slider
            document.addEventListener('mousemove', (e) => {
                if (!isDraggingMin && !isDraggingMax) return;
                
                const rect = slider.getBoundingClientRect();
                let position = (e.clientX - rect.left) / rect.width;
                position = Math.max(0, Math.min(1, position)); // Clamp between 0 and 1
                
                if (isDraggingMin) {
                    const newMin = Math.round(min + position * (max - min));
                    if (newMin < maxYear) {
                        updateYear('min', newMin);
                    }
                } else if (isDraggingMax) {
                    const newMax = Math.round(min + position * (max - min));
                    if (newMax > minYear) {
                        updateYear('max', newMax);
                    }
                }
                
                updateSlider();
            });
            
            // Stop dragging on mouse up
            document.addEventListener('mouseup', () => {
                isDraggingMin = false;
                isDraggingMax = false;
            });
            
            // Input field changes
            inputMin.addEventListener('change', (e) => {
                let value = parseInt(e.target.value);
                if (isNaN(value)) value = min;
                value = Math.max(min, Math.min(maxYear - 1, value));
                updateYear('min', value);
                updateSlider();
            });
            
            inputMax.addEventListener('change', (e) => {
                let value = parseInt(e.target.value);
                if (isNaN(value)) value = max;
                value = Math.min(max, Math.max(minYear + 1, value));
                updateYear('max', value);
                updateSlider();
            });
            
            function updateYear(type, value) {
                if (type === 'min') {
                    inputMin.value = value;
                    yearMinLabel.textContent = value;
                } else {
                    inputMax.value = value;
                    yearMaxLabel.textContent = value;
                }
            }
            
            function updateSlider() {
                const minYear = parseInt(inputMin.value);
                const maxYear = parseInt(inputMax.value);
                
                const minPos = ((minYear - min) / (max - min)) * 100;
                const maxPos = ((maxYear - min) / (max - min)) * 100;
                
                track.style.left = minPos + '%';
                track.style.width = (maxPos - minPos) + '%';
                
                handleMin.style.left = `calc(${minPos}% - 8px)`;
                handleMax.style.left = `calc(${maxPos}% - 8px)`;
            }
            
            // Prevent form submission on Enter key in input fields
            const form = document.querySelector('.filter-form');
            if (form) {
                form.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' && (e.target === inputMin || e.target === inputMax)) {
                        e.preventDefault();
                        this.submit();
                    }
                });
            }
        });
    </script>
    <script src="script.js"></script>
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