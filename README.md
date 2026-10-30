# seo.smart.filter — SEO умного фильтра

Модуль Bitrix для ЧПУ и SEO посадочных страниц умного фильтра: красивый URL ↔ технический `/filter/.../apply/`, редиректы, meta/H1/robots, SEO-тексты, подмена URL в `catalog.smart.filter`, sitemap и поиск.

| | |
|---|---|
| ID модуля | `seo.smart.filter` |
| Namespace | `CustomAI\SeoFilter` |
| Партнёр | DVBabanin |
| Версия | 1.0.0 |
| Зависимости | `main`, `iblock`; опционально `search` |

---

## Возможности

- **Редирект** OLD → NEW (301/302) с технического URL фильтра на ЧПУ
- **Внутренний rewrite** NEW → OLD: каталог получает `SMART_FILTER_PATH`, в адресной строке остаётся ЧПУ
- **Подмена URL** в AJAX умного фильтра (`FILTER_URL`, `SEF_SET_FILTER_*`); при выборе фильтра с правилом браузер переходит на NEW_URL
- **SEO**: title, description, keywords, robots, H1, тексты сверху/снизу, хлебные крошки
- **Sitemap** агентом раз в сутки + ручная генерация в настройках
- **Миграция** правил из `dwstroy.seochpulite`
- **Физические лендинги** (`/catalog/keramogranit/index.php`) — rewrite не трогает; SEO-блоки всё равно подставляются по NEW_URL

---

## Установка

1. Модуль лежит в `bitrix/modules/seo.smart.filter/`.
2. Админка → **Marketplace / Настройки продуктов → Установленные решения** → установить `seo.smart.filter`.
3. При установке создаются:
   - тип ИБ `customai_seofilter` («SEO умного фильтра»);
   - инфоблок правил ЧПУ;
   - обработчики событий;
   - агент `SitemapService::agentGenerate()` (раз в 24 ч).
4. Меню: **Контент → SEO умного фильтра** (список правил + настройки модуля).
5. Та же документация доступна во вкладке **Документация** на странице настроек модуля.

Повторная регистрация событий (после переименования/сбоя):

```php
$m = new seo_smart_filter();
$m->UnInstallEvents();
$m->InstallEvents();
```

---

## Правило ЧПУ (элемент ИБ)

| Свойство | Код | Назначение |
|----------|-----|------------|
| Старый URL фильтра | `OLD_URL` | Технический URL, **без домена**. Пример: `/catalog/filter/type-is-keramogranit/apply/` |
| Новый URL (ЧПУ) | `NEW_URL` | Красивый путь. Пример: `/catalog/keramogranit/` или `/catalog/italkero/` |
| Редирект | `REDIRECT` | `301` (по умолчанию) / `302` |
| Robots | `ROBOTS` | `index_follow`, `noindex_follow`, … |
| Другие старые URL | `OTHER_URLS` | Доп. OLD для редиректа/матчинга (множественное) |
| В sitemap | `ADD_TO_SITEMAP` | Добавлять NEW_URL в sitemap |
| В поиск | `SEARCH_INDEX` | Индексировать правило в модуле поиска |
| В хлебные крошки | `ADD_TO_CHAIN` | Добавить пункт в цепочку |
| Родитель крошки | `PARENT_BREADCRUMB_ITEM` | Связь с другим правилом |
| SEO H1 / Title / Keywords / Description | `SEO_*` | HTML-свойства |
| SEO по языку (вкладки) | `SEO_H1_{LANG}`, `SEO_TITLE_{LANG}`, `SEO_KEYWORDS_{LANG}`, `SEO_DESCRIPTION_{LANG}`, `SEO_DESC_TOP_{LANG}`, `SEO_DESC_BOTTOM_{LANG}` | Отдельная вкладка на каждый язык в карточке правила |
| Fallback (скрыты в форме) | `SEO_H1`, `SEO_TITLE`, … / `PREVIEW_TEXT` / `DETAIL_TEXT` | Если перевод пуст |

### Языки SEO-описаний

