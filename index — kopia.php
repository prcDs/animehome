<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AnimHome - Watch anime like a god</title>
    <link rel="stylesheet" href="css/style_p.css">
</head>
<body>
    <div class="top-banner">
        <img src="images/Реклама1.png" alt="Advertisement">
    </div>

    <div class="main-layout">
        <div class="left-side">
            <div class="navigation">
                <nav>
                    <ul>
                        <li><a href="index.php">HOME</a></li>
                        <li><a href="releases.php">RELEASES</a></li>
                        <li><a href="releases.php?random=1">RANDOM</a></li>
                        <li><a href="#">Support the project</a></li>
                        <li><a href="#">Help Ukraine</a></li>
                    </ul>
                </nav>
            </div>

            <div class="middle-banner">
                <img src="images/Реклама2.png" alt="Advertisement 2">
            </div>

            <div class="card-grid">
                <!-- cards -->
                <div class="card"><img src="images/Спонсори.png"><h3>Sponsor 1</h3></div>
                <div class="card"><img src="images/Спонсори.png"><h3>Sponsor 2</h3></div>
                <div class="card"><img src="images/Спонсори.png"><h3>Sponsor 3</h3></div>
                <div class="card"><img src="images/Спонсори.png"><h3>Sponsor 4</h3></div>
                <div class="card"><img src="images/Спонсори.png"><h3>Sponsor 5</h3></div>
                <div class="card"><img src="images/Спонсори.png"><h3>Sponsor 6</h3></div>
            </div>
        </div>

        <div class="login-box">
            <div class="login-container">
                <div class="auth-tabs">
                    <button type="button" class="tab-button active" data-form="login">Login</button>
                    <button type="button" class="tab-button" data-form="register">Register</button>
                </div>

                <!-- Login Form -->
                <form id="loginForm" class="auth-form active" action="auth/login.php" method="POST">
                    <div class="form-group">
                        <input type="email" name="email" placeholder="Email" required>
                    </div>
                    <div class="form-group">
                        <input type="password" name="password" placeholder="Password" required>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="auth-button">Login</button>
                    </div>
                    <div class="form-links">
                        <a href="#" class="forgot-password">Forgot password?</a>
                    </div>
                </form>

                <!-- Registration Form -->
                <form id="registerForm" class="auth-form" action="auth/register.php" method="POST" style="display: none;">
                    <div class="form-group">
                        <input type="text" name="username" placeholder="Username" required>
                    </div>
                    <div class="form-group">
                        <input type="email" name="email" placeholder="Email" required>
                    </div>
                    <div class="form-group">
                        <input type="password" name="password" placeholder="Password" required minlength="6">
                    </div>
                    <div class="form-group">
                        <input type="password" name="confirm_password" placeholder="Confirm password" required>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="auth-button">Register</button>
                    </div>
                    <div class="form-info">
                        <small>* A confirmation email will be sent to the specified email address</small>
                    </div>
                </form>
            </div>
        </div>

        <script>
            document.querySelectorAll('.tab-button').forEach(button => {
                button.addEventListener('click', () => {
                    // Remove active class from all buttons
                    document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
                    // Add active class to the clicked button
                    button.classList.add('active');
                    
                    // Hide all forms
                    document.querySelectorAll('.auth-form').forEach(form => form.style.display = 'none');
                    // Show the correct form
                    const formToShow = button.dataset.form === 'login' ? 'loginForm' : 'registerForm';
                    document.getElementById(formToShow).style.display = 'block';
                });
            });
        </script>
    </div>

    <footer>
    <div class="footer-block">
        <img src="images/Реклама3.png" alt="Advertisement 3">
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
        // Add animation for navigation buttons
        document.querySelectorAll('.navigation nav a').forEach(link => {
            link.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-2px)';
            });
            
            link.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });

            // Add loading effect on click
            if (!link.href.includes('#')) {  // Only for real links
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

        // Add loading effect for auth forms
        document.querySelectorAll('.auth-form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const button = this.querySelector('button[type="submit"]');
                if (button) {
                    button.innerHTML = '<span class="loading">LOADING</span>';
                    button.disabled = true;
                    button.classList.add('loading-active');
                }
            });
        });
    </script>
</body>
</html>