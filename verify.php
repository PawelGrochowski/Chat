<?php
require_once 'config.php';
require_once 'lib/database.php';
include('header.php');

$db = new Database();
$message = '';
$error = '';

if (isset($_GET['token'])) {
    $token = $_GET['token'];
    
    
    $user = $db->getRow('users', '*', ['verification_token' => $token]);
    
    if (!$user) {
        $error = 'Nieprawidłowy lub wygasły token weryfikacyjny.';
    } elseif ($user['verification_token_expires'] < date('Y-m-d H:i:s')) {
        $error = 'Token weryfikacyjny wygasł. Prosimy o ponowną rejestrację.';
    } elseif ($user['email_verified'] == 1) {
        $message = 'Twój email został już zweryfikowany. Możesz się zalogować.';
    } else {
        
        $db->updateRows('users', [
            'email_verified' => 1,
            'verification_token' => null,
            'verification_token_expires' => null
        ], ['id' => $user['id']]);
        
        $message = 'Gratulacje! Twój email został zweryfikowany. Możesz się teraz zalogować.';
    }
} else {
    $error = 'Brak tokena weryfikacyjnego.';
}
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card bg-dark text-white mt-5">
            <div class="card-header">
                <h3>Weryfikacja Emaila</h3>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= $error ?></div>
                    <p><a href="register.php" class="btn btn-primary">Wróć do rejestracji</a></p>
                <?php else: ?>
                    <div class="alert alert-success"><?= $message ?></div>
                    <p><a href="login.php" class="btn btn-primary">Przejdź do logowania</a></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

