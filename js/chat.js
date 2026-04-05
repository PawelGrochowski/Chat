$(document).ready(function () {

    let chatId = initialChatId ? Number(initialChatId) : null;

    const $modal = $(`
        <div class="image-modal" id="imageModal">
            <div class="image-modal-content">
                <button class="image-modal-close">&times;</button>
                <img id="modalImage" src="" alt="Full size image">
            </div>
        </div>
    `);
    $('body').append($modal);

    $(document).off('click', '.image-modal-close').on('click', '.image-modal-close', function(e) {
        e.preventDefault();
        e.stopPropagation();
        closeImageModal();
    });

    $('#imageModal').off('click').on('click', function(e) {
        if (e.target === this) {
            closeImageModal();
        }
    });

    $(document).off('keydown.imageModal').on('keydown.imageModal', function(e) {
        if (e.key === 'Escape' && $('#imageModal').hasClass('show')) {
            e.preventDefault();
            closeImageModal();
        }
    });

    function closeImageModal() {
        $('#imageModal').removeClass('show');
    }

    if (chatId) {
        loadMessages();
    }

    function scrollToBottom() {
        const box = $('#chat-box');
        box.scrollTop(box[0].scrollHeight);
    }

    function loadMessages() {
        if (!chatId) return;

        $.ajax({
            url: 'chat_api.php',
            type: 'GET',
            dataType: 'json',
            data: {
                action: 'get_messages',
                chat_id: chatId
            },
            success: function (response) {
                if (response.html) {
                    $('#chat-box').html(response.html);
                    attachImageClickHandlers();
                    scrollToBottom();
                }
            },
            error: function (xhr) {
            }
        });
    }

    function attachImageClickHandlers() {
        $(document).off('click', '.chat-image');
        
        $(document).on('click', '.chat-image', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const imageSrc = $(this).attr('src');
            
            if (imageSrc && imageSrc.trim() !== '') {
                $('#modalImage').attr('src', imageSrc);
                
                const modal = $('#imageModal');
                modal.addClass('show');
                return false;
            }
        });
    }

    $('#message-form').submit(function (e) {
        e.preventDefault();

        const message = $('#message-input').val().trim();

        if (!message) return;

        $('#message-input').val('');

        $('#chat-box').append(
            '<div class="message user-message"><p>' +
            escapeHtml(message) +
            '</p></div>'
        );

        scrollToBottom();

        const isImageRequest = isGeneratingImage(message);

        if (isImageRequest) {
            const $loader = $(
                '<div class="message assistant-message image-loader">' +
                '<div class="loader-text">Trwa generowanie.</div>' +
                '</div>'
            );
            $('#chat-box').append($loader);
            
            let dotCount = 1;
            const loaderInterval = setInterval(function() {
                dotCount = dotCount === 3 ? 1 : dotCount + 1;
                const dots = '.'.repeat(dotCount);
                $loader.find('.loader-text').text('Trwa generowanie' + dots);
            }, 500);
            
            $loader.data('intervalId', loaderInterval);
        } else {
            $('#chat-box').append(
                '<div class="message assistant-message typing-indicator">' +
                '<div class="typing-dot"></div>' +
                '<div class="typing-dot"></div>' +
                '<div class="typing-dot"></div>' +
                '</div>'
            );
        }

        scrollToBottom();

        $.ajax({
            url: 'chat_api.php',
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'send_message',
                message: message,
                chat_id: chatId 
            },

            success: function (response) {

                $('.typing-indicator').remove();
                
                const $imageLoader = $('.image-loader');
                if ($imageLoader.length) {
                    clearInterval($imageLoader.data('intervalId'));
                    $imageLoader.remove();
                }

                if (response.error) {
                    $('#chat-box').append(
                        '<div class="message assistant-message" style="border: 1px solid #ef4444; background-color: rgba(239, 68, 68, 0.1);"><p style="color: #fca5a5;">' +
                        response.error +
                        '</p></div>'
                    );
                    scrollToBottom();
                    return;
                }

                if (response.chat_id) {
                    chatId = Number(response.chat_id);
                }

                if (response.message) {
                    $('#chat-box').append(response.message);
                    attachImageClickHandlers();
                }

                scrollToBottom();
            },

            error: function (xhr) {

                $('.typing-indicator').remove();
                
                const $imageLoader = $('.image-loader');
                if ($imageLoader.length) {
                    clearInterval($imageLoader.data('intervalId'));
                    $imageLoader.remove();
                }

                let errorMsg = 'Błąd serwera';
                if (xhr && xhr.responseJSON && xhr.responseJSON.error) {
                    errorMsg = xhr.responseJSON.error;
                } else if (xhr && xhr.responseText) {
                    errorMsg = 'Błąd serwera (' + xhr.status + '): ' + xhr.statusText;
                }
                
                $('#chat-box').append(
                    '<div class="message assistant-message"><p>' + errorMsg + '</p></div>'
                );

                scrollToBottom();
            }
        });
    });

    function escapeHtml(text) {
        return text
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function isGeneratingImage(message) {
        const triggers = [
            'wygeneruj mi',
            'stwórz obrazek',
            'stwórz mi',
            'wygeneruj obrazek',
            'narysuj',
            'narysuj mi',
            'generate image',
            'create image',
            'draw'
        ];

        const lowerMessage = message.toLowerCase();

        for (let trigger of triggers) {
            if (lowerMessage.startsWith(trigger)) {
                return true;
            }
        }

        return false;
    }

    let currentAudio = null;
    let currentTtsBtn = null;

    document.addEventListener('click', async function(e) {
        const btn = e.target.closest('.tts-btn');
        if (!btn) return;
        
        e.preventDefault();
        
        if (currentAudio) {
            currentAudio.pause();
            currentAudio = null;
            
            const prevBtn = currentTtsBtn;
            if (prevBtn) {
                prevBtn.innerHTML = '<i class="bi bi-play-fill"></i>';
                currentTtsBtn = null;
            }
            if (btn === prevBtn) return;
        }

        const messageId = btn.getAttribute('data-message-id');
        if (!messageId) return;

        btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
        currentTtsBtn = btn;

        try {
            const url = `chat_api.php?action=tts&message_id=${messageId}`;
            currentAudio = new Audio(url);
            
            await new Promise((resolve, reject) => {
                currentAudio.oncanplaythrough = resolve;
                currentAudio.onerror = reject;
                currentAudio.load();
            });

            btn.innerHTML = '<i class="bi bi-pause-fill"></i>';
            currentAudio.play();

            currentAudio.onended = () => {
                btn.innerHTML = '<i class="bi bi-play-fill"></i>';
                currentAudio = null;
                currentTtsBtn = null;
            };

        } catch (error) {
            console.error('Error playing TTS:', error);
            btn.innerHTML = '<i class="bi bi-play-fill" style="color:var(--bs-form-invalid-color) !important"></i>';
            setTimeout(() => btn.innerHTML = '<i class="bi bi-play-fill"></i>', 2000);
        }
    });

})