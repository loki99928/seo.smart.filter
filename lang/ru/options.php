<?php
$MESS['CUSTOMAI_SEOFILTER_OPTIONS_PAGE_TITLE'] = 'SEO умного фильтра';
$MESS['CUSTOMAI_SEOFILTER_OPTIONS_TAB'] = 'Инфоблок правил';
$MESS['CUSTOMAI_SEOFILTER_OPTIONS_TITLE'] = 'Создание инфоблока ЧПУ';
$MESS['CUSTOMAI_SEOFILTER_CREATE_BTN'] = 'Создать инфоблок';
$MESS['CUSTOMAI_SEOFILTER_CREATE_HINT'] = 'Нужен отдельный инфоблок на сайт (или набор правил), чтобы разделять ЧПУ и sitemap.';
$MESS['CUSTOMAI_SEOFILTER_CREATE_OK'] = 'Инфоблок создан';
$MESS['CUSTOMAI_SEOFILTER_CREATE_ERROR'] = 'Не удалось создать инфоблок';
$MESS['CUSTOMAI_SEOFILTER_EDIT_IBLOCK'] = 'Открыть настройки инфоблока';
$MESS['CUSTOMAI_SEOFILTER_MODULE_MISSING'] = 'Модуль seo.smart.filter не установлен';

$MESS['CUSTOMAI_SEOFILTER_MIGRATE_TAB'] = 'Миграция';
$MESS['CUSTOMAI_SEOFILTER_MIGRATE_TITLE'] = 'Перенос из dwstroy.seochpulite';
$MESS['CUSTOMAI_SEOFILTER_MIGRATE_HINT'] = 'Скопирует активные правила из инфоблоков типа dwstroy_seochpulite в customai_seofilter. Уже существующие NEW_URL будут пропущены. Родительские крошки переназначаются.';
$MESS['CUSTOMAI_SEOFILTER_MIGRATE_BTN'] = 'Перенести правила';
$MESS['CUSTOMAI_SEOFILTER_MIGRATE_CONFIRM'] = 'Запустить миграцию правил из dwstroy.seochpulite?';
$MESS['CUSTOMAI_SEOFILTER_MIGRATE_RESULT'] = 'Миграция завершена. Создано: #CREATED#, пропущено: #SKIPPED#.';
$MESS['CUSTOMAI_SEOFILTER_OPEN_ELEMENTS'] = 'Открыть список правил';

$MESS['CUSTOMAI_SEOFILTER_SITEMAP_TAB'] = 'Sitemap';
$MESS['CUSTOMAI_SEOFILTER_SITEMAP_TITLE'] = 'Карта сайта ЧПУ';
$MESS['CUSTOMAI_SEOFILTER_SITEMAP_DOMAIN'] = 'Домен (https://...)';
$MESS['CUSTOMAI_SEOFILTER_SITEMAP_FILENAME'] = 'Имя файла в корне сайта';
$MESS['CUSTOMAI_SEOFILTER_SITEMAP_HINT'] = 'В sitemap попадают активные правила с включённым «Добавлять в карту сайта». Также агент обновляет файл раз в сутки. URL подхватывает и генератор /local/php_interface/classes/SitemapGenerator.php.';
$MESS['CUSTOMAI_SEOFILTER_SITEMAP_BTN'] = 'Сгенерировать sitemap';
$MESS['CUSTOMAI_SEOFILTER_SITEMAP_RESULT'] = 'Sitemap готов: #FILE# (#COUNT# URL).';
$MESS['CUSTOMAI_SEOFILTER_SITEMAP_ERROR'] = 'Ошибка генерации sitemap';

$MESS['CUSTOMAI_SEOFILTER_DOCS_TAB'] = 'Документация';
$MESS['CUSTOMAI_SEOFILTER_DOCS_TITLE'] = 'Документация модуля seo.smart.filter';
$MESS['CUSTOMAI_SEOFILTER_DOCS_SOURCE'] = 'Содержимое файла README.md модуля (обновляется вместе с кодом).';

$MESS['CUSTOMAI_SEOFILTER_LANG_TAB'] = 'Языки';
$MESS['CUSTOMAI_SEOFILTER_LANG_TITLE'] = 'Языки SEO-описаний';
$MESS['CUSTOMAI_SEOFILTER_LANG_HINT'] = 'Укажите языки: название + код (ru, kz). Код должен совпадать с языком витрины. После сохранения у правила появятся вкладки — откройте карточку заново (Ctrl+F5).<br><br><b>Откуда берётся язык на витрине:</b> не из атрибута &lt;html lang&gt; напрямую. Модуль смотрит режим сайта так же, как переключатель РУС/ҚАЗ: <code>ATM_PUBLIC_LANG</code>, префикс URL <code>/kz/</code>, <code>?lang=kz</code> / <code>$GLOBALS[\'lang\']</code>, для AJAX — <code>CUSTOM_LANG</code>. Русская версия → поля <code>SEO_*_RU</code>; казахская (<code>/kz/</code>, в HTML часто <code>lang="kk"</code>) → <code>SEO_*_KZ</code>. Если перевод пуст — общие SEO-поля / PREVIEW / DETAIL.';
$MESS['CUSTOMAI_SEOFILTER_LANG_NAME'] = 'Название языка';
$MESS['CUSTOMAI_SEOFILTER_LANG_CODE'] = 'Код lang';
$MESS['CUSTOMAI_SEOFILTER_LANG_ADD'] = 'Добавить язык';
$MESS['CUSTOMAI_SEOFILTER_LANG_REMOVE'] = 'Удалить';
$MESS['CUSTOMAI_SEOFILTER_LANG_SAVE'] = 'Сохранить языки';
$MESS['CUSTOMAI_SEOFILTER_LANG_SAVED'] = 'Языки сохранены. Создано свойств описаний: #CREATED# (инфоблоков: #IBLOCKS#).';
$MESS['CUSTOMAI_SEOFILTER_LANG_EMPTY'] = 'Добавьте хотя бы один язык с названием и кодом.';
