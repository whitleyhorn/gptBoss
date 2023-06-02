const messageContainer = document.getElementById("message-container");
const inputMessage = document.getElementById("input-message");
const sendButton = document.getElementById("send-button");

let conversationId;

// Unique user id for each session. In a real application, you'd need to manage users properly.
const userId = Math.random().toString(36).substr(2, 9);
const appType = "chatbot"; // Fixed app type

async function sendMessage() {
  const message = inputMessage.value.trim();

  if (message) {
    const messageElement = document.createElement("div");
    messageElement.classList.add("message", "user-message");
    messageElement.textContent = message;
    messageContainer.appendChild(messageElement);
    inputMessage.value = "";

    const response = await fetch("/chat", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        message: message,
        user_id: userId,
        app_type: appType,
        conversation_id: conversationId,
      }),
    });

    const data = await response.json();
    conversationId = data.conversation_id;

    const botMessage = data.response;
    const botMessageElement = document.createElement("div");
    botMessageElement.classList.add("message", "bot-message");
    botMessageElement.textContent = botMessage;
    messageContainer.appendChild(botMessageElement);

    messageContainer.scrollTop = messageContainer.scrollHeight;
  }
}

sendButton.addEventListener("click", sendMessage);
inputMessage.addEventListener("keyup", (event) => {
  if (event.key === "Enter") {
    sendMessage();
  }
});
