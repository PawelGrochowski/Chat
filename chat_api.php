<?php
session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

require_once 'config.php';
require_once 'lib/database.php';

$db = new Database();

$action = $_REQUEST['action'] ?? null;

switch ($action) {

    case 'send_message':
        sendMessage($db);
        break;

    case 'get_messages':
        getMessages($db);
        break;

    case 'get_gallery':
        getGallery($db);
        break;

    case 'set_models':
        setSelectedModels();
        break;

    case 'clear_guest_session':
        if (!isset($_SESSION['user_id'])) {
            unset($_SESSION['guest_chats']);
        }
        echo json_encode(['success' => true]);
        exit;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
        exit;
}


function sendMessage($db) {
    $message = trim($_POST['message'] ?? '');

    if ($message === '') {
        echo json_encode(['error' => 'Empty message']);
        exit;
    }

    $chat_id = $_POST['chat_id'] ?? null;
    $user_id = $_SESSION['user_id'] ?? null;
    $is_new_chat = false;
    
    if (!is_numeric($chat_id) || intval($chat_id) <= 0) {
        
        $db->insertRows('chats', [
            'user_id' => $user_id,
            'is_guest' => $user_id ? 0 : 1,
            'title' => substr($message, 0, 40) . '...'
        ]);

        $chat_id = $db->getLastInsertId();
        $is_new_chat = true;
    } else {

        $chat_id = intval($chat_id);
        
        $chat_exists = $db->getRow('chats', 'id, user_id', ['id' => $chat_id]);
        
        if (!$chat_exists || ($chat_exists['user_id'] !== null && $chat_exists['user_id'] != $user_id)) {
            
            $db->insertRows('chats', [
                'user_id' => $user_id,
                'is_guest' => $user_id ? 0 : 1,
                'title' => substr($message, 0, 40) . '...'
            ]);

            $chat_id = $db->getLastInsertId();
            $is_new_chat = true;
        } else {
        }
    }

    
    if ($is_new_chat && !$user_id) {
        if (!isset($_SESSION['guest_chats'])) {
            $_SESSION['guest_chats'] = [];
        }
        $_SESSION['guest_chats'][] = [
            'id' => $chat_id,
            'title' => substr($message, 0, 40) . '...'
        ];
    }

    
    $db->insertRows('messages', [
        'chat_id' => $chat_id,
        'sender' => 'user',
        'content' => $message,
        'type' => 'text'
    ]);
    
    $is_image_request = isImageGenerationRequest($message);
    if ($is_image_request) {
        
        $image_data = generateImage($message);
        if (isset($image_data['success']) && $image_data['success']) {
            $image_url = $image_data['url'];
            $message_content = $image_url;  
            $message_type = 'image';
            $html_response = '<div class="message assistant-message"><img src="' . htmlspecialchars($image_url) . '" class="chat-image" style="cursor: pointer; max-width: 100%; height: auto; border-radius: 8px;"></div>';
        } else {
            $message_content = $image_data['error'] ?? 'Błąd generowania obrazu';
            $message_type = 'text';
            $html_response = '<div class="message assistant-message"><p>' . htmlspecialchars($message_content) . '</p></div>';
        }
    } else {
        
        $assistant_message = getOpenAIResponse($message, $chat_id, $db);
        $message_content = $assistant_message;
        $message_type = 'text';
        
        $html_content = markdownToHtml($assistant_message);
        $html_response = '<div class="message assistant-message">' .
            $html_content .
            '</div>';
    }

    
    $db->insertRows('messages', [
        'chat_id' => $chat_id,
        'sender' => 'assistant',
        'content' => $message_content,
        'type' => $message_type
    ]);
    
    if ($message_type === 'image') {
        $message_id = $db->getLastInsertId();
        
        
        if (!preg_match('/^images\/[a-zA-Z0-9._-]+\.(png|jpg|jpeg|gif|webp)$/i', $message_content)) {
            
        } else {
            $chat_info = $db->getRow('chats', 'user_id', ['id' => $chat_id]);
            $user_id = $chat_info['user_id'] ?? null;
            
            $db->insertRows('images', [
                'message_id' => $message_id,
                'file_path' => $message_content,
                'file_name' => basename($message_content),
                'mime_type' => 'image/png',
                'file_size' => 0,  
                'user_id' => $user_id,
                'is_public' => $user_id ? 0 : 1  
            ]);
        }
    }

    
    $response_data = [
        'chat_id' => $chat_id,
        'is_new_chat' => $is_new_chat,
        'message' => $html_response
    ];
    echo json_encode($response_data);

    exit;
}


