<?php
session_start();
require_once 'config.php';
require_once 'lib/database.php';

$db = new Database();
$error = '';

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error = 'Wszystkie pola są wymagane.';
    } else {
        $user = $db->getRow('users', '*', ['username' => $username]);
        if ($user && password_verify($password, $user['password_hash'])) {
            if ($user['email_verified'] == 1) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                header('Location: index.php');
                exit;
            } else {
                $error = 'Twój email nie został jeszcze zweryfikowany. Sprawdź swoją skrzynkę pocztową.';
            }
        } else {
            $error = 'Nieprawidłowa nazwa użytkownika lub hasło.';
        }
    }
}

include('header.php');
?>

<style>
    body::before {
      content: "";
      position: fixed;
      inset: 0;
      background: url('https://i0.wp.com/2gdesignandbuild.com/wp-content/uploads/2020/11/Web_Carters_of_Moseley_photo_by_Tom_Bird-5.jpg?fit=2000%2C1333&ssl=1') center/cover no-repeat;
      filter: blur(8px) brightness(0.4) hue-rotate(200deg);
      z-index: -1;
    }
    
    .glass-container {
        min-height: 80vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 2rem 0;
    }

    .glass-card {
        background: rgba(30, 41, 59, 0.4);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid rgba(255, 255, 255, 0.08);
        box-shadow: 0 15px 35px 0 rgba(0, 0, 0, 0.4), inset 0 1px 0 rgba(255,255,255,0.1);
        border-radius: 1.5rem;
        padding: 3rem;
        width: 100%;
        max-width: 420px;
        color: #e2e8f0;
        animation: fadeIn 0.5s ease-out;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .glass-card h3 {
        font-weight: 700;
        letter-spacing: 0.5px;
        margin-bottom: 2rem;
        text-align: center;
        background: linear-gradient(to right, #60a5fa, #a78bfa);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        font-size: 2rem;
    }

    .form-label {
        font-size: 0.9rem;
        font-weight: 500;
        color: #94a3b8;
        margin-bottom: 0.5rem;
        margin-left: 0.25rem;
    }

    .glass-input {
        background: rgba(15, 23, 42, 0.6) !important;
        border: 1px solid rgba(255, 255, 255, 0.1) !important;
        color: #f1f5f9 !important;
        border-radius: 0.75rem !important;
        padding: 0.85rem 1.25rem;
        transition: all 0.3s ease;
        font-size: 1rem;
    }

    .glass-input:focus {
        border-color: #3b82f6 !important;
        background: rgba(15, 23, 42, 0.8) !important;
        box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.15) !important;
    }

    .glass-btn {
        background: linear-gradient(135deg, #3b82f6, #2563eb);
        border: none;
        color: white;
        font-weight: 600;
        letter-spacing: 0.5px;
        padding: 0.85rem;
        border-radius: 0.75rem;
        width: 100%;
        margin-top: 1rem;
        transition: all 0.3s ease;
        font-size: 1.05rem;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .glass-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(59, 130, 246, 0.4);
        color: white;
    }
    
    .glass-footer {
        margin-top: 2rem;
        text-align: center;
        font-size: 0.95rem;
        color: #94a3b8;
    }
    
    .glass-footer a {
        color: #60a5fa;
        text-decoration: none;
        font-weight: 600;
        transition: color 0.2s, text-shadow 0.2s;
    }
    
    .glass-footer a:hover {
        color: #93c5fd;
        text-shadow: 0 0 8px rgba(147, 197, 253, 0.4);
    }

    .custom-alert {
        background: rgba(239, 68, 68, 0.15); 
        border: 1px solid rgba(239, 68, 68, 0.3); 
        color: #fca5a5; 
        border-radius: 0.75rem;
        padding: 1rem;
        margin-bottom: 1.5rem;
        font-size: 0.9rem;
    }

    .glass-input-group {
        position: relative;
        display: flex;
        flex-wrap: stretch;
        width: 100%;
    }
    
    .glass-toggle-btn {
        position: absolute;
        right: 5px;
        top: 50%;
        transform: translateY(-50%);
        background: transparent;
        border: none;
        color: #94a3b8;
        z-index: 10;
        padding: 0.5rem 0.75rem;
        transition: color 0.2s;
    }
    
    .glass-toggle-btn:hover, .glass-toggle-btn:active {
        color: #e2e8f0;
        background: transparent;
    }
</style>

<div class="glass-container">
    <div class="glass-card">
        <h3>Witaj ponownie</h3>
        
        <?php if ($error): ?>
            <div class="custom-alert d-flex align-items-center">
                <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i> 
                <div><?= $error ?></div>
            </div>
        <?php endif; ?>
        
        <form method="post">
            <div class="mb-4">
                <label for="username" class="form-label">Nazwa użytkownika</label>
                <input type="text" class="form-control glass-input" id="username" name="username" required placeholder="Twój login">
            </div>
            <div class="mb-4">
                <label for="password" class="form-label">Hasło</label>
                <div class="glass-input-group">
                    <input type="password" class="form-control glass-input w-100 pr-5" id="password" name="password" required placeholder="••••••••">
                    <button class="btn glass-toggle-btn" type="button" id="togglePassword">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
            </div>
            <button type="submit" class="glass-btn">
                <i class="bi bi-box-arrow-in-right me-2"></i> Zaloguj się
            </button>
        </form>
        
        <div class="glass-footer">
            Nie masz jeszcze konta? <br>
            <a href="register.php" class="mt-2 d-inline-block">Zarejestruj się teraz</a>
        </div>
    </div>
</div>

<script>
document.getElementById('togglePassword').addEventListener('click', function() {
    const passwordInput = document.getElementById('password');
    const toggleButton = this.querySelector('i');
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        toggleButton.classList.remove('bi-eye');
        toggleButton.classList.add('bi-eye-slash');
    } else {
        passwordInput.type = 'password';
        toggleButton.classList.remove('bi-eye-slash');
        toggleButton.classList.add('bi-eye');
    }
});
</script>

<?php include('footer.php'); ?>
