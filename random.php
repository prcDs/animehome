<?php
// random.php - Обробник для випадкового аніме

function getRandomAnime() {
    // Генеруємо випадкову сторінку з топ аніме (1-100)
    $random_page = rand(1, 100);
    $url = "https://api.jikan.moe/v4/top/anime?page=" . $random_page . "&limit=25";
    
    // Спробуємо кілька разів, якщо API не відповідає
    $max_attempts = 3;
    $attempt = 0;
    
    while ($attempt < $max_attempts) {
        $json = @file_get_contents($url);
        
        if ($json !== false) {
            $data = json_decode($json, true);
            
            if (isset($data['data']) && !empty($data['data'])) {
                // Вибираємо випадкове аніме з отриманого списку
                $random_anime = $data['data'][array_rand($data['data'])];
                return $random_anime['mal_id'];
            }
        }
        
        $attempt++;
        // Якщо перша спроба не вдалася, пробуємо іншу сторінку
        $random_page = rand(1, 50);
        $url = "https://api.jikan.moe/v4/top/anime?page=" . $random_page . "&limit=25";
    }
    
    // Якщо все не вдалося, повертаємо популярне аніме (ID 1)
    return 1; // "Cowboy Bebop" - завжди доступне
}

// Якщо файл викликається напряму
if (basename($_SERVER['PHP_SELF']) == 'random.php') {
    $random_id = getRandomAnime();
    
    if ($random_id) {
        // Перенаправляємо на сторінку перегляду
        header('Location: player.php?id=' . $random_id);
        exit;
    } else {
        // Якщо щось пішло не так, повертаємося на головну
        header('Location: releases.php');
        exit;
    }
}
?>