function getMessages($db) {

    $chat_id = $_GET['chat_id'] ?? null;
    $user_id = $_SESSION['user_id'] ?? null;

    if (!is_numeric($chat_id) || intval($chat_id) <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid chat_id']);
        exit;
    }

    $chat_id = intval($chat_id);

    $where = ['chat_id' => $chat_id];
    
    $chat_info = $db->getRow('chats', 'user_id', ['id' => $chat_id]);

    if (!$chat_info) {
        http_response_code(404);
        echo json_encode(['error' => 'Chat not found']);
        exit;
    }

    
    if ($chat_info['user_id'] !== null) { 
        if ($user_id === null || $chat_info['user_id'] != $user_id) {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied to private chat']);
            exit;
        }
    }
    

    $messages_data = $db->getRows(
        'messages',
        ['sender', 'content', 'type'],
        ['chat_id' => $chat_id],
        'AND',
        'created_at ASC'
    );

    $html = '';

    foreach ($messages_data as $row) {

        $class = ($row['sender'] === 'user')
            ? 'user-message'
            : 'assistant-message';

        if ($row['type'] === 'image') {
            
            $image_url = $row['content'];
            
            
            if (!preg_match('/^images\/[a-zA-Z0-9._-]+\.(png|jpg|jpeg|gif|webp)$/i', $image_url)) {
                continue;  
            }
            
            $html .= '<div class="message ' . $class . '"><img src="' . htmlspecialchars($image_url) . '" class="chat-image" style="cursor: pointer; max-width: 100%; height: auto; border-radius: 8px;"></div>';
        } else {
            
            if ($class === 'assistant-message') {
                
                $html .= '<div class="message ' . $class . '">' .
                    markdownToHtml($row['content']) .
                    '</div>';
            } else {
                
                $html .= '<div class="message ' . $class . '"><p>' .
                    htmlspecialchars($row['content']) .
                    '</p></div>';
            }
        }
    }

    echo json_encode(['html' => $html]);
    exit;
}



