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
    public function sendRequest($request_body, $log_file=null) {
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
    
    public function getRequestBody($input, $systemRole = 'You are a helpful AI', $max_tokens = 3000, $temperature = 0.8){
      $request_body = array(
        'model' => 'gpt-3.5-turbo',
        'messages' => array(
            array('role' => 'system', 'content' => $systemRole),
            array('role' => 'user', 'content' => $input),
        ),
        'temperature' => $temperature,
        'max_tokens' => $max_tokens,
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
    // Properties
    public $chatChain;
    public $db;

    public function __construct($chatChain, $db) {
      $this->chatChain = $chatChain;
      $this->db = $db;
    }
    
    // Methods
    public function saveChatChain($log_file=null) {
        foreach ($this->chatChain->messages as $message) {
            // Only insert new messages (with id set to null)
            if($log_file) {
              fwrite($log_file, "message var: \n");
              fwrite($log_file, print_r($message, true));
            }
            if ($message->id === null) {
                $message->id = $this->insertMessageIntoDatabase($message);
            }
        }
    }

    public function insertMessageIntoDatabase($message) {
        $query = "INSERT INTO messages (chatChainId, sender, content, timestamp, branchIndex) VALUES (:chatChainId, :sender, :content, :timestamp, :branchIndex)";
        $statement = $this->db->prepare($query);
        $statement->bindParam(':chatChainId', $message->chatChainId, PDO::PARAM_INT);
        $statement->bindParam(':sender', $message->sender, PDO::PARAM_STR);
        $statement->bindParam(':content', $message->content, PDO::PARAM_STR);
        $statement->bindParam(':timestamp', $message->timestamp, PDO::PARAM_STR);
        $statement->bindParam(':branchIndex', $message->branchIndex, PDO::PARAM_INT);
        $statement->execute();

        return $this->db->lastInsertId();
    }

    public function retrieveMessagesFromDatabase() {
        $query = "SELECT * FROM messages WHERE chatChainId = :chatChainId ORDER BY timestamp ASC";
        $statement = $this->db->prepare($query);
        $statement->bindParam(':chatChainId', $this->chatChain->id, PDO::PARAM_INT);
        $statement->execute();

        $messages = [];
        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $message = new Message($row['id'], $row['chatChainId'], $row['sender'], $row['content'], $row['timestamp'], $row['branchIndex']);
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

    public function prependSummary($maxSummaryLength, $newMessage, $log_file) {
        $totalSummary = '';
        $latestBranchIndex = end($this->messages)->branchIndex;
        $numBranches = $latestBranchIndex + 1;
        $summaryLengthPerBranch = intval($maxSummaryLength / $numBranches);
        $branches = $this->messagesByBranch();

        foreach ($branches as $branchContent) {
            $branchSummary = $this->summarizeBranch($branchContent, $summaryLengthPerBranch, $log_file);
            if(strlen($branchSummary) > $summaryLengthPerBranch){
              $branchSummary = substr($branchSummary, 0, $summaryLengthPerBranch);
            }
            $totalSummary .= $branchSummary;
        }


        // Combine the total summary with the new message
        $summaryWithNewMessage = $totalSummary . $newMessage;

        return $summaryWithNewMessage;
    }

    private function summarizeBranch($branchContent, $maxLength, $log_file) {
      // Send the input to ChatGPT API
      $chatGPTAPI = new ChatGPTAPI();
      // One token corresponds to roughly 4 characters
      // TODO: Only use tokens not chars
      $numTokens = $maxLength / 4;
      if($numTokens > 3000) $numTokens = 3000;
      $input = "Please summarize each message in the following conversation and return the conversation in the same format.  Conversation: " . $branchContent;
      $requestBody = $chatGPTAPI->getRequestBody($input, 'You are a helpful AI that takes in conversations and summarizes them', $numTokens, 0.3);
      $response = $chatGPTAPI->sendRequest($requestBody, $log_file);
      return $response;
    }

    
}

class Message {
    // Properties
    public $id;
    public $chatChainId;
    public $sender;
    public $content;
    public $timestamp;
    public $branchIndex;

    // Constructor
    public function __construct($id, $chatChainId, $sender, $content, $timestamp, $branchIndex) {
        $this->id = $id;
        $this->chatChainId = $chatChainId;
        $this->sender = $sender;
        $this->content = $content;
        $this->timestamp = $timestamp;
        $this->branchIndex = $branchIndex;
    }
}
