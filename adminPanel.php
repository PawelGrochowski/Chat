<?php
ob_start();
require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'lib/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    if ($_POST['username'] === 'PawelGr' && $_POST['password'] === 'Pawel123!') {
        $_SESSION['is_admin'] = true;
        header('Location: adminPanel.php');
        exit;
    } else {
        $error = 'Nieprawidłowe dane logowania.';
    }
}

if (isset($_GET['logout'])) {
    unset($_SESSION['is_admin']);
    header('Location: adminPanel.php');
    exit;
}

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    include('header.php');
?>
    <style>
        body::before {
            content: ""; position: fixed; inset: 0;
            background: url('https://i0.wp.com/2gdesignandbuild.com/wp-content/uploads/2020/11/Web_Carters_of_Moseley_photo_by_Tom_Bird-5.jpg?fit=2000%2C1333&ssl=1') center/cover no-repeat;
            filter: blur(8px) brightness(0.4) hue-rotate(200deg); z-index: -1;
        }
        .glass-container { min-height: 80vh; display: flex; align-items: center; justify-content: center; }
        .glass-card {
            background: rgba(30, 41, 59, 0.4); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.08);
            box-shadow: 0 15px 35px 0 rgba(0, 0, 0, 0.4); border-radius: 1.5rem; padding: 3rem; width: 100%; max-width: 420px; color: #e2e8f0;
        }
        .glass-card h3 { text-align: center; color: #f87171; font-weight: 700; margin-bottom: 1.5rem; }
        .glass-input { background: rgba(15, 23, 42, 0.6); border: 1px solid rgba(255, 255, 255, 0.1); color: #f1f5f9; border-radius: 0.75rem; padding: 0.85rem; width: 100%; }
        .glass-btn { background: linear-gradient(135deg, #ef4444, #b91c1c); border: none; color: white; padding: 0.85rem; border-radius: 0.75rem; width: 100%; margin-top: 1rem; font-weight: bold; }
    </style>
    <div class="glass-container">
        <div class="glass-card">
            <h3>Tylko dla personelu</h3>
            <?php if ($error): ?><div class="alert alert-danger" style="background: rgba(239, 68, 68, 0.2); color: #fca5a5; border: none;"><?=$error?></div><?php endif; ?>
            <form method="post">
                <div class="mb-3"><label>Admin</label><input type="text" name="username" class="glass-input" required></div>
                <div class="mb-4"><label>Hasło</label><input type="password" name="password" class="glass-input" required></div>
                <button type="submit" name="login" class="glass-btn">Uzyskaj dostęp</button>
            </form>
        </div>
    </div>
<?php
    include('footer.php');
    exit;
}

$db = new Database();
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

$check_column = mysqli_query($conn, "SHOW COLUMNS FROM `users` LIKE 'is_allowed'");
if (mysqli_num_rows($check_column) == 0) {
    mysqli_query($conn, "ALTER TABLE `users` ADD COLUMN `is_allowed` TINYINT(1) DEFAULT 0");
}

if (!file_exists('settings.json')) {
    file_put_contents('settings.json', json_encode(['guest_access' => 1]));
}
$settings = json_decode(file_get_contents('settings.json'), true);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'toggle_guest') {
        $settings['guest_access'] = isset($_POST['guest_access']) ? 1 : 0;
        file_put_contents('settings.json', json_encode($settings));
    }
    if ($_POST['action'] === 'toggle_user') {
        $user_id = intval($_POST['user_id']);
        $status = intval($_POST['status']);
        $new_status = ($status === 1) ? 0 : 1;
        $db->updateRows('users', ['is_allowed' => $new_status], ['id' => $user_id]);
    }
    header('Location: adminPanel.php');
    exit;
}

$users = $db->getRows('users', ['id', 'username', 'email', 'is_allowed']);

include('header.php');
?>
<style>
    body { background-color: #0f172a; color: #e2e8f0; font-family: 'Inter', sans-serif; }
    .admin-container { max-width: 900px; margin: 2rem auto; background: #1e293b; padding: 2rem; border-radius: 15px; box-shadow: 0 10px 25px rgba(0,0,0,0.5); }
    h2 { color: #f87171; border-bottom: 2px solid #334155; padding-bottom: 1rem; margin-bottom: 2rem; font-family: 'Audiowide', cursive; }
    .table { color: #cbd5e1; margin-top: 1rem; }
    .table th { border-bottom: 2px solid #334155; color: #94a3b8; text-transform: uppercase; font-size: 0.85rem; }
    .table td { border-bottom: 1px solid #334155; vertical-align: middle; }
    
    .switch { position: relative; display: inline-block; width: 50px; height: 26px; }
    .switch input { opacity: 0; width: 0; height: 0; }
    .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #475569; transition: .3s; border-radius: 34px; }
    .slider:before { position: absolute; content: ""; height: 20px; width: 20px; left: 3px; bottom: 3px; background-color: white; transition: .3s; border-radius: 50%; }
    input:checked + .slider { background-color: #10b981; }
    input:focus + .slider { box-shadow: 0 0 1px #10b981; }
    input:checked + .slider:before { transform: translateX(24px); }
    
    .panel-section { background: rgba(15, 23, 42, 0.4); padding: 1.5rem; border-radius: 10px; margin-bottom: 2rem; border: 1px solid #334155; }
    .badge-allowed { background: rgba(16, 185, 129, 0.2); color: #34d399; padding: 0.3rem 0.6rem; border-radius: 5px; font-size: 0.8rem; }
    .badge-denied { background: rgba(239, 68, 68, 0.2); color: #f87171; padding: 0.3rem 0.6rem; border-radius: 5px; font-size: 0.8rem; }
</style>

<div class="container admin-container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-shield-lock-fill me-2"></i> Panel Administracyjny</h2>
        <a href="?logout=1" class="btn btn-outline-danger btn-sm">Wyloguj sesję</a>
    </div>

    <div class="panel-section d-flex justify-content-between align-items-center">
        <div>
            <h5 class="mb-1 text-primary">Dostęp dla "Gościa"</h5>
            <small class="text-secondary">Czy pozwolić na generowanie treści przez niezalogowanych użytkowników?</small>
        </div>
        <form method="post" class="m-0">
            <input type="hidden" name="action" value="toggle_guest">
            <label class="switch" title="Przełącz blokadę gościa">
                <input type="checkbox" name="guest_access" onchange="this.form.submit()" <?= $settings['guest_access'] ? 'checked' : '' ?>>
                <span class="slider"></span>
            </label>
        </form>
    </div>

    <div class="panel-section">
        <h5 class="mb-3 text-primary">Zarejestrowani użytkownicy</h5>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Login</th>
                        <th>Status uprawnień</th>
                        <th class="text-end">Dostęp</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr><td colspan="4" class="text-center py-4">Brak zarejestrowanych kont poza adminem</td></tr>
                    <?php else: ?>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td>#<?= $u['id'] ?></td>
                                <td><span class="fw-bold"><?= htmlspecialchars($u['username']) ?></span></td>
                                <td>
                                    <?php if ($u['is_allowed'] == 1): ?>
                                        <span class="badge-allowed"><i class="bi bi-check-circle-fill"></i> Dostęp nadany</span>
                                    <?php else: ?>
                                        <span class="badge-denied"><i class="bi bi-x-circle-fill"></i> Zablokowany</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <form method="post" class="m-0 d-inline-block">
                                        <input type="hidden" name="action" value="toggle_user">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <input type="hidden" name="status" value="<?= $u['is_allowed'] ?>">
                                        <label class="switch">
                                            <input type="checkbox" onchange="this.form.submit()" <?= $u['is_allowed'] == 1 ? 'checked' : '' ?>>
                                            <span class="slider"></span>
                                        </label>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