1. Настройки модуля → вкладка **Языки**: название (для админки) + код lang. По умолчанию только `ru`; для казахского добавьте `kz` и сохраните — иначе вкладки/свойства перевода не создадутся.
2. Сохранить — у правила появятся вкладки по каждому языку с полным набором SEO-полей.
3. **Откуда язык на витрине (не из `<html lang>` напрямую):** как у переключателя РУС/ҚАЗ — `ATM_PUBLIC_LANG`, префикс `/kz/`, `?lang=kz`, `$GLOBALS['lang']`, для AJAX ещё `CUSTOM_LANG`. В разметке казахская версия часто даёт `lang="kk"`, модуль считает это как `kz`.
4. Подстановка текстов: код языка → свойства `SEO_H1_RU` / `SEO_H1_KZ` и т.д. Пусто → общие `SEO_*` / `PREVIEW_TEXT` / `DETAIL_TEXT`. Чужой язык не подмешивается.
5. Пример: русская витрина → вкладка `ru`; `/kz/catalog/.../` → вкладка `kz`.

### Как заполнять OLD_URL

1. В каталоге выберите нужный набор фильтра.
2. Скопируйте URL вида `/catalog/filter/.../apply/` (не `?SMART_FILTER_PATH=` и не `/catalog/0/filter/...`).
3. Вставьте в `OLD_URL` **как есть**, с ведущим слэшем и без домена.
4. В `NEW_URL` укажите целевой ЧПУ (лучше со слешем в конце: `/catalog/italkero/`).

Модуль дополнительно нормализует варианты (`/0/filter/`, query `SMART_FILTER_PATH`, пути с кодом раздела перед `/filter/`), но канонический OLD — `/catalog/filter/{path}/apply/`.

### Физический лендинг vs только правило

| Тип | Пример | Что нужно |
|-----|--------|-----------|
| Физический | `catalog/keramogranit/index.php` + правило NEW=`/catalog/keramogranit/` | Rewrite пропускается; SEO через `getSeoBlocks()`; редирект с filter-URL работает |
| Виртуальный ЧПУ | только правило NEW=`/catalog/italkero/` | Rewrite NEW→OLD обязателен; отдельный `index.php` не нужен |

---

## Как это работает (запрос)

```
Запрос
  │
  ├─ catalog/index.php: prolog_before
  │     → RedirectService: OLD → 301/302 NEW (если матч по OLD/OTHER)
  │     → UrlRewriter: NEW → внутренний OLD + SMART_FILTER_PATH
  │
  ├─ atmPrepareSefFolder('/catalog/')
  │     → разбор /filter/.../apply/ или ELEMENT_CODE
  │
  ├─ header.php → OnBeforeProlog (повтор redirect/rewrite пропускается, если уже wasApplied)
  │     → SeoApplicator::applyViewContent (тексты; без restorePublicUrl — иначе каталог 404)
  │
  ├─ шаблон каталога / smart.filter
  │     → Helper::mapSmartFilterUrls (подмена FILTER_URL → NEW)
  │     → Helper::getSeoBlocks (H1, top, bottom)
  │
  └─ OnEpilog → title/meta/robots (после SetTitle('') каталога)
```

**AJAX умного фильтра:** rewrite на XHR пропускается (`RequestGuard`). `FILTER_AJAX_URL` **не** подменяется на NEW (иначе ломается подгрузка каталога). `FILTER_URL` / `SEF_SET_*` подменяются; JS при SEO-URL делает полный переход на ЧПУ.

---

## Встраивание в сайт

### 1. `catalog/index.php`

До `header.php` уже подключены `prolog_before`, ранний redirect/rewrite и `atmPrepareSefFolder` (см. текущий файл). SEF-шаблон фильтра:

```php
'smart_filter' => 'filter/#SMART_FILTER_PATH#/apply/',
```

Не использовать `#SECTION_ID#/filter/...` — Bitrix генерирует `/catalog/0/filter/...`, которого нет в `urlrewrite.php`.

В `urlrewrite.php` должно быть правило вида:

```php
'#^/catalog/filter/(.+)/apply/?$#' → SMART_FILTER_PATH=$1 → /catalog/index.php
```

### 2. Шаблон `catalog.smart.filter` (`new` / `new_cloud`)

В `result_modifier.php`:

```php
if (\Bitrix\Main\Loader::includeModule('seo.smart.filter')) {
    \CustomAI\SeoFilter\Helper::mapSmartFilterUrls($arParams, $arResult);
}
```