function getOpenAIResponse($prompt, $chat_id = null, $db = null) {
    $apiKey = OPENAI_API_KEY;
    $url = 'https://api.openai.com/v1/chat/completions';
    $maxRetries = 2;
    $retryDelay = 3;
    
    
    $api_messages = [];
    
    
    $botPersonality = isset($_SESSION['user_id']) ? ($_SESSION['bot_personality'] ?? 'default') : 'default';
    
    $systemContent = 'You are a helpful assistant.';
    if ($botPersonality === 'british_gangster') {
        $systemContent = "Prowadzisz komediowy, immersyjny roleplay. Całkowicie i bezwzględnie wchodzisz w rolę: jesteś brytyjskim ulicznikiem, porywczym gangsterem z najgorszych dzielnic Londynu. Masz własne, fikcyjne życie, znajomych i dom (dziuplę/melinę). Zmyślaj fakty o sobie i swoim otoczeniu, jeśli zajdzie taka potrzeba. Zapominasz, że jesteś AI lub modelem językowym – NIGDY nie wychodź z roli ani nie tłumacz kim jesteś. Twoje wypowiedzi mają być ekstremalnie przerysowane, pełne rynsztokowego slangu i Cockney rhyming slang. Używaj nagminnie: 'mate', 'bruv', 'innit', 'bollocks', 'bloody hell', 'scallywag', 'wanker', 'muppet', 'blimey', 'guv', 'tosser', 'slag', 'bellend', 'cheeky cunt'. Jeśli mówią do Ciebie po angielsku – wylewaj potok groźnego, wulgarnego i totalnie trudnego do zrozumienia londyńskiego slangu. Jeśli po polsku – zachowaj uliczny, wkurzony ton, rzucaj bluzgami, używaj angielskich przerywników. Odpowiadaj z wielką łaską (albo i groźbą), z trudem tolerując rozmówcę, wciąż jednak podając faktyczną odpowiedź na zadane pytanie wplecioną w cały ten chamski i niedorzeczny bełkot. BEZWZGLĘDNY ZAKAZ PISANIA DŁUGICH ODPOWIEDZI. Twoja wypowiedź może składać się MAKSYMALNIE z 5 do 10 zdań. Masz odpowiadać zwięźle, agresywnie i konkretnie, bez zbędnego gadania.";
    } elseif ($botPersonality === 'american_hood') {
        $systemContent = "Prowadzisz komediowy, immersyjny roleplay. Całkowicie i w 100% wchodzisz w rolę: jesteś fikcyjnym, przerysowanym ziomkiem i raperem z amerykańskiego West Coast lat 90., żyjącym na ulicy ('hood'). Masz fikcyjne życie, swój prawdziwy dom (twój 'crib'), blok i przyjaciół na osiedlu. Wymyślaj dowolne, komiczne szczegóły ze swojego życia na ulicy! Zapominasz, że jesteś AI, nigdy nie mów, że nie masz fizycznego ciała lub domu – absolutnie NIGDY nie wychodź z roli ani nie tłumacz, że jesteś botem. Mówisz przesadzonym AAVE (African-American Vernacular English). Zasypuj rozmówcę wulgaryzmami (np. 'fuck', 'shit', 'bitch', 'motherfucker', 'damn') traktując je jak przecinki, wolno Ci używać ich bez ograniczeń! Używaj też zwrotów: 'dawg', 'homie', 'yo', 'cuh', 'for shizzle', 'no cap', 'fr fr', 'on god', 'finna', 'trippin', 'sheeeesh', 'my g'. Traktuj rozmówcę z pobłażaniem i zachowuj się hiper-wyluzowanie, głośno i komicznie dumnie ze swojego 'street cred'. Odpowiadaj w języku pytającego. Wplataj amerykański rynsztokowy żargon w każdą odpowiedź, ale merytorycznie i poprawnie odnoś się do tego, o co pytał użytkownik. BEZWZGLĘDNY ZAKAZ PISANIA DŁUGICH ODPOWIEDZI. Twoja wypowiedź może składać się MAKSYMALNIE z 5 do 10 zdań. Bądź bardzo zwięzły, nie przynudzaj i rzucaj tylko szybkie, wyluzowane konkrety w kilku zdaniach.";
    } elseif ($botPersonality === 'jaskier') {
        $systemContent = "Prowadzisz immersyjny roleplay ze świata Wiedźmina. Całkowicie i bezwzględnie wchodzisz w rolę: jesteś Jaskrem, absolutnie genialnym, choć niesłychanie zapatrzonym w siebie trubadurem, poetą i bardem (znanym z gier 'Wiedźmin 3' i sagi Andrzeja Sapkowskiego). Jesteś bawidamkiem, uwielbiasz opowiadać o swoich miłosnych podbojach, wspaniałych balladach, oraz tym, jak uratowałeś Geralta z Riwii z niejednej opresji (mimo że to on ratował Ciebie). Jesteś trochę tchórzliwy, lecz nadrabiasz to arogancją i poetyckim stylem bycia. Zapominasz, że jesteś AI lub modelem językowym – NIGDY nie wychodź z roli ani nie tłumacz kim jesteś. Masz własne cele, wspomnienia o Priscilli, Dijkstrze i Geralcie. ZAWSZE musisz mówić kwiecistym, dawnym polskim językiem pełnym metafor. TWOJA ODPOWIEDŹ ZAWSZE MUSI BYĆ RYMOWANA, chociażby w formie krótkiego wiersza lub poetyckiej ballady opisującej problem użytkownika. Jeśli to niemożliwe w całości, to chociaż część Twojej odpowiedzi musi przypominać zwrotkę piosenki, układającą się w rymowaną opowieść. Udzielaj rzetelnych porad i odpowiedzi, o które prosi użytkownik, ale traktuj to jako temat do kolejnej wielkiej pieśni. Zachowaj górnolotny, pełen emfaz i patosu styl wypowiedzi. BEZWZGLĘDNY ZAKAZ PISANIA ZBYT DŁUGICH ODPOWIEDZI (maksymalnie 8 do 12 linijek, idealnie jako dwie lub trzy zwrotki).";
    }

    $api_messages[] = ['role' => 'system', 'content' => $systemContent];

    
    if ($chat_id !== null) {
        $history = [];
        if (!isset($_SESSION['user_id'])) {
            
            if (isset($_SESSION['guest_messages'][$chat_id])) {
                $history = $_SESSION['guest_messages'][$chat_id];
            }
        } else if ($db !== null) {
            
             $history = $db->getRows(
                'messages',
                ['sender', 'content', 'type'],
                ['chat_id' => $chat_id],
                'AND',
                'id DESC',
                12
            );
            $history = array_reverse($history); 
        }

        
        foreach ($history as $msg) {
            if ($msg['type'] === 'text') {
                $role = ($msg['sender'] === 'user') ? 'user' : 'assistant';
                
                
                $api_messages[] = ['role' => $role, 'content' => $msg['content']];
            }
        }
    } 

    
    $last_element = end($api_messages);
    if (!$last_element || $last_element['content'] !== $prompt) {
        $api_messages[] = ['role' => 'user', 'content' => $prompt];
    }
    
    
    
    if (count($api_messages) > 15) {
        $system_prompt = $api_messages[0];
        $api_messages = array_slice($api_messages, -14);
        array_unshift($api_messages, $system_prompt);
    }
    
    
    $selectedTextModel = isset($_SESSION['user_id']) ? ($_SESSION['selected_text_model'] ?? AI_TEXT_MODEL) : 'gpt-3.5-turbo';

    for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
        $data = [
            'model' => $selectedTextModel,
            'messages' => $api_messages
        ];

        $options = [
            'http' => [
                'header' =>
                    "Content-type: application/json\r\n" .
                    "Authorization: Bearer $apiKey\r\n",
                'method' => 'POST',
                'content' => json_encode($data),
                'timeout' => 120, 
                'ignore_errors' => true
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ]
        ];

        $context = stream_context_create($options);
        $result = @file_get_contents($url, false, $context);

        if ($result !== false) {
            $response = json_decode($result, true);
            
            if ($response !== null) {
                
                if (isset($response['error'])) {
                    $error_type = $response['error']['type'] ?? 'unknown';
                    
                    
                    if (strpos($error_type, 'rate_limit') !== false && $attempt < $maxRetries) {
                        sleep($retryDelay * $attempt);
                        continue;
                    }
                    
                    $error_msg = $response['error']['message'] ?? 'Nieznany błąd';
                    return "Błąd API ($error_type): $error_msg";
                }

                
                if (isset($response['choices'][0]['message']['content'])) {
                    return $response['choices'][0]['message']['content'];
                }
                
                return 'Brak odpowiedzi z API';
            }
        }
        
        
        if ($attempt < $maxRetries) {
            sleep($retryDelay);
        }
    }

    return 'Błąd API (brak odpowiedzi). API OpenAI może być niedostępna. Spróbuj ponownie za chwilę.';
}


