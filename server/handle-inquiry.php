<?php
include_once("functions/request_chatgpt.php");

// VALIDATE
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('HTTP/1.1 405 Method Not Allowed');
  header('Content-Type: application/json; charset=UTF-8');
  die(json_encode(array('error' => 'Only POST requests are allowed.')));
}

$inquiry = trim($_POST['inquiry']);
$appType = trim($_POST['appType']);

validate_input($inquiry, "inquiry");
validate_input($appType, "appType");

if (strlen($inquiry) < 10 || strlen($inquiry) > 500) {
  header('HTTP/1.1 400 Bad Request');
  header('Content-Type: application/json; charset=UTF-8');
  die(json_encode(array('error' => 'Input length should be between 10 and 500 characters.')));
}

// Add instructions to the inquiry based on app type
$inquiryWithInstructions = add_instructions($inquiry, $appType);

// Handle inquiry based on app type
if ($appType === "legal") {
  $result = handle_legal_inquiry($inquiryWithInstructions);
} else if ($appType === "article") {
  $result = handle_article_inquiry($inquiryWithInstructions);
} else {
  header('HTTP/1.1 400 Bad Request');
  header('Content-Type: application/json; charset=UTF-8');
  die(json_encode(array('error' => 'Invalid `appType` value')));
}

echo json_encode(['result' => $inquiryWithInstructions]);

// *****
function validate_input($input, $name) {
  if (empty($input)) {
    header('HTTP/1.1 400 Bad Request');
    header('Content-Type: application/json; charset=UTF-8');
    die(json_encode(array('error' => "Missing required parameter `$name`")));
  }
}

function add_instructions($inquiry, $appType) {
  $instructions = "";

  if ($appType === "legal") {
    $instructions = "Given the following legal question, please provide a legal answer in 25 words or less.";
  } else if ($appType === "article") {
    $instructions = "Given the following request, please generate the requested article in 50 words or less.";
  }

  return $instructions . " " . $inquiry;
}

function handle_legal_inquiry($inquiry){}
function handle_article_inquiry($inquiry){}