В `script.js` шаблона `new`: при ответе AJAX, если `FILTER_URL` — SEO-ЧПУ (нет `/filter/` и нет `SMART_FILTER_PATH`), выполняется `location.href` на этот URL.

### 3. Шаблон каталога (`sections.php`)

```php
$seoBlocks = ['h1' => '', 'top' => '', 'bottom' => ''];
if (\Bitrix\Main\Loader::includeModule('seo.smart.filter')) {
    $seoBlocks = \CustomAI\SeoFilter\Helper::getSeoBlocks();
}
$catalogH1 = $seoBlocks['h1'] !== '' ? $seoBlocks['h1'] : 'Каталог';
// вывести $seoBlocks['top'] / ['bottom'] в .catalog-filter-seo
```

### 4. Физические лендинги

`local/include/catalog_type_landing.php` + `catalog/{code}/index.php` с `$atmLandingSmartFilter`. Правило с тем же NEW_URL и OLD = `/catalog/filter/{smart}/apply/`.

---

## Публичный API (`Helper`)

```php
use CustomAI\SeoFilter\Helper;

Helper::mapSmartFilterUrls($arParams, $arResult);  // подмена URL фильтра
Helper::restorePublicUrl();                        // вернуть NEW в REQUEST_URI
Helper::getSeoBlocks();                            // ['h1','top','bottom']
Helper::getCurrentRule();                          // ?SeoRule
Helper::createIblock(true);                        // новый ИБ правил
Helper::migrateFromDwstroy(?$iblockId);            // миграция из dwstroy
Helper::generateSitemap(?$domain, ?$filename);     // sitemap XML
Helper::getSitemapPaths(?$siteId);                 // список NEW_URL для sitemap
```

Устаревшие алиасы: `returnUrl()`, `returnNewUrl()` — не использовать в новом коде.

---

## Структура модуля

```
seo.smart.filter/
├── include.php          # автозагрузка классов
├── options.php          # настройки: ИБ, миграция, sitemap
├── install/             # установка, события, агент
└── lib/
    ├── Helper.php       # фасад для шаблонов
    ├── Events.php       # обработчики Bitrix
    ├── Dto/SeoRule.php
    ├── Repository/RuleRepository.php
    ├── Schema/IblockInstaller.php
    └── Service/
        ├── RedirectService.php   # OLD → NEW
        ├── UrlRewriter.php       # NEW → OLD
        ├── UrlNormalizer.php     # канонизация и варианты URL
        ├── FilterUrlMapper.php   # подмена URL в smart.filter
        ├── SeoApplicator.php     # meta, тексты, крошки
        ├── Sanitizer.php
        ├── RequestGuard.php      # admin / ajax / POST
        ├── MigrationService.php
        └── SitemapService.php
```

---

## Настройки модуля

**Настройки → Настройки продукта → Настройки модулей → seo.smart.filter** (или пункт меню «Настройки»):

1. **Инфоблок** — создать дополнительный ИБ правил.
2. **Миграция** — перенос из `dwstroy.seochpulite`.
3. **Sitemap** — домен, имя файла, ручная генерация.

Файл sitemap по умолчанию пишется агентом раз в сутки.

---

## Типовые сценарии

### Редирект с filter-URL не срабатывает

1. Есть ли активное правило с `OLD_URL`, совпадающим с фильтром (или тот же `SMART_FILTER_PATH`)?
2. Заполнен ли `NEW_URL`?
3. Проверка: `/catalog/filter/type-is-keramogranit/apply/` → должен уйти на `/catalog/keramogranit/` (если правило есть).
4. События установлены? Меню «SEO умного фильтра» видно?

### При фильтрации URL не меняется на ЧПУ

1. В консоли AJAX `FILTER_URL` уже NEW? Если да — обновите `script.js` шаблона `new` (навигация на SEO-URL).
2. Если `FILTER_URL` всё ещё `/filter/...` — `mapSmartFilterUrls` не подключён или OLD не матчится.
3. Не подменяйте вручную `FILTER_AJAX_URL` на NEW.

### H1/title сбрасываются в «Каталог»

Meta применяется на **OnEpilog** после `SetTitle('')` в `catalog/index.php`. Если epilog-обработчик не зарегистрирован — переустановите события.