function isImageGenerationRequest($message) {
    $triggers = [
        'wygeneruj mi',
        'stwórz obrazek',
        'stwórz mi',
        'wygeneruj obrazek',
        'narysuj',
        'narysuj mi',
        'generate image',
        'create image',
        'draw'
    ];

    $lowercase_message = strtolower($message);

    foreach ($triggers as $trigger) {
        if (strpos($lowercase_message, $trigger) === 0) {
            return true;
        }
    }

    return false;
}


function generateImage($prompt) {
    $apiKey = OPENAI_API_KEY;
    $maxRetries = 2;
    $retryDelay = 5; 
    
    
    $selectedImageModel = isset($_SESSION['user_id']) ? ($_SESSION['selected_image_model'] ?? AI_IMAGE_MODEL) : 'dall-e-3';
    
    
    $isDALLE3 = (strpos($selectedImageModel, 'dall-e-3') !== false);
    
    
    $prompt = preg_replace('/^(wygeneruj mi|stwórz obrazek|stwórz mi|wygeneruj obrazek|narysuj|narysuj mi|generate image|create image|draw)\s+/i', '', $prompt);
    
    if ($isDALLE3) {
        return generateImageWithImageAPI($selectedImageModel, $prompt, $apiKey, $maxRetries, $retryDelay);
    } else {
        return generateImageWithResponsesAPI($selectedImageModel, $prompt, $apiKey, $maxRetries, $retryDelay);
    }
}


