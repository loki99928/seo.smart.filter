<?php

namespace Atmosfera\SeoFilter;

use Atmosfera\SeoFilter\Repository\RuleRepository;
use Atmosfera\SeoFilter\Schema\IblockInstaller;
use Atmosfera\SeoFilter\Service\RedirectService;
use Atmosfera\SeoFilter\Service\RequestGuard;
use Atmosfera\SeoFilter\Service\Sanitizer;
use Atmosfera\SeoFilter\Service\SeoApplicator;
use Atmosfera\SeoFilter\Service\UrlRewriter;
use Bitrix\Iblock\IblockTable;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;

final class Events
{
    /**
     * Контент → SEO умного фильтра
     *
     * @param array $globalMenu
     * @param array $moduleMenu
     */
    public static function onBuildGlobalMenu(&$globalMenu, &$moduleMenu): void
    {
        if (!Loader::includeModule('seo.smart.filter')) {
            return;
        }

        Loc::loadMessages(__FILE__);

        $text = Loc::getMessage('ATMOSFERA_SEOFILTER_MENU_TEXT') ?: 'SEO умного фильтра';
        $title = Loc::getMessage('ATMOSFERA_SEOFILTER_MENU_TITLE')
            ?: 'ЧПУ, мета-теги и SEO-тексты умного фильтра';

        $settingsUrl = 'settings.php?lang=' . LANGUAGE_ID . '&mid=seo.smart.filter';
        $url = $settingsUrl;
        $items = [];

        $iblockIds = (new RuleRepository())->getIblockIds();
        $many = count($iblockIds) > 1;
        if ($iblockIds !== []) {
            foreach ($iblockIds as $iblockId) {
                $iblockId = (int)$iblockId;
                if ($iblockId <= 0) {
                    continue;
                }
                $listUrl = 'iblock_element_admin.php?IBLOCK_ID=' . $iblockId
                    . '&type=' . rawurlencode(IblockInstaller::TYPE_ID)
                    . '&lang=' . LANGUAGE_ID
                    . '&find_section_section=0';
                if ($url === $settingsUrl) {
                    $url = $listUrl;
                }
                $label = Loc::getMessage('ATMOSFERA_SEOFILTER_MENU_RULES') ?: 'Правила ЧПУ';
                if ($many && Loader::includeModule('iblock')) {
                    $ib = \CIBlock::GetByID($iblockId)->Fetch();
                    if (!empty($ib['NAME'])) {
                        $label = (string)$ib['NAME'];
                    }
                }
                $items[] = [
                    'text' => $label,
                    'title' => $label,
                    'url' => $listUrl,
                    'more_url' => [
                        'iblock_element_edit.php?IBLOCK_ID=' . $iblockId,
                        'iblock_element_admin.php?IBLOCK_ID=' . $iblockId,
                    ],
                ];
            }
        }

        $items[] = [
            'text' => Loc::getMessage('ATMOSFERA_SEOFILTER_MENU_SETTINGS') ?: 'Настройки',
            'title' => Loc::getMessage('ATMOSFERA_SEOFILTER_MENU_SETTINGS') ?: 'Настройки',
            'url' => $settingsUrl,
            'more_url' => ['settings.php?mid=seo.smart.filter'],
        ];

        $moduleMenu[] = [
            'parent_menu' => 'global_menu_content',
            'section' => 'seo_smart_filter',
            'sort' => 480,
            'text' => $text,
            'title' => $title,
            'url' => $url,
            'icon' => 'iblock_menu_icon_types',
            'page_icon' => 'iblock_menu_icon_types',
            'items_id' => 'menu_seo_smart_filter',
            'items' => $items,
        ];
    }

    public static function onBeforeProlog(): void
    {
        if (RequestGuard::isAdmin()) {
            self::ensureAdminRuleElementForm();

            return;
        }

        // Redirect OLD → NEW before rewriting internals for the catalog
        if ((new RedirectService())->handle()) {
            return;
        }

        UrlRewriter::applyInternalFilterUrl();
        // Texts for templates; title/meta applied later on Epilog (after catalog SetTitle(''))
        (new SeoApplicator())->applyViewContent();
    }

