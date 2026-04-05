<?php
require_once 'config.php';
require_once 'lib/database.php';
require_once 'lib/SendEmail.php';
include('header.php');

$db = new Database();
$error = '';
$success = '';


function isValidEmailDomain($email) {
    
    $email_parts = explode('@', $email);
    if (count($email_parts) !== 2) {
        return false;
    }
    
    $domain = $email_parts[1];
    
    
    if (function_exists('getmxrr')) {
        $mxhosts = [];
        return @getmxrr($domain, $mxhosts) && !empty($mxhosts);
    } elseif (function_exists('checkdnsrr')) {
        
        return @checkdnsrr($domain, 'MX');
    }
    
    
    return true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($username) || empty($email) || empty($password)) {
        $error = 'Wszystkie pola są wymagane.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Wprowadzony email jest nieprawidłowy.';
    } elseif (!isValidEmailDomain($email)) {
        $error = 'Wprowadzony email nie ma prawidłowej domeny lub serwera pocztowego.';
    } elseif (strlen($password) < 6) {
        $error = 'Hasło musi mieć co najmniej 6 znaków.';
    } else {
        $existing_user = $db->getRow('users', 'id', ['username' => $username]);
        if ($existing_user) {
            $error = 'Nazwa użytkownika jest już zajęta.';
        } else {
            $existing_email = $db->getRow('users', 'id', ['email' => $email]);
            if ($existing_email) {
                $error = 'Adres email jest już używany.';
            } else {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $verification_token = bin2hex(random_bytes(32));
                $token_expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
                
                $db->insertRows('users', [
                    'username' => $username,
                    'email' => $email,
                    'password_hash' => $password_hash,
                    'email_verified' => 0,
                    'verification_token' => $verification_token,
                    'verification_token_expires' => $token_expires
                ]);
                
                
                $verify_link = "http://" . $_SERVER['HTTP_HOST'] . "/verify.php?token=" . $verification_token;
                $emailer = new SendEmail();
                $message = "
                    <p>Dziękujemy za rejestrację! Aby ukończyć proces rejestracji, naciśnij poniższy link:</p>
                    <a href='$verify_link' class='button'>Potwierdź email</a>
                    <p>Link będzie ważny przez 24 godziny.</p>
                ";
                
                $email_sent = $emailer->send($email, $username, 'Potwierdzenie emaila - Clanker CHAT', $message);
                
                if ($email_sent) {
                    $success = 'Rejestracja zakończona sukcesem! Sprawdź swoją skrzynkę pocztową i naciśnij link weryfikacyjny.';
                } else {
                    $success = 'Rejestracja zakończona, ale nie udało się wysłać emaila weryfikacyjnego. Skontaktuj się z administratorem.';
                }
            }
        }
    }
}
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
        max-width: 480px;
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
        margin-top: 1.5rem;
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

    .custom-alert-success {
        background: rgba(34, 197, 94, 0.15); 
        border: 1px solid rgba(34, 197, 94, 0.3); 
        color: #86efac; 
        border-radius: 0.75rem;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        font-size: 1rem;
        text-align: center;
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
        <h3>Utwórz konto</h3>
        
        <?php if ($error): ?>
            <div class="custom-alert d-flex align-items-center">
                <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i> 
                <div><?= $error ?></div>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="custom-alert-success">
                <i class="bi bi-check-circle-fill mb-2 d-block" style="font-size: 2.5rem;"></i>
                <div><?= $success ?></div>
            </div>
        <?php else: ?>
        <form method="post">
            <div class="mb-4">
                <label for="username" class="form-label">Nazwa użytkownika</label>
                <input type="text" class="form-control glass-input" id="username" name="username" required placeholder="Wymarzony login">
            </div>
            <div class="mb-4">
                <label for="email" class="form-label">Adres Email</label>
                <input type="email" class="form-control glass-input" id="email" name="email" required placeholder="jan@kowalski.pl">
            </div>
            <div class="mb-4">
                <label for="password" class="form-label">Hasło (min. 6 znaków)</label>
                <div class="glass-input-group">
                    <input type="password" class="form-control glass-input w-100 pr-5" id="password" name="password" required placeholder="••••••••">
                    <button class="btn glass-toggle-btn" type="button" id="togglePassword">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
            </div>
            <button type="submit" class="glass-btn">
                <i class="bi bi-person-plus-fill me-2"></i> Zarejestruj się
            </button>
        </form>
        <?php endif; ?>
        
        <div class="glass-footer">
            Masz już konto? <br>
            <a href="login.php" class="mt-2 d-inline-block">Przejdź do logowania</a>
        </div>
    </div>
</div>

<script>
document.getElementById('togglePassword')?.addEventListener('click', function() {
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