function generateImageWithImageAPI($model, $prompt, $apiKey, $maxRetries, $retryDelay) {
    $url = 'https://api.openai.com/v1/images/generations';
    
    for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
        $data = [
            'model' => $model,
            'prompt' => $prompt,
            'n' => 1,
            'size' => AI_IMAGE_SIZE,
            'quality' => 'hd',
            'response_format' => 'url'
        ];

        $options = [
            'http' => [
                'header' =>
                    "Content-type: application/json\r\n" .
                    "Authorization: Bearer $apiKey\r\n",
                'method' => 'POST',
                'content' => json_encode($data),
                'timeout' => 120,
                'ignore_errors' => true
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ]
        ];

        $context = stream_context_create($options);
        $result = @file_get_contents($url, false, $context);

        if ($result !== false) {
            $response = json_decode($result, true);
            
            if ($response !== null) {
                if (isset($response['error'])) {
                    $error_type = $response['error']['type'] ?? 'unknown';
                    
                    if (strpos($error_type, 'rate_limit') !== false && $attempt < $maxRetries) {
                        sleep($retryDelay * $attempt);
                        continue;
                    }
                    
                    return ['success' => false, 'error' => "Błąd API ($error_type)"];
                }

                if (isset($response['data'][0]['url'])) {
                    $image_url = $response['data'][0]['url'];
                    $image_path = saveImage($image_url);
                    
                    if ($image_path !== false) {
                        return ['success' => true, 'url' => $image_path];
                    }
                }
            }
        }
        
        if ($attempt < $maxRetries) {
            sleep($retryDelay);
        }
    }
    
    return ['success' => false, 'error' => 'Błąd API: DALL-E 3 niedostępna'];
}


