<?php

/**
* Отправляет сообщение через бота пользователю 
* @param $token токен бота
* @param $text текст бота
* @param $chatId ID чата кому отправляется сообщение
* @param $replyMarkup подключение клавиатуры, если требуется
* @return void
*/
function sendMessage($token, $text, $chatId, $replyMarkup = '') : void
{
    $ch = curl_init();
    $chPost = [
                CURLOPT_URL => 'https://api.telegram.org/bot' . $token . '/sendMessage',
                CURLOPT_POST => TRUE,
                CURLOPT_RETURNTRANSFER => TRUE,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_POSTFIELDS => [
                    'chat_id' => $chatId,
                    'parse_mode' => 'HTML',
                    'text' => $text,
                    'reply_markup' => $replyMarkup,
                ],
            ];

        curl_setopt_array($ch, $chPost);
        curl_exec($ch);
}

/**
* Задает параметры в $_POST для дальнейшего взаимодействия
* @param новый статус бота
* @param ID чата написавшего
* @param текст от пользователя, если требуется
* @return void
*/
function setState($state, $id, $text = '',) : void
{
    $_POST['state'] = $state;
    $_POST['id'] = $id;
    $_POST['text'] = $text;
}

/**
* Очищает кастомные параметры $_POST
* @return void
*/
function unsetState() : void
{
    unset($_POST['state']);
    unset($_POST['id']);
    unset($_POST['text']);
}

/**
* Меняет сообщение в телеграм канале
* @param $token токен бота
* @param $channelId ID телеграм канала
* @param $messageId ID сообщения
* @param $newText новый текст для сообщения
* @return void
*/
function changeMessage($token, $channelId, $messageId, $newText) : void
{
    $ch = curl_init();
    $chPost = [
                CURLOPT_URL => 'https://api.telegram.org/bot' . $token . '/editMessageCaption',
                CURLOPT_POST => TRUE,
                CURLOPT_RETURNTRANSFER => TRUE,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_POSTFIELDS => [
                    'chat_id' => $channelId,
                    'message_id' => $messageId,
                    'parse_mode' => 'HTML',
                    'caption' => $newText,
                ],
            ];

        curl_setopt_array($ch, $chPost);
        curl_exec($ch);
}

/**
* Получает HTML код страницы с искомым товаром
* @param $article искомый артикул
* @return string код HTML страницы
*/
function getHtmlCode(string $article) : string
{
    $url = 'https://t.me/s/channelURL?q=' . $article;
    $request = curl_init();
    $requestOptions = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_HEADER => 1,
        ];
    curl_setopt_array($request, $requestOptions);
    $out = curl_exec($request);
    curl_close($request);
    return $out;
}

/**
* Получает ID сообщения телеграм канала из HTML кода заданной страницы
* @param $article артикул товара из сообщения пользователя
* @return ID сообщения в телеграм канале, если не найден товар, то возвращает -1
*/
function getMessageId($article) : int
{
    preg_match('/' . $article . '&before=\K\d+/', getHtmlCode($article), $matches);
    $res = (!isset($matches[0]) ? -1 : $matches[0] - 1);
    return $res;
}

/**
* Получает текст сообщения телеграм канала из HTML кода заданной страницы
* @param $article артикул товара из сообщения пользователя
* @return string текст сообщения в телеграм канале
*/
function getMessageText($article) : string
{  
    $badWords = [
        '/<mark class="highlight">/',
        '/<\/mark>/',
    ];
    preg_match('/js-message_text" dir="auto">(.*)<\/div>/', getHtmlCode($article), $matches);
    $messageText = preg_replace($badWords, '', $matches[1]);
    $messageText = preg_replace('/\&gt;/', '&#62;', $messageText);
    $messageText = preg_replace('/\&lt;/', '&#60;', $messageText);
    $messageText = preg_replace('/<br\/>/', "\n", $messageText);
    return $messageText;
}


/**
* Меняет статус товара
* @param $article артикул товара
* @param $status новый статус
* @return string полный текст поста | false если условие выполнения не валидно
*/
function changeMessageStatus($article, $status) : bool|string
{
    $messageText = getMessageText($article); //Получаем текст поста
    
    $hasUpdStatus = strripos($messageText, 'UPD'); //Проверка на наличие какого либо статуса
    $hasReserveStatus = strripos($messageText, 'В РЕЗЕРВЕ'); //Проверка на наличие статуса резерва
    $hasSoldStatus = strripos($messageText, 'ПРОДАНО'); //Проверка на наличие статуса продажи

    switch ($status) {
        case 0: //Если пользователь ввел "В продаже"
            preg_match("/<b>UPD: В РЕЗЕРВЕ<\/b>\\n\\n(.+)/s", $messageText, $matches);
            return ($hasReserveStatus !== false) ? $matches[1] : false;
            break;
        case 1: //Если пользователь ввел "В резерве"
            return ($hasUpdStatus === false) ? "<b>UPD: В РЕЗЕРВЕ</b>\n\n" . $messageText : false; 
            break;
        case 2: //Если пользователь ввел "Продано"
            if ($hasSoldStatus) {
                return false;
            } else {
                $messageText = preg_replace('/(р\..+&#60;)/s', 'р.</b>', $messageText); //Убираем из поста ">ЗАРЕЗЕРВИРОВАТЬ<"
                if ($hasReserveStatus) {
                    $messageText = preg_replace('/В РЕЗЕРВЕ/', "ПРОДАНО", $messageText);
                    return $messageText;
                } else {
                    return "<b>UPD: ПРОДАНО</b>\n\n" .  $messageText; 
                }
            }
            break;
    }
}

/**
* Меняет цены на канале
* @param string $data данные в виде артикул цена дисконт цена нового
* @return array [$article, false] артикул и неудавшейся стейтмент
* @return array [$messageId, $messageText] ID сообщения и текст сообщения
*/
function priceChanger(string $data) : array
{
    preg_match_all('/\d+/', $data, $matches);
    $article = preg_replace('/^0+/', '', $matches[0][0]);
    $discountPrice = $matches[0][1];
    $fullPrice = $matches[0][2];

    $messageId = getMessageId($article);
    
    switch ($messageId) {
        case -1:
            return [$article, false]; //Если сообщение не найдено
            break;
        default:
            $messageText = getMessageText($article);
            $hasSoldStatus = strripos($messageText, 'ПРОДАНО');
            if ($hasSoldStatus | is_null($discountPrice) | is_null($fullPrice) | $fullPrice <= $discountPrice) {
                return [$article, false]; //Если товар уже продан или неверный ввод цен
            } else {   
                $messageText = preg_replace('/<s>.+р\./', '<s>' . $fullPrice . '</s> ' . $discountPrice . ' р.',  $messageText);
                return [$messageId, $messageText]; //Если сообщение найдено
            }
            break;
    }
}
