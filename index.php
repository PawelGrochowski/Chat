<?php
include('header.php');
$chat_title = 'Nowa rozmowa';
if (isset($_GET['chat_id']) && is_numeric($_GET['chat_id'])) {
    $current_chat_id = intval($_GET['chat_id']);
    $chat_info = $db->getRow('chats', ['title'], ['id' => $current_chat_id]);
    if ($chat_info && !empty($chat_info['title'])) {
        $chat_title = htmlspecialchars($chat_info['title']);
    }
}
?>
<link rel="stylesheet" href="css/chat-style.css">
<style>
    body::before {
      content: "";
      position: fixed;
      inset: 0;
      background: url('https://i0.wp.com/2gdesignandbuild.com/wp-content/uploads/2020/11/Web_Carters_of_Moseley_photo_by_Tom_Bird-5.jpg?fit=2000%2C1333&ssl=1') center/cover no-repeat;
      filter: blur(5px) brightness(0.6) hue-rotate(200deg);
      z-index: -1;
    }
    .header-content h2 {
        font-family: 'Orbitron', sans-serif !important;
        font-weight: 700;
        letter-spacing: 2px;
        text-transform: uppercase;
        color: #93c5fd;
        text-shadow: 0 0 10px rgba(147, 197, 253, 0.5);
    }
</style>
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@500;700&display=swap" rel="stylesheet">
<div class="custom-container">
    <div class="chat-header">
        <div class="header-content">
            <h2><?= $chat_title ?></h2>
        </div>
    </div>
    <div class="chat-box" id="chat-box">
    </div>
    <div class="chat-footer">
        <form id="message-form">
            <input type="text" id="message-input" placeholder="Wpisz wiadomość...">
            <button type="submit">Wyślij</button>
        </form>
    </div>
</div>
<script>
    const initialChatId = <?= json_encode($_GET['chat_id'] ?? null) ?>;
</script>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
<script src="js/chat.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const savedTextModel = sessionStorage.getItem('selectedTextModel') || 'gpt-5.4-2026-03-05';
    const savedImageModel = sessionStorage.getItem('selectedImageModel') || 'gpt-5.4-2026-03-05';
    const savedPersonality = sessionStorage.getItem('selectedPersonality') || 'default';
    document.getElementById('textModelSelect').value = savedTextModel;
    document.getElementById('imageModelSelect').value = savedImageModel;
    document.getElementById('personalitySelect').value = savedPersonality;
    document.getElementById('saveModelsBtn').addEventListener('click', function() {
        const textModel = document.getElementById('textModelSelect').value;
        const imageModel = document.getElementById('imageModelSelect').value;
        const personality = document.getElementById('personalitySelect').value;
        sessionStorage.setItem('selectedTextModel', textModel);
        sessionStorage.setItem('selectedImageModel', imageModel);
        sessionStorage.setItem('selectedPersonality', personality);
        fetch('chat_api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=set_models&text_model=' + encodeURIComponent(textModel) + 
                  '&image_model=' + encodeURIComponent(imageModel) +
                  '&bot_personality=' + encodeURIComponent(personality)
        })
        .then(response => response.json())
        .then(data => {
            const modal = bootstrap.Modal.getInstance(document.getElementById('modelSelectorModal'));
            modal.hide();
            alert('✅ Modele i osobowość zapisane!');
        })
        .catch(error => console.error('Error:', error));
    });
});
</script>