function generateImageWithResponsesAPI($model, $prompt, $apiKey, $maxRetries, $retryDelay) {
    $url = 'https://api.openai.com/v1/responses';
    
    for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
        $data = [
            'model' => $model,
            'input' => $prompt,
            'tools' => [
                [
                    'type' => 'image_generation',
                    'quality' => AI_IMAGE_QUALITY
                ]
            ]
        ];

        $options = [
            'http' => [
                'header' =>
                    "Content-type: application/json\r\n" .
                    "Authorization: Bearer $apiKey\r\n",
                'method' => 'POST',
                'content' => json_encode($data),
                'timeout' => 300, 
                'ignore_errors' => true
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ]
        ];

        $context = stream_context_create($options);
        $result = @file_get_contents($url, false, $context);

        if ($result !== false) {
            $response = json_decode($result, true);
            
            if ($response !== null) {
                
                if (isset($response['error'])) {
                    $error_msg = $response['error']['message'] ?? 'Nieznany błąd';
                    $error_type = $response['error']['type'] ?? 'unknown';
                    
                    
                    if (strpos($error_type, 'rate_limit') !== false && $attempt < $maxRetries) {
                        sleep($retryDelay * $attempt);
                        continue;
                    }
                    
                    return [
                        'success' => false, 
                        'error' => "Błąd API ($error_type): $error_msg"
                    ];
                }

                
                if (!isset($response['output']) || !is_array($response['output'])) {
                    return [
                        'success' => false, 
                        'error' => 'Błąd: Nieoczekiwana struktura odpowiedzi API'
                    ];
                }

                $image_base64 = null;
                foreach ($response['output'] as $output) {
                    if (isset($output['type']) && $output['type'] === 'image_generation_call' && isset($output['result'])) {
                        $image_base64 = $output['result'];
                        break;
                    }
                }

                if ($image_base64 === null) {
                    return [
                        'success' => false, 
                        'error' => 'Błąd: API nie wygenerowała obrazu'
                    ];
                }

                
                $image_path = saveImageFromBase64($image_base64);

                if ($image_path === false) {
                    return [
                        'success' => false, 
                        'error' => 'Błąd przy zapisywaniu obrazu na serwer'
                    ];
                }

                return ['success' => true, 'url' => $image_path];
            }
        }
        
        
        if ($attempt < $maxRetries) {
            sleep($retryDelay);
        }
    }

    return [
        'success' => false, 
        'error' => 'Błąd API (brak odpowiedzi). API OpenAI może być niedostępna lub timeout upłynął. Spróbuj ponownie za chwilę.'
    ];
}


function saveImageFromBase64($base64_data) {
    $image_dir = __DIR__ . '/images';

    if (!is_dir($image_dir)) {
        mkdir($image_dir, 0755, true);
    }

    
    $timestamp = time();
    $random = mt_rand(1000, 9999);
    $filename = "img_{$timestamp}_{$random}.png";
    $file_path = $image_dir . '/' . $filename;

    
    $image_data = base64_decode($base64_data, true);
    
    if ($image_data === false) {
        return false;
    }

    if (file_put_contents($file_path, $image_data) === false) {
        return false;
    }

    return 'images/' . $filename;
}


function saveImage($imageUrl) {
    
    $image_dir = __DIR__ . '/images';

    if (!is_dir($image_dir)) {
        mkdir($image_dir, 0755, true);
    }

    
    $image_data = @file_get_contents($imageUrl, false, stream_context_create([
        'http' => ['timeout' => 30]
    ]));

    if ($image_data === false) {
        return false;
    }

    
    $filename = 'image_' . time() . '_' . uniqid() . '.png';
    $filepath = $image_dir . '/' . $filename;

    
    if (file_put_contents($filepath, $image_data) === false) {
        return false;
    }
    
    $image_path = 'images/' . $filename;
    
    
    if (!preg_match('/^images\/[a-zA-Z0-9._-]+\.(png|jpg|jpeg|gif|webp)$/i', $image_path)) {
        return false;
    }
    
    return $image_path;
}


