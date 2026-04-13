<?php
require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'lib/database.php';
$db = new Database();
$chats = [];
if (isset($_SESSION['user_id'])) {
    $chats = $db->getRows('chats', ['id', 'title'], ['user_id' => $_SESSION['user_id']], 'AND', 'created_at DESC');
} else {
    if (!isset($_SESSION['guest_chats'])) {
        $_SESSION['guest_chats'] = [];
    }
    $chats = $_SESSION['guest_chats'];
    $chats = array_reverse($chats);
}
?>
<!doctype html>
<html lang="pl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AI Chatbot</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Nunito:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Pixelify+Sans:wght@400..700&family=Roboto+Slab:wght@100..900&display=swap" rel="stylesheet">
  <script>
    if (!sessionStorage.getItem('guest_session_active')) {
        sessionStorage.setItem('guest_session_active', '1');
        <?php if (!isset($_SESSION['user_id']) && !empty($_SESSION['guest_chats'])): ?>
        fetch('chat_api.php?action=clear_guest_session').then(() => {
            window.location.reload();
        });
        <?php endif; ?>
    }
    function deleteChat(event, chatId) {
        event.preventDefault();
        event.stopPropagation();
        if (confirm('Czy na pewno chcesz usunąć tę rozmowę z historii? Obrazki powiązane z nią pozostaną w Twojej bibliotece.')) {
            const formData = new FormData();
            formData.append('chat_id', chatId);
            fetch('chat_api.php?action=delete_chat', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const params = new URLSearchParams(window.location.search);
                    if (params.get('chat_id') == chatId) {
                        window.location.href = 'index.php'; 
                    } else {
                        window.location.reload(); 
                    }
                }
            })
            .catch(err => console.error(err));
        }
    }
  </script>
  <style>
    body {
      background-color: #0f172a;
      color: #e2e8f0;
      font-family: 'Inter', sans-serif !important;
    }
    h1, h2, h3, h4, h5, h6, .brand-font {
      font-family: 'Nunito', sans-serif !important;
    }
    .navbar {
      background-color: #1e293b !important;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
    }
    .navbar-nav {
      display: flex;
      align-items: center;
      font-family: 'Nunito', sans-serif !important;
      font-weight: 700;
      letter-spacing: 0.5px;
    }
    @import url('https://fonts.googleapis.com/css2?family=Audiowide&display=swap');
    
    .navbar-brand {
      transform: translateY(-1px);
      font-family: 'Audiowide', cursive !important;
      font-size: 1.4rem !important;
      letter-spacing: 1px;
    }

    .navbar-brand, .nav-link, .dropdown-toggle {
      color: #f1f5f9 !important;
      font-weight: 500;
    }
    
    .navbar-brand:hover, .nav-link:hover, .dropdown-item:hover {
      color: #3b82f6 !important;
    }
    .nav-item {
      margin-right: 15px;
    }
    .container {
      max-width: 90%;
    }
    .dropdown-menu {
      max-height: 300px;
      overflow-y: auto;
      background-color: #1e293b;
      border: 1px solid #334155;
      border-radius: 8px;
      box-shadow: 0 4px 15px rgba(0,0,0,0.5);
      padding: 8px 0;
    }
    .dropdown-menu::-webkit-scrollbar {
      width: 8px;
    }
    .dropdown-menu::-webkit-scrollbar-track {
      background: #0f172a; 
      border-radius: 0 8px 8px 0;
    }
    .dropdown-menu::-webkit-scrollbar-thumb {
      background-color: #475569; 
      border-radius: 4px;
    }
    .dropdown-menu::-webkit-scrollbar-thumb:hover {
      background-color: #3b82f6; 
    }
    .dropdown-item {
      padding: 8px 20px;
      color: #cbd5e1;
      transition: background-color 0.2s, color 0.2s;
    }
    .dropdown-item:hover, .dropdown-item:focus {
      background-color: rgba(59, 130, 246, 0.15); 
      color: #3b82f6 !important;
    }
    .delete-chat-btn:hover {
      color: #dc2626 !important; 
      transform: scale(1.1);
    }
  </style>
