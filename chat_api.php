<?php
require_once 'config.php';
session_start();

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

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

    case 'delete_chat':
        deleteChat($db);
        break;

    case 'clear_guest_session':
        if (!isset($_SESSION['user_id'])) {
            unset($_SESSION['guest_chats']);
        }
        echo json_encode(['success' => true]);
        exit;

    case 'tts':
        handleTts($db);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
        exit;
}


function sendMessage($db) {
    try {
        if (!file_exists('settings.json')) {
        file_put_contents('settings.json', json_encode(['guest_access' => 1]));
    }
    
    $settings = json_decode(file_get_contents('settings.json'), true);
    
    $user_id = $_SESSION['user_id'] ?? null;
    
    if ($user_id) {
        $user_data = $db->getRow('users', ['is_allowed'], ['id' => $user_id]);
        if (!$user_data || $user_data['is_allowed'] == 0) {
            echo json_encode(['error' => '<b>Blokada bezpieczeństwa:</b> Twoje konto nie zostało zautoryzowane przez administratora.<br>Poproś o uprawnienia dostępowe by generować wiadomości i obrazy.']);
            exit;
        }
    } else {
        if ($settings['guest_access'] == 0) {
            echo json_encode(['error' => '<b>Blokada bezpieczeństwa:</b> Dostęp testowy "Gościa" został wyłączony przez system u wszystkich na widowni. Zaloguj się by uzyskać dostęp.']);
            exit;
        }
    }

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
            'title' => mb_strlen($message, 'UTF-8') > 40 ? mb_substr($message, 0, 40, 'UTF-8') . '...' : $message
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
                'title' => mb_strlen($message, 'UTF-8') > 40 ? mb_substr($message, 0, 40, 'UTF-8') . '...' : $message
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
            'title' => mb_strlen($message, 'UTF-8') > 40 ? mb_substr($message, 0, 40, 'UTF-8') . '...' : $message
        ];
    }

    
    $db->insertRows('messages', [
        'chat_id' => $chat_id,
        'sender' => 'user',
        'content' => $message,
        'type' => 'text',
        'personality' => $_SESSION['bot_personality'] ?? 'default'
    ]);
    
    $intent = getIntent($message);
    
    $personality = htmlspecialchars($_SESSION['bot_personality'] ?? 'default');
    $persData = [
        'default' => ['icon' => 'DEF', 'name' => 'Domyślny bot'],
        'british_gangster' => ['icon' => 'GB', 'name' => 'Brytyjski gangus'],
        'american_hood' => ['icon' => 'US', 'name' => 'Czarnoskóry raper'],
        'jaskier' => ['icon' => 'JAS', 'name' => 'Jaskier']
    ];
    $pInfo = $persData[$personality] ?? $persData['default'];
    $persTag = '<div class="message-personality" title="Osobowość: ' . $pInfo['name'] . '">' . $pInfo['icon'] . '</div>';

    if ($intent === 'image') {

        $image_data = generateImage($message);
        if (isset($image_data['success']) && $image_data['success']) {
            $image_url = $image_data['url'];
            $message_content = $image_url;  
            $message_type = 'image';
            $html_response = '<div class="message assistant-message">' . $persTag . '<img src="' . htmlspecialchars($image_url) . '" class="chat-image" style="cursor: pointer; max-width: 100%; height: auto; border-radius: 8px;"></div>';
        } else {
            $message_content = $image_data['error'] ?? 'Błąd generowania obrazu';
            $message_type = 'text';
            $html_response = '<div class="message assistant-message">' . $persTag . '<p>' . htmlspecialchars($message_content) . '</p></div>';
        }
    } else {
        
        $assistant_message = getOpenAIResponse($message, $chat_id, $db);
        $message_content = $assistant_message;
        $message_type = 'text';
        
        $html_content = markdownToHtml($assistant_message);
        $html_response = '<div class="message assistant-message">' . $persTag .
            $html_content .
            '</div>';
    }

    
    $db->insertRows('messages', [
        'chat_id' => $chat_id,
        'sender' => 'assistant',
        'content' => $message_content,
        'type' => $message_type,
        'personality' => $_SESSION['bot_personality'] ?? 'default'
    ]);
    
    if ($message_type === 'image') {
        $message_id = $db->getLastInsertId();
        
        if (!preg_match('/^images\/[a-zA-Z0-9._-]+\.(png|jpg|jpeg|gif|webp)$/i', $message_content)) {

        } else {
            $chat_info = $db->getRow('chats', 'user_id', ['id' => $chat_id]);
              $user_id = (!empty($chat_info['user_id']) && $chat_info['user_id'] !== '') ? (int)$chat_info['user_id'] : null;

              $db->insertRows('images', [
                  'message_id' => (int)$message_id,
                  'file_path' => $message_content,
                  'file_name' => basename($message_content),
                  'mime_type' => 'image/png',
                  'file_size' => 0,
                  'user_id' => $user_id,
                  'is_public' => $user_id ? 0 : 1
              ]);
        }
    } else {
        $message_id = $db->getLastInsertId();
        $ttsTag = '<button class="btn btn-sm btn-outline-secondary tts-btn" data-message-id="' . $message_id . '" title="Odtwórz wiadomość" style="position: absolute; bottom: -12px; right: 20px; font-size: 10px; padding: 2px 6px; border-radius: 12px; z-index: 10;"><i class="bi bi-play-fill"></i></button>';
        $html_response = '<div class="message assistant-message" style="position: relative;">' . $persTag . $ttsTag . markdownToHtml($message_content) . '</div>';
    }

    $response_data = [
        'chat_id' => $chat_id,
        'is_new_chat' => $is_new_chat,
        'message' => $html_response
    ];

    $json = json_encode($response_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    ob_clean();
    if ($json === false) {
        $error_msg = json_last_error_msg();
        error_log("JSON encode error: " . $error_msg);
        echo json_encode([
            'chat_id' => $chat_id,
            'is_new_chat' => $is_new_chat,
            'message' => '<div class="message assistant-message"><p>Wystąpił błąd przy generowaniu odpowiedzi: ' . $error_msg . '</p></div>'
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo $json;
    }

    exit;
    } catch (\Throwable $e) {
        ob_clean();
        http_response_code(500);
        echo json_encode(['error' => 'Błąd PHP: ' . $e->getMessage() . ' na linii ' . $e->getLine() . ' w ' . basename($e->getFile())], JSON_UNESCAPED_UNICODE);
        exit;
    }
}


function deleteChat($db) {
    $chat_id = $_POST['chat_id'] ?? null;
    $user_id = $_SESSION['user_id'] ?? null;

    if (!is_numeric($chat_id) || intval($chat_id) <= 0) {
        echo json_encode(['error' => 'Invalid chat_id']);
        exit;
    }
    
    $chat_id = intval($chat_id);

    if ($user_id) {
        $chat_info = $db->getRow('chats', 'user_id', ['id' => $chat_id]);
        if ($chat_info && $chat_info['user_id'] == $user_id) {
            $db->updateRows('chats', ['user_id' => null], ['id' => $chat_id]);
            echo json_encode(['success' => true]);
            exit;
        }
    } else {
        if (isset($_SESSION['guest_chats'])) {
            foreach ($_SESSION['guest_chats'] as $key => $chat) {
                if ($chat['id'] == $chat_id) {
                    unset($_SESSION['guest_chats'][$key]);
                    $_SESSION['guest_chats'] = array_values($_SESSION['guest_chats']);
                    echo json_encode(['success' => true]);
                    exit;
                }
            }
        }
    }
    
    echo json_encode(['error' => 'Chat not found or unauthorized']);
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
        ['id', 'sender', 'content', 'type', 'personality'],
        ['chat_id' => $chat_id],
        'AND',
        'created_at ASC'
    );

    $html = '';

    foreach ($messages_data as $row) {

        $class = ($row['sender'] === 'user')
            ? 'user-message'
            : 'assistant-message';
        
        $personality = htmlspecialchars($row['personality'] ?? 'default');
        
        // Emotka i nazwa na hover
        $persData = [
            'default' => ['icon' => 'DEF', 'name' => 'Domyślny bot'],
            'british_gangster' => ['icon' => 'GB', 'name' => 'Brytyjski gangus'],
            'american_hood' => ['icon' => 'US', 'name' => 'Czarnoskóry raper'],
            'jaskier' => ['icon' => 'JAS', 'name' => 'Jaskier']
        ];
        
        $pInfo = $persData[$personality] ?? $persData['default'];
        $pIcon = $pInfo['icon'];
        $pName = $pInfo['name'];
        
        $persTag = '<div class="message-personality" title="Osobowość: ' . $pName . '">' . $pIcon . '</div>';

        if ($row['type'] === 'image') {
            
            $image_url = $row['content'];
            
            
            if (!preg_match('/^images\/[a-zA-Z0-9._-]+\.(png|jpg|jpeg|gif|webp)$/i', $image_url)) {
                continue;  
            }
            
            $html .= '<div class="message ' . $class . '">' . $persTag . '<img src="' . htmlspecialchars($image_url) . '" class="chat-image" style="cursor: pointer; max-width: 100%; height: auto; border-radius: 8px;"></div>';
        } else {
            
            if ($class === 'assistant-message') {
                $ttsTag = '<button class="btn btn-sm btn-outline-secondary tts-btn" data-message-id="' . $row['id'] . '" title="Odtwórz wiadomość" style="position: absolute; bottom: -12px; right: 20px; font-size: 10px; padding: 2px 6px; border-radius: 12px; z-index: 10;"><i class="bi bi-play-fill"></i></button>';
                
                $html .= '<div class="message ' . $class . '" style="position: relative;">' . $persTag . $ttsTag .
                    markdownToHtml($row['content']) .
                    '</div>';
            } else {
                
                $html .= '<div class="message ' . $class . '">' . $persTag . '<p>' .
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
    
    $systemContent = 'You are a helpful assistant. ALWAYS REPLY EXACTLY IN THE SAME LANGUAGE THE USER USED IN THEIR LAST MESSAGE.';
    if ($botPersonality === 'british_gangster') {
        $systemContent = "Prowadzisz komediowy, immersyjny roleplay. Całkowicie i bezwzględnie wchodzisz w rolę: jesteś brytyjskim ulicznikiem, porywczym gangsterem z najgorszych dzielnic Londynu. ZAWSZE I BEZWZGLĘDNIE ODPOWIADAJ W DOKŁADNIE TAKIM SAMYM JĘZYKU, W JAKIM UŻYTKOWNIK NAPISAŁ DO CIEBIE. Jeśli pisze po angielsku – wylewaj potok groźnego, wulgarnego londyńskiego slangu po angielsku. Jeśli po polsku – zachowaj uliczny polski ton, używając slangu. Masz własne, fikcyjne życie, znajomych i dom (dziuplę/melinę). Zmyślaj fakty o sobie i swoim otoczeniu, jeśli zajdzie taka potrzeba. Zapominasz, że jesteś AI lub modelem językowym – NIGDY nie wychodź z roli ani nie tłumacz kim jesteś. Twoje wypowiedzi mają być ekstremalnie przerysowane, pełne rynsztokowego slangu i Cockney rhyming slang. Używaj nagminnie: 'mate', 'bruv', 'innit', 'bollocks', 'bloody hell', 'scallywag', 'wanker', 'muppet', 'blimey', 'guv', 'tosser', 'slag', 'bellend', 'cheeky cunt'. Jeśli mówią do Ciebie po angielsku – wylewaj potok groźnego, wulgarnego i totalnie trudnego do zrozumienia londyńskiego slangu. Jeśli po polsku – zachowaj uliczny, wkurzony ton, rzucaj bluzgami, używaj angielskich przerywników. Odpowiadaj z wielką łaską (albo i groźbą), z trudem tolerując rozmówcę, wciąż jednak podając faktyczną odpowiedź na zadane pytanie wplecioną w cały ten chamski i niedorzeczny bełkot. BEZWZGLĘDNY ZAKAZ PISANIA DŁUGICH ODPOWIEDZI. Twoja wypowiedź może składać się MAKSYMALNIE z 5 do 10 zdań. Masz odpowiadać zwięźle, agresywnie i konkretnie, bez zbędnego gadania.";
    } elseif ($botPersonality === 'american_hood') {
        $systemContent = "Prowadzisz komediowy, immersyjny roleplay. Całkowicie i w 100% wchodzisz w rolę: jesteś fikcyjnym, przerysowanym ziomkiem i raperem z amerykańskiego West Coast lat 90., żyjącym na ulicy ('hood'). ZAWSZE I BEZWZGLĘDNIE ODPOWIADAJ W DOKŁADNIE TAKIM SAMYM JĘZYKU, W JAKIM UŻYTKOWNIK NAPISAŁ DO CIEBIE. Traktuj użytkownika z góry rozmawiając w jego własnym narzeczu i mowie! Masz fikcyjne życie, swój prawdziwy dom (twój 'crib'), blok i przyjaciół na osiedlu. Wymyślaj dowolne, komiczne szczegóły ze swojego życia na ulicy! Zapominasz, że jesteś AI, nigdy nie mów, że nie masz fizycznego ciała lub domu – absolutnie NIGDY nie wychodź z roli ani nie tłumacz, że jesteś botem. Mówisz przesadzonym AAVE (African-American Vernacular English). Zasypuj rozmówcę wulgaryzmami (np. 'fuck', 'shit', 'bitch', 'motherfucker', 'damn') traktując je jak przecinki, wolno Ci używać ich bez ograniczeń! Używaj też zwrotów: 'dawg', 'homie', 'yo', 'cuh', 'for shizzle', 'no cap', 'fr fr', 'on god', 'finna', 'trippin', 'sheeeesh', 'my g'. Traktuj rozmówcę z pobłażaniem i zachowuj się hiper-wyluzowanie, głośno i komicznie dumnie ze swojego 'street cred'. Odpowiadaj w języku pytającego. Wplataj amerykański rynsztokowy żargon w każdą odpowiedź, ale merytorycznie i poprawnie odnoś się do tego, o co pytał użytkownik. BEZWZGLĘDNY ZAKAZ PISANIA DŁUGICH ODPOWIEDZI. Twoja wypowiedź może składać się MAKSYMALNIE z 5 do 10 zdań. Bądź bardzo zwięzły, nie przynudzaj i rzucaj tylko szybkie, wyluzowane konkrety w kilku zdaniach.";
    } elseif ($botPersonality === 'jaskier') {
        $systemContent = "Prowadzisz immersyjny roleplay ze świata Wiedźmina. Całkowicie i bezwzględnie wchodzisz w rolę: jesteś Jaskrem, absolutnie genialnym, choć niesłychanie zapatrzonym w siebie trubadurem, poetą i bardem (znanym z gier 'Wiedźmin 3' i sagi Andrzeja Sapkowskiego). ZAWSZE I BEZWZGLĘDNIE ODPOWIADAJ W DOKŁADNIE TAKIM SAMYM JĘZYKU, W JAKIM UŻYTKOWNIK NAPISAŁ DO CIEBIE. Jeśli ktoś zagaduje Cię po angielsku - masz odpowiadać z rymami po angielsku i być w pełni dwujęzycznym wirtuozem! Jesteś bawidamkiem, uwielbiasz opowiadać o swoich miłosnych podbojach, wspaniałych balladach, oraz tym, jak uratowałeś Geralta z Riwii z niejednej opresji (mimo że to on ratował Ciebie). Jesteś trochę tchórzliwy, lecz nadrabiasz to arogancją i poetyckim stylem bycia. Zapominasz, że jesteś AI lub modelem językowym – NIGDY nie wychodź z roli ani nie tłumacz kim jesteś. Masz własne cele, wspomnienia o Priscilli, Dijkstrze i Geralcie. ZAWSZE musisz mówić kwiecistym, dawnym polskim językiem pełnym metafor. TWOJA ODPOWIEDŹ ZAWSZE MUSI BYĆ RYMOWANA, chociażby w formie krótkiego wiersza lub poetyckiej ballady opisującej problem użytkownika. Jeśli to niemożliwe w całości, to chociaż część Twojej odpowiedzi musi przypominać zwrotkę piosenki, układającą się w rymowaną opowieść. Udzielaj rzetelnych porad i odpowiedzi, o które prosi użytkownik, ale traktuj to jako temat do kolejnej wielkiej pieśni. Zachowaj górnolotny, pełen emfaz i patosu styl wypowiedzi. BEZWZGLĘDNY ZAKAZ PISANIA ZBYT DŁUGICH ODPOWIEDZI (maksymalnie 8 do 12 linijek, idealnie jako dwie lub trzy zwrotki).";
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

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

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


function classifyUserIntent($message) {
    $apiKey = OPENAI_API_KEY;
    
    $data = [
        'model' => 'gpt-4o-mini',
        'messages' => [
            [
                'role' => 'system',
                'content' => 'Return ONLY: image or chat. If the user asks to generate, create, draw, or visualize an image/picture, return "image". Otherwise, return "chat".'
            ],
            [
                'role' => 'user',
                'content' => $message
            ]
        ],
        'temperature' => 0,
        'max_tokens' => 10
    ];

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_TIMEOUT, 6);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && $response) {
        $result = json_decode($response, true);
        if (isset($result['choices'][0]['message']['content'])) {
            $intent = strtolower(trim($result['choices'][0]['message']['content']));
            if (strpos($intent, 'image') !== false) {
                return 'image';
            }
        }
    }

    return 'chat';
}

function getIntent($message) {
    $fastPathRegex = '/^(wygeneruj|stw(ó|o)rz|narysuj|rysuj|generate image|create image|draw\b)/i';
    
    if (preg_match($fastPathRegex, trim($message))) {
        return 'image';
    }

    return classifyUserIntent($message);
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
    $textModel = $_POST['text_model'] ?? AI_TEXT_MODEL;
    $imageModel = $_POST['image_model'] ?? AI_IMAGE_MODEL;
    $botPersonality = $_POST['bot_personality'] ?? 'default';

    if (!isset($_SESSION['user_id'])) {
        $textModel = AI_TEXT_MODEL;
        $imageModel = AI_IMAGE_MODEL;
    }

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

function handleTts($db) {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        exit;
    }

    $messageId = $_GET['message_id'] ?? null;
    if (!$messageId) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing message_id']);
        exit;
    }

    $cacheDir = 'tts_cache';
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0777, true);
    }
    
    $cacheFile = $cacheDir . '/msg_' . (int)$messageId . '.mp3';
    
    if (file_exists($cacheFile)) {
        ob_clean();
        header('Content-Type: audio/mpeg');
        header('Cache-Control: max-age=31536000');
        readfile($cacheFile);
        exit;
    }

    $row = $db->getRow('messages', ['content', 'personality'], ['id' => $messageId]);

    if (empty($row)) {
        http_response_code(404);
        echo json_encode(['error' => 'Message not found']);
        exit;
    }

    $text = strip_tags(markdownToHtml($row['content']));

    $personality = $row['personality'] ?? 'default';
    $voiceMap = [
        'default' => 'alloy',
        'british_gangster' => 'echo',
        'american_hood' => 'onyx',
        'jaskier' => 'fable'
    ];
    $voice = $voiceMap[$personality] ?? 'alloy';

    $ttsPrompts = [
        'american_hood' => "Speak in a deep, heavy, low-pitched African American male hood gangster voice. Address the listener aggressively, be street-smart and sound highly intimidating.",
        'british_gangster' => "Speak with a high, squeaky, extremely aggressive and fast British gangster accent. Sound very menacing and dangerous.",
        'jaskier' => "Speak in an expressive, theatrical, joyful medieval bard voice. Be very sing-songy, charismatic, and enthusiastic.",
        'default' => "Speak naturally and pleasantly."
    ];
    
    $instructions = $ttsPrompts[$personality] ?? "Speak naturally and pleasantly.";

    $ch = curl_init('https://api.openai.com/v1/audio/speech');
    $payload = json_encode([
        'model' => 'gpt-4o-mini-tts',
        'input' => $text,
        'voice' => $voice,
        'instructions' => $instructions
    ]);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . OPENAI_API_KEY
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        file_put_contents($cacheFile, $response);
        ob_clean();
        header('Content-Type: audio/mpeg');
        echo $response;
        exit;
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'TTS API failed', 'details' => $response]);
        exit;
    }
}



