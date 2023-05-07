const form = document.getElementById("inquiry-form");
const inquiryInput = document.getElementById("inquiry");
const appTypeInput = document.getElementById("app-type");
const responseElement = document.getElementById("response");
const apiEndpoint = "/server/handle-inquiry.php";

form.addEventListener("submit", handleSubmit);

// ****
async function handleSubmit(event) {
  event.preventDefault();

  const { value: inquiry } = inquiryInput;
  const { value: appType } = appTypeInput;

  if (!inquiry) {
    responseElement.textContent = "Please enter an inquiry.";
    return;
  }

  try {
    const formData = new URLSearchParams();
    formData.append("inquiry", inquiry);
    formData.append("appType", appType);

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
