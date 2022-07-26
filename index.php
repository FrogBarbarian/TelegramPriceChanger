<?php   

function handler ($event, $context)
{
    require_once 'functions.php'; //Подключаем функции
   
    $vars = require 'variables.php'; //Подключаем важные переменные

    $token = $vars['token']; //Токен бота
    $channelId = $vars['channelId']; //ID телеграм канала
    $curators = $vars['curators']; //Список кураторов бота

    $data = json_decode($event['body'], true); //Декодируем json данные запроса в массив php
    $chatId = $data['message']['from']['id']; //ID чата запроса
    $text = $data['message']['text']; //Текст запроса
    $reply = ''; //Ответ пользователю

    //Клавиатура при смене статуса
    $statusKeyboard = json_encode([
            "keyboard" => [
                [["text" => "В продаже",],],
                [["text" => "В резерве",],],
                [["text" => "Продано",],]
            ],
            'resize_keyboard' => true,
    ], true);

    //Базовая клавиатура
    $basicKeyboard = json_encode([
            "keyboard" => [
                [["text" => "Поменять статус",],],
                [["text" => "Поменять цены",],],
            ],
            'resize_keyboard' => true,
    ], true);

    //Проверка на валидность написавшего
    if (array_search($chatId, $curators) === false) {
        $reply = "<b>Вы не являетесь куратором бота</b>";
        sendMessage($token, $reply, $chatId);
        $text = 'continue';
    }

    //Место старта основного кода              
    //Проверяем установлено состояние ввода или это первичное сообщение
    if (isset($_POST['state']) && $_POST['id'] == $chatId) {
        switch ($_POST['state']) {
            //Если пользователь ввел /status
            case 'status':
                $article = preg_replace('/^0+/', '', $text); //Отсекаем нули вначале артикула
                //Проверяет введенный артикул на существование на канале
                switch (getMessageId($article)) {
                    case -1:
                        $reply = "<b>Артикул введен неверно, либо товар не найден</b>";
                        sendMessage($token, $reply, $chatId, $basicKeyboard);
                        $text = 'end';
                        break;
                    default:
                        $reply = "<b>Что с товаром?</b>";
                        sendMessage($token, $reply, $chatId, $statusKeyboard);
                        setState('entered status', $chatId, $article);
                        $text = 'continue';
                        break;
                }
                break;
            //Пользователь ввел валидный артикул после /status, проверяем последующий ввод
            case 'entered status':
                $messageId = getMessageId($_POST['text']);
                switch ($text) {
                    case 'В продаже':
                        $newText = changeMessageStatus($_POST['text'], 0);
                        if ($newText === false) {
                            $text = 'invalid status';
                        } else {
                            changeMessage($token, $channelId, $messageId, $newText);
                            $text = 'succsess status';
                        }
                        break;
                    case 'В резерве':
                        $newText = changeMessageStatus($_POST['text'], 1);
                        if ($newText === false) {
                            $text = 'invalid status';
                        } else {
                            changeMessage($token, $channelId, $messageId, $newText);
                            $text = 'succsess status';
                        }
                        break;
                    case 'Продано':
                        $newText = changeMessageStatus($_POST['text'], 2);
                        if ($newText === false) {
                            $text = 'invalid status';
                        } else {
                            changeMessage($token, $channelId, $messageId, $newText);
                            $text = 'succsess status';
                        }
                        break;
                    default:
                        $reply = "<b>Введены неверные данные</b>";
                        sendMessage($token, $reply, $chatId, $basicKeyboard);
                        $text = 'end';
                        break;
                }
                setState($text, $chatId, $messageId);
                break;
            //Если пользователь выбрал изменить цены на канале
            case 'prices':
                $pricesArray = explode("\n", $text); //Разбиваем ожидаемые данные
                $_POST['badways'] = ''; //Сохраняет артикулы, которые не найдены на канале
                $_POST['badcount'] = 0; //Количество не найденых товаров
                for ($i = 0; $i < count($pricesArray); $i++) {
                    $res = priceChanger($pricesArray[$i]);
                    if ($res[1] == false) {
                        $_POST['badways'] .= $res[0] . ' ';
                        $_POST['badcount'] += 1;
                    } else {
                        changeMessage($token, $channelId, $res[0], $res[1]);
                    }
                }
                if ($_POST['badways'] !== '') {
                    $reply = $_POST['badways'] . "\nЭти товары не найдены или что то пошло не так (<b>" . $_POST['badcount'] . " шт</b>)";
                    sendMessage($token, $reply, $chatId, $basicKeyboard);
                } else {
                    $reply = 'Все хорошо, все цены изменены';
                    sendMessage($token, $reply, $chatId, $basicKeyboard);
                }
                $text = 'end';
                break;
        }
    }

    //Когда пользователь нажал на одну из первоначальных кнопок (или вручную ввел)
    switch ($text) {
        case 'Поменять статус':
            $text = '/status';
            break;
        case 'Поменять цены':
            $text = '/prices';
            break;
        default:
            break;        
    }

    //Базовые обработки пользовательского ввода
    switch ($text) {
        case '/start':
            $reply = "Данный бот предназначен для автоматизации работы с телеграм каналом ОГО Уценка";
            sendMessage($token, $reply, $chatId, $basicKeyboard);
            unsetState();
            break;
        case '/help':
            $reply = "<b>Список команд:</b>\n/status - поменять стаус поста\n/prices - изменить цены";
            sendMessage($token, $reply, $chatId, $basicKeyboard);
            unsetState();
            break;
        case '/status':
            $reply = "<b>Введите артикул без пробелов</b>";
            sendMessage($token, $reply, $chatId, json_encode(['remove_keyboard' => true], true));
            setState('status', $chatId, $text);
            break;
        case '/prices':
            $reply = "<b>Чтобы изменить цены напишите данные в таком виде:</b>\n<i>358777 15000 21000\n360370 1540 3250</i>\nАртикул   дисконт цена   полная цена\n<b>Рекомендую не более 20 ценников за раз</b>";
            sendMessage($token, $reply, $chatId, json_encode(['remove_keyboard' => true], true));
            setState('prices', $chatId, $text);
            break;
        case 'end':
            unsetState();
            break;
        case 'continue':
            break;
        case 'invalid status':
            $reply = "<b>Не удалось присвоить статус</b>";
            sendMessage($token, $reply, $chatId, $basicKeyboard);
            unsetState();
            break;
        case 'succsess status':
            $reply = "<a href=\"https://t.me/testforogobot/$messageId\"><b>Статус успешно присвоен</b></a>";
            sendMessage($token, $reply, $chatId, $basicKeyboard);
            unsetState();
            break;
        default:
            $reply = "<b>Неверная команда</b>\nЯ могу /status поменять <b>статус</b> поста\n/prices изменить <b>цены</b>";
            sendMessage($token, $reply, $chatId);
            unsetState();
            break;
    }

    return [
            'statusCode' => 200,
            'body' => '',
            'isBase64Encoded'=> false,
    ];
}
