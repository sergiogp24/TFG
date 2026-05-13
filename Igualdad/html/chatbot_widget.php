<?php
// Asegurarnos de que las rutas sean correctas independientemente de dónde se incluya
$basePath = app_base_path(); // Asumiendo que config.php está cargado y tenemos app_base_path()
$cssPath = $basePath . '/css/chatbot.css';
$jsPath = $basePath . '/assets/js/chatbot.js';
?>

<!-- Importar FontAwesome para los iconos (si no está ya incluido en el proyecto) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<!-- Cargar CSS del Chatbot -->
<link rel="stylesheet" href="<?php echo $cssPath; ?>">

<!-- Botón Flotante -->
<button id="chat-widget-button" aria-label="Abrir Asistente Virtual">
    <i class="fas fa-comment-dots"></i>
</button>

<!-- Ventana de Chat -->
<div id="chat-widget-window">
    <div class="chat-header">
        <div class="chat-header-info">
            <div class="chat-header-icon"></div>
            <span>Tu Asistente Virtual</span>
        </div>
        <button id="chat-close-btn" class="chat-close-btn">&times;</button>
    </div>
    
    <div class="chat-messages" id="chat-messages">
        <!-- Los mensajes se insertarán aquí dinámicamente -->
        
        <!-- Indicador de escribiendo (oculto por defecto) -->
        <div class="chat-typing" id="chat-typing">
            <div class="dot"></div>
            <div class="dot"></div>
            <div class="dot"></div>
        </div>
    </div>
    
    <div class="chat-input-area">
        <input type="text" id="chat-input" class="chat-input" placeholder="Escribe tu mensaje aquí..." autocomplete="off">
        <button id="chat-send-btn" class="chat-send-btn" data-csrf="<?= csrf_token() ?>" data-api="<?= h(app_path('/php/api_chat.php')) ?>">
            <i class="fas fa-paper-plane"></i>
        </button>
    </div>
</div>

<!-- Cargar JS del Chatbot -->
<script src="<?php echo $jsPath; ?>"></script>
