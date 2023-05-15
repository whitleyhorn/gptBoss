<?php
class ChatGPTManager {
    private $app_type;

    public function __construct($app_type) {
        $this->app_type = $app_type;
    }

    // Methods
    public function addInstructions($user_msg) {
        $instructions = $this->getAppInstructions();
        return $instructions . ' ' . $user_msg;
    }

    private function getAppInstructions() {
        if ($this->app_type === "legal") {
            return "Given the following legal question, please provide a legal answer in 25 words or less.";
        } else if ($this->app_type === "article") {
            return "Given the following request, please generate the requested article in 50 words or less.";
        }
        return "";
    }

    public function filterAbuse($input) {}
    public function addDisclaimer($input) {}
}

class ChatGPTAPI {
    // Methods
    public function sendRequest($request_body) {
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/chat/completions');
      curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'Authorization: Bearer ' . getenv('OPENAI_API_KEY')
      ));
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_POST, true);
      curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request_body));
      $response = curl_exec($ch);
      curl_close($ch);

      // PARSE RESULTS
      $results = json_decode($response, true);

      // HANDLE ERROR
      if(isset($results['error'])) {
        $error_message = $results['error']['message'];
        throw new Exception($error_message);
      }

      $outputs = array();
      foreach ($results['choices'] as $choice) {
        $message = $choice['message']['content'];
        $message = str_replace("\n", "<br>", $message); // replace line breaks with <br> tags
        $outputs[] = $message;
      }

      return $outputs[0];
    }
    public function getRequestBody($input){
      $request_body = array(
        'model' => 'gpt-3.5-turbo',
        'messages' => array(
            // TODO: Modify the system role content based on app type
            array('role' => 'system', 'content' => 'You are a helpful AI'),
            array('role' => 'user', 'content' => $input),
        ),
        'temperature' => 0.8,
        'max_tokens' => 1500,
      );

      return $request_body;
    }
    // NOTE: These could be useful in separating concerns, but for now we are handlding the response and error in sendRequest.
    private function handleResponse($response) {}
    private function handleError($error) {}
}

class UsageTracker {
    // Methods
    // TODO: This.
    public function recordMetrics($chatChain) {}
}

class AdditionalCapabilities {
    // Methods
    // TODO: "Boss can add extra capabilities, e.g offer to send the user an email or text transcript or a list of to-do items."
    public function sendEmail($user, $content) {}
    public function sendText($user, $content) {}
    public function generateToDoList($user) {}
}

class PersistenceManager {
    // Methods
    public function saveChatChain($chatChain, $pdo) {
        foreach ($chatChain->messages as $message) {
            // Only insert new messages (with id set to null)
            if ($message->id === null) {
                $message->id = $this->insertMessageIntoDatabase($message, $pdo);
            }
        }
    }

    public function loadChatChain($chatChainId, $pdo) {
        $chatChain = new ChatChain($chatChainId);
        $chatChain->messages = $this->retrieveMessagesFromDatabase($chatChainId, $pdo);
        return $chatChain;
    }

    public function insertMessageIntoDatabase($message, $pdo) {
        $query = "INSERT INTO messages (chatChainId, sender, content, timestamp, branchIndex) VALUES (:chatChainId, :sender, :content, :timestamp, :branchIndex)";
        $statement = $pdo->prepare($query);
        $statement->bindParam(':chatChainId', $message->chatChainId, PDO::PARAM_INT);
        $statement->bindParam(':sender', $message->sender, PDO::PARAM_STR);
        $statement->bindParam(':content', $message->content, PDO::PARAM_STR);
        $statement->bindParam(':timestamp', $message->timestamp, PDO::PARAM_STR);
        $statement->bindParam(':branchIndex', $message->branchIndex, PDO::PARAM_INT);
        $statement->execute();

        return $pdo->lastInsertId();
    }

    public function retrieveMessagesFromDatabase($chatChainId, $pdo) {
        $query = "SELECT * FROM messages WHERE chatChainId = :chatChainId ORDER BY timestamp ASC";
        $statement = $pdo->prepare($query);
        $statement->bindParam(':chatChainId', $chatChainId, PDO::PARAM_INT);
        $statement->execute();

        $messages = [];
        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $message = new Message($row['id'], $row['chatChainId'], $row['sender'], $row['content'], $row['timestamp']);
            array_push($messages, $message);
        }
        return $messages;
    }
}

class ChatChain {
    // Properties
    public $id;
    public $userId;
    public $messages;
    private $branchChars; // max chars per branch

    // Constructor
    public function __construct($id, $userId, $branchChars) {
        $this->id = $id;
        $this->userId = $userId;
        $this->branchChars = $branchChars;
        $this->messages = [];
    }

    private function messagesByBranch(){
        $branches = array();

        foreach ($this->messages as $message) {
            $branchIndex = $message->branchIndex;

            if (!isset($branches[$branchIndex])) {
                $branches[$branchIndex] = '';
            }

            $branches[$branchIndex] .= "{$message->sender}: {$message->content}\n";
        }

        return $branches;
    }

    public function shouldStartNewBranch($newMsgLength) {
        // Get the latest branch index
        $latestBranchIndex = end($this->messages)->branchIndex;

        // Count the characters in the messages from the latest branch
        $charCount = 0;
        foreach ($this->messages as $message) {
            if ($message->branchIndex === $latestBranchIndex) {
                $charCount += strlen($message->content);
            }
        }

        $charCount += $newMsgLength;

        // Check if it's time to create a new branch
        return ($charCount >= $this->branchChars);
    }

    public function summarizeConversation($maxSummaryLength, $newMessage) {
        $totalSummary = '';
        $latestBranchIndex = end($this->messages)->branchIndex;
        $summaryLengthPerBranch = intval($maxSummaryLength / $latestBranchIndex);
        $branches = messagesByBranch();

        foreach ($branches as $branchContent) {
            $branchSummary = $this->summarizeBranch($branchContent, $summaryLengthPerBranch);
            if(strlen($branchSummary) > $summaryLengthPerBranch){
              $branchSummary = substr($branchSummary, 0, $summaryLengthPerBranch);
            }
            $totalSummary .= $branchSummary;
        }

        // Combine the total summary with the new message
        $summaryWithNewMessage = $totalSummary . $newMessage;

        return $summaryWithNewMessage;
    }

    private function summarizeBranch($branchContent, $maxLength) {
      // Send the input to ChatGPT API
      $chatGPTAPI = new ChatGPTAPI();
      // One token corresponds to roughly 4 characters
      // TODO: Only use tokens not chars
      $numTokens = $maxLength / 4;
      $input = "Please summarize each message in the following conversation and return the conversation in the same format. Use approximately {$numTokens} tokens, and no more than {$numTokens} tokens. Conversations: " . $branchContent;
      $requestBody = $chatGPTAPI->getRequestBody($inputForChatGPT);
      $response = $chatGPTAPI->sendRequest($requestBody);
      return $response;
    }

    
}

class Message {
    // Properties
    public $id = null; // Set default value to null
    public $chatChainId;
    public $sender;
    public $content;
    public $timestamp;
    public $branchIndex;

    // Constructor
    public function __construct($chatChainId, $sender, $content, $timestamp, $branchIndex) {
        $this->chatChainId = $chatChainId;
        $this->sender = $sender;
        $this->content = $content;
        $this->timestamp = $timestamp;
        $this->branchIndex = $branchIndex;
    }
}
