const form = document.getElementById("inquiry-form");
const inquiryInput = document.getElementById("inquiry");
const appTypeInput = document.getElementById("app-type");
const idInput = document.getElementById("id");
const chatIdInput = document.getElementById("chat-id");
const conversationListElement = document.getElementById("conversation-list");
const apiEndpoint = "/server/handle-inquiry.php";

form.addEventListener("submit", handleSubmit);

async function handleSubmit(event) {
  event.preventDefault();

  const { value: inquiry } = inquiryInput;
  const { value: appType } = appTypeInput;
  const { value: user_id } = idInput;
  const { value: chat_id } = chatIdInput;

  if (!inquiry) {
    alert("Please enter an inquiry");
    return;
  }

  try {
    const formData = new URLSearchParams();
    formData.append("user_msg", inquiry);
    formData.append("app_type", appType);
    formData.append("user_id", user_id);
    formData.append("chat_id", chat_id);

    const response = await fetch(apiEndpoint, {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: formData,
    });

    const data = await response.json();

    if (!response.ok) {
      throw new Error(data.error);
    }

    let historyHTML = "";
    for (let msg of data.history) {
      historyHTML += `<b>${msg.from}:</b> ${msg.message}<br/>`;
    }

    conversationListElement.innerHTML = historyHTML;
  } catch (error) {
    alert("Error: " + error.message);
  }
}
