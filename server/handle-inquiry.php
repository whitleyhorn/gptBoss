<?php
if (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false) {
  include_once("../../db.php");
} else {
  // uses environment variables
  include_once("db.php");
}
include_once("functions/request_chatgpt.php");
include_once("classes/all_classes.php");

// VALIDATE
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('HTTP/1.1 405 Method Not Allowed');
  header('Content-Type: application/json; charset=UTF-8');
  die(json_encode(array('error' => 'Only POST requests are allowed.')));
}

$user_msg = trim($_POST['user_msg']);
$app_type = trim($_POST['app_type']);
$user_id = trim($_POST['user_id']);
$chat_id = trim($_POST['chat_id']);

validate_input($user_msg, "user_msg");
validate_input($app_type, "app_type");
validate_input($user_id, "user_id", "numeric");
validate_input($chat_id, "chat_id", "numeric");

if (strlen($user_msg) < 10 || strlen($user_msg) > 500) {
  header('HTTP/1.1 400 Bad Request');
  header('Content-Type: application/json; charset=UTF-8');
  die(json_encode(array('error' => 'Input length should be between 10 and 500 characters.')));
}

// NOTE: 13000 characters is not exactly the token limit, it's intentionally smaller than it needs to be for now
$tokenLimitChars = 13000;
// Create a new chat chain for the user with 13000 max chars per branch
$chatChain = new ChatChain($chat_id, $user_id, $tokenLimitChars, $db);
$persistenceManager = new PersistenceManager($chatChain, $db);
$chatChain->messages = $persistenceManager->retrieveMessagesFromDatabase();
$latestBranchIndex = (count($chatChain->messages) > 0) ? 
  end($chatChain->messages)->branchIndex 
  : 0;
$branchIndex = $latestBranchIndex;

// Check if a new branch should be started
$newMsgLength = strlen($user_msg);
if ($chatChain->shouldStartNewBranch($newMsgLength)) {
  $branchIndex++;
}

// Initialize input for chatGPT, which will either be the user message with instructions added (if first message), or the user message with the chat history summary prepended to it
$inputForChatGPT = '';

// If first msg, add instructions to the user input
$chatGPTManager = new ChatGPTManager($app_type);
if(count($chatChain->messages) === 0) {
  $user_msg = $chatGPTManager->addInstructions($user_msg);
  $inputForChatGPT = $user_msg;
} else {
  // Prepend a summary of the prev messages to the new message
  // NOTE: We subtract the new message length from $tokenLimitChars because we want to be able to send the context (summaries) plus the entire new message. 
  // We may need to add logic to summarize the new message as well if it's past a certain number of characters
  // Or we just enforce a particular character limit per message
  $messageWithSummary = $chatChain->prependSummary(($tokenLimitChars - strlen($user_msg)), $user_msg);
  $inputForChatGPT = "I am going to send you a summary of a conversation between a user and chatGPT, with the final message from the user at the end. Please respond to that final message.";
}


// Send the input to ChatGPT API
$chatGPTAPI = new ChatGPTAPI();
$requestBody = $chatGPTAPI->getRequestBody($inputForChatGPT);
$response = $chatGPTAPI->sendRequest($requestBody);

// Add user message to the chat chain
$message = new Message(null, $chatChain->id, "user", $user_msg, time(), $branchIndex);
$chatChain->messages[] = $message;

// Add response to the chat chain
$message = new Message(null, $chatChain->id, "ChatGPT", $response, time(), $branchIndex);
$chatChain->messages[] = $message;

// Save the chat chain
$persistenceManager->saveChatChain();

// Record usage metrics
$usageTracker = new UsageTracker();
$usageTracker->recordMetrics($chatChain);

echo json_encode(['result' => $response]);

// *****
function validate_input($input, $name, $str_type = 'varchar') {
  if (empty($input)) {
    header('HTTP/1.1 400 Bad Request');
    header('Content-Type: application/json; charset=UTF-8');
    die(json_encode(array('error' => "Missing required parameter `$name`")));
  }

  if ($str_type === 'numeric' && !ctype_digit($input)) {
    header('HTTP/1.1 400 Bad Request');
    header('Content-Type: application/json; charset=UTF-8');
    die(json_encode(array('error' => "Parameter `$name` should be an integer.")));
  }

  if ($str_type === 'boolean' && !in_array(strtolower($input), array("0", "1", "false", "true"))) {
    header('HTTP/1.1 400 Bad Request');
    header('Content-Type: application/json; charset=UTF-8');
    die(json_encode(array('error' => "Parameter `$name` should be either '0' (false), '1' (true), 'false', or 'true'.")));
  }
}
