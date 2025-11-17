</main>
  <footer class="site-footer">
    <p>© <?= date('Y') ?> SlotBook</p>
  </footer>

  <!-- Chatbot Widget -->
   <link rel="stylesheet" href="css\chatbot.css">
  <div id="chatbotWidget">
    <div id="chatbotButton">
      <span>💬</span>
    </div>
    <div id="chatbotWindow" style="display: none;">
      <div class="chatbot-header">
        <h4>SlotBook Assistant</h4>
        <button id="closeChatbot">&times;</button>
      </div>
      <div class="chatbot-messages" id="chatbotMessages">
<div class="bot-message">
  Hello! I'm your SlotBook assistant. I can check room availability for you. Try asking "Is room 202 available at 2pm?" or "Check the research lab for tomorrow morning"
</div>
      </div>
      <div class="chatbot-input">
        <input type="text" id="chatbotInput" placeholder="Ask about room availability...">
        <button id="sendMessage">Send</button>
      </div>
    </div>
  </div>


  <script>
  document.addEventListener('DOMContentLoaded', function() {
    const chatbotButton = document.getElementById('chatbotButton');
    const chatbotWindow = document.getElementById('chatbotWindow');
    const closeChatbot = document.getElementById('closeChatbot');
    const chatbotMessages = document.getElementById('chatbotMessages');
    const chatbotInput = document.getElementById('chatbotInput');
    const sendMessage = document.getElementById('sendMessage');

    chatbotButton.addEventListener('click', function() {
      chatbotWindow.style.display = 'flex';
      chatbotInput.focus();
    });

    closeChatbot.addEventListener('click', function() {
      chatbotWindow.style.display = 'none';
    });

    function addMessage(message, isUser = false) {
      const messageDiv = document.createElement('div');
      messageDiv.classList.add(isUser ? 'user-message' : 'bot-message');
      messageDiv.textContent = message;
      chatbotMessages.appendChild(messageDiv);
      chatbotMessages.scrollTop = chatbotMessages.scrollHeight;
    }

    function showTypingIndicator() {
      const typingDiv = document.createElement('div');
      typingDiv.classList.add('chatbot-typing');
      typingDiv.id = 'typingIndicator';
      typingDiv.textContent = 'Assistant is typing...';
      chatbotMessages.appendChild(typingDiv);
      chatbotMessages.scrollTop = chatbotMessages.scrollHeight;
    }

    function hideTypingIndicator() {
      const typingIndicator = document.getElementById('typingIndicator');
      if (typingIndicator) {
        typingIndicator.remove();
      }
    }

    function sendUserMessage() {
      const message = chatbotInput.value.trim();
      if (message === '') return;

      addMessage(message, true);
      chatbotInput.value = '';
      showTypingIndicator();

      // Send to server
      fetch('chatbot.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'message=' + encodeURIComponent(message)
      })
      .then(response => response.json())
      .then(data => {
        hideTypingIndicator();
        addMessage(data.response);
      })
      .catch(error => {
        hideTypingIndicator();
        console.error('Error:', error);
        addMessage('Sorry, I encountered an error. Please try again.');
      });
    }

    sendMessage.addEventListener('click', sendUserMessage);

    chatbotInput.addEventListener('keypress', function(e) {
      if (e.key === 'Enter') {
        sendUserMessage();
      }
    });

    // Close chatbot when clicking outside
    document.addEventListener('click', function(e) {
      if (!chatbotWindow.contains(e.target) && !chatbotButton.contains(e.target)) {
        chatbotWindow.style.display = 'none';
      }
    });
  });
  </script>

  <script src="/slotbook/assets/main.js"></script>
</body>
</html>