document.addEventListener('DOMContentLoaded', () => {
    const chatButton = document.getElementById('chat-widget-button');
    const chatWindow = document.getElementById('chat-widget-window');
    const closeBtn = document.getElementById('chat-close-btn');
    const chatMessages = document.getElementById('chat-messages');
    const chatInput = document.getElementById('chat-input');
    const sendBtn = document.getElementById('chat-send-btn');
    const typingIndicator = document.getElementById('chat-typing');

    let chatHistory = []; // Para enviar contexto

    // Abrir/Cerrar Chat
    chatButton.addEventListener('click', () => {
        chatWindow.classList.add('chat-open');
        setTimeout(() => chatInput.focus(), 300);
        
        // Mensaje de bienvenida si está vacío
        if (chatMessages.querySelectorAll('.chat-message').length === 0) {
            addMessage('¡Hola! Soy tu asistente virtual. ¿En qué puedo ayudarte con la plataforma hoy?', 'bot');
        }
    });

    closeBtn.addEventListener('click', () => {
        chatWindow.classList.remove('chat-open');
    });

    // Enviar mensaje al pulsar Enter
    chatInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
            sendMessage();
        }
    });

    sendBtn.addEventListener('click', sendMessage);

    async function sendMessage() {
        const text = chatInput.value.trim();
        if (!text) return;

        // Añadir mensaje del usuario a la interfaz
        addMessage(text, 'user');
        chatInput.value = '';
        // Guardar en historial
        chatHistory.push({ role: 'user', content: text });

        // Mostrar "escribiendo..."
        typingIndicator.classList.add('active');
        chatMessages.scrollTop = chatMessages.scrollHeight;

        try {
            const finalApiUrl = sendBtn.getAttribute('data-api') || '../php/api_chat.php';

            const response = await fetch(finalApiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ 
                    message: text,
                    history: chatHistory,
                    _csrf_token: sendBtn.getAttribute('data-csrf')
                })
            });

            typingIndicator.classList.remove('active');

            if (response.ok) {
                const data = await response.json();
                if (data.reply) {
                    addMessage(data.reply, 'bot');
                    chatHistory.push({ role: 'assistant', content: data.reply });
                } else if (data.error) {
                    addMessage('Error: ' + data.error, 'bot');
                }
            } else {
                try {
                    const errorData = await response.json();
                    addMessage('Error: ' + (errorData.error || 'Error de conexión con el servidor.'), 'bot');
                } catch(e) {
                    addMessage('Lo siento, ha ocurrido un error de conexión con el servidor. Por favor, intenta de nuevo.', 'bot');
                }
            }
        } catch (error) {
            console.error('Chat error:', error);
            typingIndicator.classList.remove('active');
            addMessage('Lo siento, ha ocurrido un error inesperado.', 'bot');
        }
    }

    function addMessage(text, sender) {
        const msgDiv = document.createElement('div');
        msgDiv.classList.add('chat-message', sender);
        
        // Escapar HTML para evitar XSS
        const safe = text
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');

        // Convertir saltos de línea en <br>
        msgDiv.innerHTML = safe.replace(/\n/g, '<br>');
        
        // Insertar justo antes del indicador de "escribiendo..."
        chatMessages.insertBefore(msgDiv, typingIndicator);
        
        // Scroll hasta abajo
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }
});