    /**
     * Rebuild language SEO tabs every time a rule is opened in admin
     * (personal form settings otherwise keep dumping SEO fields onto «Правило»).
     */
    private static function ensureAdminRuleElementForm(): void
    {
        static $done = false;
        if ($done) {
            return;
        }

        global $APPLICATION;
        $page = is_object($APPLICATION) ? (string)$APPLICATION->GetCurPage(false) : '';
        if (
            $page !== '/bitrix/admin/iblock_element_edit.php'
            && mb_strpos($page, '/bitrix/admin/iblock_element_edit') === false
        ) {
            return;
        }

        $iblockId = (int)($_REQUEST['IBLOCK_ID'] ?? $_GET['IBLOCK_ID'] ?? 0);
        if ($iblockId <= 0 || !Loader::includeModule('iblock')) {
            return;
        }

        $iblock = \CIBlock::GetByID($iblockId)->Fetch();
        if (!is_array($iblock) || (string)($iblock['IBLOCK_TYPE_ID'] ?? '') !== IblockInstaller::TYPE_ID) {
            return;
        }

        $done = true;
        (new IblockInstaller())->syncLanguageDescriptionProperties();
    }

    public static function onEpilog(): void
    {
        // After catalog/index.php SetTitle('') — restore SEO title/meta
        (new SeoApplicator())->applyDocumentMeta();
    }

    public static function onEndBufferContent(&$content): void
    {
        if (RequestGuard::isAdmin() || !is_string($content)) {
            return;
        }

        $content = (new SeoApplicator())->replacePlaceholders($content);
    }

    public static function onAfterElementSave(array &$arFields): void
    {
        if (empty($arFields['ID']) || empty($arFields['IBLOCK_ID'])) {
            return;
        }

        if (!Loader::includeModule('iblock')) {
            return;
        }

        $iblock = IblockTable::getList([
            'filter' => [
                '=ID' => (int)$arFields['IBLOCK_ID'],
                '=IBLOCK_TYPE_ID' => IblockInstaller::TYPE_ID,
            ],
            'select' => ['ID'],
            'limit' => 1,
        ])->fetch();

        if (!$iblock) {
            return;
        }

        $res = \CIBlockElement::GetList(
            ['ID' => 'ASC'],
            [
                'ID' => (int)$arFields['ID'],
                'IBLOCK_ID' => (int)$arFields['IBLOCK_ID'],
            ]
        );

        if (!$ob = $res->GetNextElement()) {
            return;
        }

        $fields = $ob->GetFields();
        $properties = $ob->GetProperties();
        $oldUrl = (string)($properties['OLD_URL']['VALUE'] ?? '');
        $newUrl = (string)($properties['NEW_URL']['VALUE'] ?? '');

        if ($oldUrl !== '') {
            $decoded = urldecode($oldUrl);
            if (strcmp($oldUrl, $decoded) !== 0) {
                \CIBlockElement::SetPropertyValuesEx(
                    (int)$fields['ID'],
                    (int)$fields['IBLOCK_ID'],
                    ['OLD_URL' => $decoded]
                );
            }
        }

        if ($newUrl !== '' && (string)$fields['CODE'] !== $newUrl) {
            $element = new \CIBlockElement();
            $element->Update((int)$fields['ID'], ['CODE' => $newUrl]);
        }

        RuleRepository::resetRequestCache();
    }

    public static function onBeforeSearchIndex(array $arFields): array
    {
        if (($arFields['MODULE_ID'] ?? '') !== 'iblock' || !Loader::includeModule('iblock')) {
            return $arFields;
        }

        $repo = new RuleRepository();
        $iblockIds = $repo->getIblockIds($arFields['SITE_ID'] ?? null);
        $param2 = (int)($arFields['PARAM2'] ?? 0);

        if (!in_array($param2, $iblockIds, true)) {
            return $arFields;
        }

        $res = \CIBlockElement::GetList(
            ['SORT' => 'ASC'],
            [
                'IBLOCK_ID' => $param2,
                'ACTIVE' => 'Y',
                'ID' => (int)$arFields['ITEM_ID'],
                '!PROPERTY_SEARCH_INDEX' => false,
                '!PROPERTY_SEO_H1' => false,
                '!PROPERTY_NEW_URL' => false,
            ],
            false,
            false,
            ['ID', 'IBLOCK_ID', 'PROPERTY_SEO_H1', 'PROPERTY_NEW_URL']
        );

        if ($data = $res->Fetch()) {
            $arFields['URL'] = (string)$data['PROPERTY_NEW_URL_VALUE'];
            $h1 = Sanitizer::propertyValue($data['PROPERTY_SEO_H1_VALUE'] ?? null);
            $arFields['TITLE'] = $h1;
            $arFields['BODY'] = $h1;
        } else {
            unset($arFields['TITLE'], $arFields['BODY']);
        }

        return $arFields;
    }
}
