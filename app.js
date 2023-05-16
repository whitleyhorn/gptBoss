const form = document.getElementById("inquiry-form");
const inquiryInput = document.getElementById("inquiry");
const appTypeInput = document.getElementById("app-type");
const idInput = document.getElementById("id");
const chatIdInput = document.getElementById("chat-id");
const responseElement = document.getElementById("response");
const apiEndpoint = "/server/handle-inquiry.php";

form.addEventListener("submit", handleSubmit);

async function handleSubmit(event) {
  event.preventDefault();

  const { value: inquiry } = inquiryInput;
  const { value: appType } = appTypeInput;
  const { value: user_id } = idInput;
  const { value: chat_id } = chatIdInput;

  if (!inquiry) {
    responseElement.textContent = "Please enter an inquiry.";
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

    responseElement.textContent = data.result;
  } catch (error) {
    responseElement.textContent = `Error: ${error.message}`;
  }
}