</head>
<body>
  <nav class="navbar navbar-expand-lg" data-bs-theme="dark">
    <div class="container-fluid px-4">
      <a class="navbar-brand" href="index.php"><i class="bi bi-house-door-fill me-2"></i>Clanker CHAT</a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
              aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navbarNav">
        <ul class="navbar-nav ms-auto">
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown"
               aria-expanded="false">
              <i class="bi bi-list-check me-1"></i>Historia rozmów
            </a>
            <ul class="dropdown-menu dropdown-menu-dark">
              <?php foreach ($chats as $chat) { ?>
                <li>
                  <div class="dropdown-item d-flex justify-content-between align-items-center" style="cursor: pointer;" onclick="window.location.href='index.php?chat_id=<?= $chat['id'] ?>'">
                    <span class="text-truncate" style="max-width: 85%;"><?= htmlspecialchars($chat['title'] ?? 'Chat #' . $chat['id']) ?></span>
                    <i class="bi bi-x-circle delete-chat-btn ms-2" data-chat-id="<?= $chat['id'] ?>" title="Usuń chat" onclick="event.stopPropagation(); deleteChat(event, <?= $chat['id'] ?>);" style="color: #f87171; transition: color 0.2s;"></i>
                  </div>
                </li>
              <?php } ?>
            </ul>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="gallery.php"><i class="bi bi-images me-1"></i>Biblioteka obrazków</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="index.php"><i class="bi bi-chat-dots me-1"></i>Nowa rozmowa</a>
          </li>
          <?php if (isset($_SESSION['user_id'])): ?>
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-person-circle me-1"></i>Witaj, <?= htmlspecialchars($_SESSION['username']) ?>
                </a>
                <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end">
                    <li>
                        <button class="dropdown-item" data-bs-toggle="modal" data-bs-target="#modelSelectorModal" style="border:none;background:none;cursor:pointer;text-align:left;width:100%;">
                            <i class="bi bi-gear me-2"></i>Opcje modeli
                        </button>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item" href="logout.php">
                            <i class="bi bi-box-arrow-right me-2"></i>Wyloguj
                        </a>
                    </li>
                </ul>
            </li>
          <?php else: ?>
            <li class="nav-item">
                <a class="nav-link" href="login.php"><i class="bi bi-box-arrow-in-right me-1"></i>Logowanie</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="register.php"><i class="bi bi-person-plus me-1"></i>Rejestracja</a>
            </li>
          <?php endif; ?>
        </ul>
      </div>
    </div>
  </nav>
  <div class="modal fade" id="modelSelectorModal" tabindex="-1" aria-labelledby="modelSelectorLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content" style="background-color: #1e293b; color: #e2e8f0; border: 1px solid #475569;">
        <div class="modal-header" style="border-bottom: 1px solid #475569;">
          <h5 class="modal-title" id="modelSelectorLabel"><i class="bi bi-gear me-2"></i>Wybór modeli AI</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label for="textModelSelect" class="form-label">Model do tekstów</label>
            <select class="form-select" id="textModelSelect" style="background-color: #334155; color: #e2e8f0; border: 1px solid #475569;">
              <option value="gpt-5.4-2026-03-05">GPT-5.4 (2026)</option>
              <option value="gpt-3.5-turbo">GPT-3.5 Turbo</option>
            </select>
          </div>
          <div class="mb-3">
            <label for="imageModelSelect" class="form-label">Model do obrazów</label>
            <select class="form-select" id="imageModelSelect" style="background-color: #334155; color: #e2e8f0; border: 1px solid #475569;">
              <option value="gpt-5.4-2026-03-05">GPT-5.4 (2026)</option>
              <option value="dall-e-3">DALL-E 3</option>
            </select>
          </div>
          <div class="mb-3">
            <label for="personalitySelect" class="form-label">Osobowość Bota</label>
            <select class="form-select" id="personalitySelect" style="background-color: #334155; color: #e2e8f0; border: 1px solid #475569;">
              <option value="default">Domyślny asystent</option>
              <option value="Brytyjczyk">Brytyjczyk</option>
              <option value="Amerykanin">Amerykanin</option>
              <option value="jaskier">Jaskier</option>
            </select>
          </div>
          <small class="text-muted">Wybrane modele będą używane do wszystkich nowych wiadomości.</small>
        </div>
        <div class="modal-footer" style="border-top: 1px solid #475569;">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Zamknij</button>
          <button type="button" class="btn btn-primary" id="saveModelsBtn">Zapisz</button>
        </div>
      </div>
    </div>
  </div>
  <div class="container mt-4">
