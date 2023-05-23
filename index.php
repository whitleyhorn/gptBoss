<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GPT Boss Frontend</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <h1>GPT Boss</h1>
    <form id="inquiry-form">
        <label for="app-type">Select App Type:</label>
        <select id="app-type" name="app-type">
            <option value="legal">Legal Answers</option>
            <option value="article">Article Generator</option>
        </select>
        <br/>
        <label for="inquiry">Enter your inquiry:</label>
        <textarea id="inquiry" rows="4" cols="50"></textarea>
        <br/>
        <label for="id">Enter your ID:</label>
        <input type="number" id="id" name="id">
        <br/>
        <label for="chat-id">Enter your Chat ID:</label>
        <input type="number" id="chat-id" name="chat-id">
        <br/>
        <button type="submit">Submit</button>
    </form>
    <div id="conversation-container">
        <h2>Conversation</h2>
        <ul id="conversation-list">
        </ul>
    </div>
    <script src="app.js"></script>
</body>
</html>
