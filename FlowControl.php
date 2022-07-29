<?php

  namespace core/FlowControl.php;
  
  /**
  * Управляет параметрами для корректного потока общения с пользователем
  */
  class FlowControl()
  {
    /**
    * Задает параметры в $_POST для дальнейшего взаимодействия
    * @param $state новый статус бота
    * @param $id ID чата написавшего
    * @param $text текст от пользователя, если требуется в контексте
    * @return void
    */
    public function setState($state, $id, $text = '') : void
    {
      $_POST['state'] = $state;
      $_POST['id'] = $id;
      $_POST['text'] = $text;
    }
    
    /**
    * Очищает кастомные параметры $_POST
    * @return void
    */
    public function unsetState() : void
    {
      unset($_POST['state']);
      unset($_POST['id']);
      unset($_POST['text']);
    }
  }
