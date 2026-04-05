<?php
include('header.php');
?>

<link rel="stylesheet" href="css/gallery-style.css">

<div class="gallery-container">
    <div class="gallery-header">
        <h1>Biblioteka Obrazków</h1>
        <div class="filter-buttons">
            <button class="filter-btn active" data-filter="all">Wszystkie</button>
            <button class="filter-btn" data-filter="guest">Publiczne (Gość)</button>
            <?php if (isset($_SESSION['user_id'])): ?>
                <button class="filter-btn" data-filter="user">Moje</button>
            <?php endif; ?>
        </div>
    </div>

    <div class="gallery-grid" id="galleryGrid">
        <div class="loading">Ładowanie obrazków...</div>
    </div>
</div>


<div class="image-modal" id="imageModal">
    <div class="image-modal-content">
        <button class="image-modal-close">&times;</button>
        <img id="modalImage" src="" alt="Full size image">
    </div>
</div>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
<script>
$(document).ready(function() {
    let allImages = [];
    let currentFilter = 'all';

    loadGallery();

    function loadGallery() {
        $.ajax({
            url: 'chat_api.php',
            type: 'GET',
            dataType: 'json',
            data: { action: 'get_gallery' },
            success: function(response) {
                if (response.success) {
                    allImages = response.images;
                    renderGallery();
                }
            },
            error: function() {
                $('#galleryGrid').html('<div class="error">Błąd podczas ładowania...</div>');
            }
        });
    }

    function renderGallery() {
        const filteredImages = allImages.filter(img => {
            if (currentFilter === 'all') return true;
            if (currentFilter === 'guest') return img.owner === 'guest';
            if (currentFilter === 'user') return img.owner === 'user';
            return true;
        });

        if (filteredImages.length === 0) {
            $('#galleryGrid').html('<div class="empty"> Brak obrazków</div>');
            return;
        }

        let html = '';
        filteredImages.forEach((img, index) => {
            const date = new Date(img.date).toLocaleDateString('pl-PL');
            const ownerLabel = img.owner === 'guest' ? 'Publiczny' : 'Prywatny';
            html += `
                <div class="gallery-item" data-index="${index}">
                    <div class="gallery-image-wrapper">
                        <img src="${img.url}" alt="Obrazek" class="gallery-thumb">
                        <div class="gallery-overlay">
                            <span class="view-icon">Powiększ</span>
                        </div>
                    </div>
                    <div class="gallery-info">
                        <small>${date}</small>
                        <span class="owner-badge">${ownerLabel}</span>
                    </div>
                </div>
            `;
        });

        $('#galleryGrid').html(html);

        
        $('.gallery-item').on('click', function() {
            const index = $(this).data('index');
            const img = filteredImages[index];
            $('#modalImage').attr('src', img.url);
            $('#imageModal').addClass('show');
        });
    }

    $('.filter-btn').on('click', function() {
        $('.filter-btn').removeClass('active');
        $(this).addClass('active');
        currentFilter = $(this).data('filter');
        renderGallery();
    });

    $('.image-modal-close').on('click', function() {
        $('#imageModal').removeClass('show');
    });

    $('#imageModal').on('click', function(e) {
        if (e.target.id === 'imageModal') {
            $('#imageModal').removeClass('show');
        }
    });

    $(document).on('keydown', function(e) {
        if (e.key === 'Escape') {
            $('#imageModal').removeClass('show');
        }
    });
});
</script>