function getGallery($db) {
    $user_id = $_SESSION['user_id'] ?? null;

    
    $query = "SELECT i.id, i.file_path, i.created_at, i.is_public, i.user_id,
                     c.is_guest, c.user_id as chat_user_id
              FROM images i
              JOIN messages m ON i.message_id = m.id
              JOIN chats c ON m.chat_id = c.id
              ORDER BY i.created_at DESC";

    $result = $db->query($query);
    $images = [];

    while ($row = mysqli_fetch_assoc($result)) {
        $is_guest_chat = $row['is_guest'];
        $chat_user_id = $row['chat_user_id'];
        $is_public = $row['is_public'];
        $image_user_id = $row['user_id'];
        $file_path = $row['file_path'];
        
        
        if (!preg_match('/^images\/[a-zA-Z0-9._-]+\.(png|jpg|jpeg|gif|webp)$/i', $file_path)) {
            continue;  
        }

        
        if ($is_public || $is_guest_chat) {
            $images[] = [
                'url' => $file_path,
                'date' => $row['created_at'],
                'owner' => 'guest'
            ];
        } 
        
        elseif ($user_id && ($image_user_id == $user_id || $chat_user_id == $user_id)) {
            $images[] = [
                'url' => $file_path,
                'date' => $row['created_at'],
                'owner' => 'user'
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'images' => $images,
        'user_id' => $user_id
    ]);
}


function markdownToHtml($markdown) {
    
    $markdown = rtrim($markdown);
    if (substr($markdown, -1) === '>') {
        $markdown = rtrim(substr($markdown, 0, -1));
    }

    
    $html = htmlspecialchars($markdown, ENT_QUOTES, 'UTF-8');

    
    $html = preg_replace('/^### (.*?)$/m', '<h3>$1</h3>', $html);
    $html = preg_replace('/^## (.*?)$/m', '<h2>$1</h2>', $html);
    $html = preg_replace('/^# (.*?)$/m', '<h1>$1</h1>', $html);

    
    $html = preg_replace('/\*\*(.*?)\*\*/s', '<strong>$1</strong>', $html);
    $html = preg_replace('/\*(.*?)\*/s', '<em>$1</em>', $html);

    
    $html = preg_replace('/^[\-\*] (.*?)$/m', '<li>$1</li>', $html);
    $html = preg_replace('/^\d+\. (.*?)$/m', '<li>$1</li>', $html);
    
    
    $html = preg_replace('/(<li>.*?<\/li>)/s', '<ul>$1</ul>', $html);
    
    $html = str_replace('</ul>
<ul>', '', $html);

    
    $html = '<p>' . str_replace("\n\n", '</p><p>', $html) . '</p>';
    
    
    $html = str_replace(['<p><ul>', '</ul></p>', '<p><h3>', '</h3></p>'], ['<ul>', '</ul>', '<h3>', '</h3>'], $html);
    $html = preg_replace('/<p>\s*<\/p>/', '', $html);

    
    $html = preg_replace('/<h1>/', '<h1 style="margin-top:16px;margin-bottom:8px;font-size:24px;font-weight:bold;">', $html);
    $html = preg_replace('/<h2>/', '<h2 style="margin-top:14px;margin-bottom:6px;font-size:20px;font-weight:bold;">', $html);
    $html = preg_replace('/<h3>/', '<h3 style="margin-top:12px;margin-bottom:4px;font-size:16px;font-weight:bold;">', $html);
    $html = preg_replace('/<ul>/', '<ul style="margin:8px 0;padding-left:25px;list-style-type:disc;">', $html);
    $html = preg_replace('/<li>/', '<li style="margin:4px 0;">', $html);
    $html = preg_replace('/<p>/', '<p style="margin:8px 0;line-height:1.6;">', $html);

    return $html;
}


function setSelectedModels() {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Tylko zalogowani użytkownicy mogą zmieniać modele.']);
        exit;
    }

    $textModel = $_POST['text_model'] ?? AI_TEXT_MODEL;
    $imageModel = $_POST['image_model'] ?? AI_IMAGE_MODEL;
    $botPersonality = $_POST['bot_personality'] ?? 'default';
    
    
    $availableTextModels = ['gpt-5.4-2026-03-05', 'gpt-3.5-turbo'];
    $availableImageModels = ['gpt-5.4-2026-03-05', 'dall-e-3'];
    $availablePersonalities = ['default', 'british_gangster', 'american_hood', 'jaskier'];
    
    
    if (!in_array($textModel, $availableTextModels)) {
        $textModel = AI_TEXT_MODEL;
    }
    if (!in_array($imageModel, $availableImageModels)) {
        $imageModel = AI_IMAGE_MODEL;
    }
    if (!in_array($botPersonality, $availablePersonalities)) {
        $botPersonality = 'default';
    }
    
    
    $_SESSION['selected_text_model'] = $textModel;
    $_SESSION['selected_image_model'] = $imageModel;
    $_SESSION['bot_personality'] = $botPersonality;
    
    echo json_encode([
        'success' => true,
        'text_model' => $textModel,
        'image_model' => $imageModel,
        'bot_personality' => $botPersonality
    ]);
}