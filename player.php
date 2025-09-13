<?php
session_start();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die('Invalid anime ID.');
}
else {
    $anime_id = (int)$_GET['id'];
    $url = "https://api.jikan.moe/v4/anime/{$anime_id}";
    $in_json = file_get_contents($url);
    $data = json_decode($in_json, true);
    if (isset($data['data'])) {
        $anime = $data['data'];
    } else {
        $anime = null;
    }
}    

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
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>AnimHome - <?= $anime ? htmlspecialchars($anime['title']) : 'Anime not found' ?></title>
  <link rel="stylesheet" href="css/style_p.css">
  <link rel="stylesheet" href="css/style_common.css">
  <link rel="stylesheet" href="https://cdn.plyr.io/3.7.8/plyr.css" />
</head>

<body>
  <!-- Top Banner -->
  <div class="top-banner">
      <img src="images/Реклама1.png" alt="Advertisement">
  </div>

  <!-- Main Content -->
  <div class="main-layout">
      <!-- Left Side -->
      <div class="left-side">
          <!-- Navigation -->
          <div class="navigation">
              <nav>
                  <ul>
                      <li><a href="index.php">HOME</a></li>
                      <li><a href="releases.php">RELEASES</a></li>
                      <li><a href="random.php">RANDOM</a></li>
                      <li><a href="#">Support the project</a></li>
                      <li><a href="Pidpro.html">Help Ukraine</a></li>
                  </ul>
              </nav>
          </div>

          <!-- Anime Information -->
          <?php if ($anime): ?>
          <article class="anime-layout">
              <section class="anime-top">
                  <div class="anime-image">
                      <img src="<?= $anime['images']['jpg']['image_url'] ?>" alt="poster">
                  </div>
                  <div class="anime-info">
                      <h2><?= htmlspecialchars(!empty($anime['title_english']) ? $anime['title_english'] : ($anime['title'] ?? '')) ?></h2>
                      <p><?= htmlspecialchars($anime['title'] ?? '') ?></p>
                      <hr />
                      <p><strong>Type:</strong> <?= htmlspecialchars($anime['type'] ?? 'N/A') ?></p>
                      <p><strong>Episodes:</strong> <?= htmlspecialchars($anime['episodes'] ?? 'N/A') ?></p>
                      <p><strong>Genres:</strong> 
                          <?php 
                              if (!empty($anime['genres'])) {
                                  $genre_names = array_map(function($genre) {
                                      return htmlspecialchars($genre['name']);
                                  }, $anime['genres']);
                                  echo implode(', ', $genre_names);
                              } else {
                                  echo 'N/A';
                              }
                          ?>
                      </p>
                      <p><strong>Year:</strong> <?= htmlspecialchars($anime['year'] ?? 'N/A') ?></p>
                      <p><strong>Duration:</strong> <?= htmlspecialchars($anime['duration'] ?? 'N/A') ?></p>
                      <p><strong>Score:</strong> <?= htmlspecialchars($anime['score'] ?? 'N/A') ?></p>
                      <p><strong>Status:</strong> <?= htmlspecialchars($anime['status'] ?? 'N/A') ?></p>
                      <div class="anime-description">
                          <p><strong>Synopsis:</strong></p>
                          <p><?= nl2br(htmlspecialchars($anime['synopsis'] ?? 'Synopsis not found')) ?></p>
                      </div>
                  </div>
              </section>

              <section class="player-and-ad">
                  <div class="anime-player">
                      <?php if (!empty($anime['trailer']['embed_url'])): ?>
                          <h3>Trailer</h3>
                          <iframe src="<?= htmlspecialchars($anime['trailer']['embed_url']) ?>" 
                                  allowfullscreen 
                                  style="width: 100%; height: 400px; border-radius: 10px; border: none;">
                          </iframe>
                      <?php endif; ?>
                      
                      <h3 style="margin-top: 20px;">Video Player</h3>
                      <video id="player" controls poster="<?= $anime['images']['jpg']['image_url'] ?>" 
                             style="width: 100%; max-width: 800px; border-radius: 10px;">
                          <source src="#" type="video/mp4">
                          <p>Your browser does not support HTML5 video.</p>
                      </video>
                      <p style="margin-top: 10px; color: #888; font-size: 14px;">
                          * Video content will be added later
                      </p>
                  </div>
                  
                  <aside class="side-ad" aria-label="Advertisement">
                      <img src="images/Реклама3.png" alt="Advertisement" />
                  </aside>
              </section>
          </article>
          <?php else: ?>
          <div style="padding: 40px; text-align: center;">
              <h2>Anime not found</h2>
              <p>Unfortunately, the requested anime was not found.</p>
              <a href="releases.php" style="color: #007bff;">← Back to list</a>
          </div>
          <?php endif; ?>
      </div>

      <!-- Right Side: Recommendations -->
      <div class="recommendations-box">
          <div class="recommendations-header">
              <h3>Similar Anime</h3>
          </div>
          
          <div class="recommendations-list">
              <?php
              if (isset($anime['mal_id'])) {
                  // Get anime by similar genres and type
                  $genre_ids = array_map(function($genre) {
                      return $genre['mal_id'];
                  }, $anime['genres']);
                  
                  $genre_param = implode(',', $genre_ids);
                  $similar_url = "https://api.jikan.moe/v4/anime?genres={$genre_param}&type={$anime['type']}&limit=5";
                  $similar_json = @file_get_contents($similar_url);
                  $similar_data = json_decode($similar_json, true);
                  
                  if (isset($similar_data['data']) && !empty($similar_data['data'])) {
                      foreach ($similar_data['data'] as $rec) {
                          if ($rec['mal_id'] !== $anime['mal_id']) {  // Don't show the current anime
                              echo '<div class="recommendation-item">';
                              echo '<a href="player.php?id=' . $rec['mal_id'] . '">';
                              echo '<img src="' . $rec['images']['jpg']['image_url'] . '" alt="' . htmlspecialchars($rec['title']) . '">';
                              echo '<h4>' . htmlspecialchars($rec['title']) . '</h4>';
                              echo '</a>';
                              echo '</div>';
                          }
                      }
                  } else {
                      echo '<p>No recommendations found</p>';
                  }
              }
              ?>
          </div>
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
      
      // Adding animation for navigation buttons
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
      
      // Adding loading effect for all anime links
      document.querySelectorAll('.recommendation-item a').forEach(link => {
          link.addEventListener('click', function(e) {
              this.classList.add('loading-active');
              const title = this.querySelector('h4');
              if (title) {
                  title.innerHTML = '<span class="loading">LOADING</span>';
              }
          });
      });
  </script>

  <script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script>
  <script src="https://cdn.plyr.io/3.7.8/plyr.polyfilled.js"></script>
</body>
</html>