<?php
/**
*  astedit-lang.ru.php - include localized strings for astedit.php
* language : Russian, codepage: UTF-8
* modified 2025-12-05
*/

class AsteditLocalization {
    static $strings = [
      'title_field'=>'Поле',
      'title_type_length'=>'Тип, длина',
      'title_description'=>'Описание, комментарии',
      'sql_error_1146' => 'Таблица {tablename} отсутствует в БД. Обратитесь к администратору',
    ];
}

$ast_tips = [
  'charset' => 'UTF-8'
  ,'title_delete' => 'Удаление'
  ,'tipdelete' => 'Удалить запись'
  ,'tipedit' => 'Редактировать'
  ,'tipadd' => 'Добавить'
  ,'tipresetfilter' => 'Сбросить' # фильтр
  ,'setfilter' => 'Отобрать'
  ,'deleteprompt' => 'Подтверждаете удаление записи {id} ?'
  ,'titleadd' => 'Добавление записи'
  ,'buttadd' => 'Добавить запись'
  ,'titleedit' => 'Редактирование записи'
  ,'titleviewrecord' => 'Просмотр записи'
  ,'buttsave' => 'Сохранить значения'
  ,'buttcancel' => 'Отменить ввод'

  ,'rec_deleted' => 'запись удалена'
  ,'rec_delerror' => 'ошибка при удалении'
  ,'tobrowse' => 'Вернуться в список'
  ,'sql_error' => 'ошибка при выполнении запроса'
  ,'done' => 'выполнено'
  ,'updated' => 'Данные сохранены'
  ,'title_newrec' => '(Новая)'
  ,'clone_record' => 'Клонировать'
  ,'title_clone_record' => 'Клонировать запись'
  ,'table_frozen' => 'Изменения данных временно заблокированы!'
  ,'link_viewrecord' => 'Просмотреть'
  ,'title_showsearchbar' => 'Показать/спрятать панель поиска'
  ,'help' => 'Помощь'
  ,'tiphelpedit' => 'Показать инструкцию по редактированию данных'
  ,'help_page_notfound' => 'Запрошенная страница помощи не найдена.'
  ,'err_readonly_mode' => 'Вы находитесь в режиме ТОЛЬКО ДЛЯ ЧТЕНИЯ!'
  ,'err_record_cant_be_deleted' => 'Запись не может быть удалена, т.к. используется в других данных'
  ,'clone_record' => 'Клонировать запись'
  ,'tipclone' => 'Клонирование записи',
];
