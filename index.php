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
        <button type="submit">Submit</button>
    </form>
    <div id="response-container">
        <h2>Response</h2>
        <pre id="response"></pre>
    </div>
    <script src="app.js"></script>
</body>
</html> 
