<?php
  return [
    'statusKeyboard' ==> json_encode([
            "keyboard" => [
                [["text" => "В продаже",],],
                [["text" => "В резерве",],],
                [["text" => "Продано",],]
            ],
            'resize_keyboard' => true,
    ], true),
    'basicKeyboard' => json_encode([
            "keyboard" => [
                [["text" => "Поменять статус",],],
                [["text" => "Поменять цены",],],
            ],
            'resize_keyboard' => true,
    ], true),
  ];
