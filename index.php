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
        <form id="message-form" style="position: relative; display: flex; width: 100%;">
            <input type="text" id="message-input" placeholder="Wpisz wiadomość..." style="padding-right: 80px; width: 100%;">
            
            <div class="personality-selector" style="position: absolute; right: 110px; top: 50%; transform: translateY(-50%); display: flex; align-items: center; justify-content: center; opacity: 0.8; transition: opacity 0.2s;">
                <div class="custom-dropdown" id="quickPersonalityDropdown">
                    <button type="button" class="custom-dropdown-toggle" id="quickPersonalityToggle" title="Zmień osobowość w locie">DEF</button>
                    <ul class="custom-dropdown-menu" id="quickPersonalityMenu">
                        <li data-value="default" title="Domyślny bot"><span class="custom-badge">DEF</span> Domyślny bot</li>
                        <li data-value="Brytyjczyk" title="Brytyjski gangus"><span class="custom-badge">GB</span> Brytyjczyk</li>
                        <li data-value="Amerykanin" title="Czarnoskóry raper"><span class="custom-badge">US</span> Amerykanin</li>
                        <li data-value="jaskier" title="Jaskier"><span class="custom-badge">JAS</span> Jaskier</li>
                    </ul>
                </div>
            </div>
            
            <button type="submit" style="margin-left: 10px;">Wyślij</button>
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
    
    const customDropdown = document.getElementById('quickPersonalityDropdown');
    
    if (customDropdown) {
        const toggleBtn = document.getElementById('quickPersonalityToggle');
        const menu = document.getElementById('quickPersonalityMenu');
        const items = menu.querySelectorAll('li');
        
        const badgesMap = {
            'default': 'DEF',
            'Brytyjczyk': 'GB',
            'Amerykanin': 'US',
            'jaskier': 'JAS'
        };

        toggleBtn.textContent = badgesMap[savedPersonality] || 'DEF';
        
        items.forEach(item => {
            if(item.dataset.value === savedPersonality) item.classList.add('active');
        });

        toggleBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            menu.classList.toggle('show');
        });

        document.addEventListener('click', function(e) {
            if (!customDropdown.contains(e.target)) {
                menu.classList.remove('show');
            }
        });

        items.forEach(item => {
            item.addEventListener('click', function(e) {
                e.stopPropagation();
                const val = this.dataset.value;
                document.getElementById('personalitySelect').value = val;
                toggleBtn.textContent = badgesMap[val];
                
                items.forEach(i => i.classList.remove('active'));
                this.classList.add('active');
                
                menu.classList.remove('show');

                const textModel = document.getElementById('textModelSelect').value;
                const imageModel = document.getElementById('imageModelSelect').value;
                sessionStorage.setItem('selectedPersonality', val);

                fetch('chat_api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=set_models&text_model=' + encodeURIComponent(textModel) +
                          '&image_model=' + encodeURIComponent(imageModel) +
                          '&bot_personality=' + encodeURIComponent(val)
                }).catch(e => console.error('Silent save error:', e));
            });
        });
    }

    document.getElementById('saveModelsBtn').addEventListener('click', function() {
        const textModel = document.getElementById('textModelSelect').value;
        const imageModel = document.getElementById('imageModelSelect').value;
        const personality = document.getElementById('personalitySelect').value;
        
        if (customDropdown) {
            const toggleBtn = document.getElementById('quickPersonalityToggle');
            const items = document.getElementById('quickPersonalityMenu').querySelectorAll('li');
            const badgesMap = {'default': 'DEF', 'Brytyjczyk': 'GB', 'Amerykanin': 'US', 'jaskier': 'JAS'};
            toggleBtn.textContent = badgesMap[personality] || 'DEF';
            items.forEach(i => {
                i.classList.remove('active');
                if (i.dataset.value === personality) i.classList.add('active');
            });
        }
        
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
            alert('Modele i osobowość zapisane!');
        })
        .catch(error => console.error('Error:', error));
    });
});
</script>
