<?php

namespace Atmosfera\SeoFilter\Schema;

use Atmosfera\SeoFilter\Service\LanguageConfig;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\SiteTable;
use Bitrix\Main\Type\DateTime;

final class IblockInstaller
{
    public const TYPE_ID = 'atmosfera_seofilter';

    private static function loadLang(): void
    {
        Loc::loadMessages(__FILE__);
    }

    public function ensureType(): bool
    {
        self::loadLang();
        if (!Loader::includeModule('iblock')) {
            return false;
        }

        $typeName = Loc::getMessage('ATMOSFERA_SEOFILTER_IBLOCK_TYPE_NAME') ?: 'SEO умного фильтра';
        $lang = [
            'ru' => [
                'NAME' => $typeName,
                'SECTION_NAME' => Loc::getMessage('ATMOSFERA_SEOFILTER_IBLOCK_TYPE_SECTION') ?: 'Раздел',
                'ELEMENT_NAME' => Loc::getMessage('ATMOSFERA_SEOFILTER_IBLOCK_TYPE_ELEMENT') ?: 'Правило',
            ],
        ];

        $exists = \Bitrix\Iblock\TypeTable::getList([
            'filter' => ['=ID' => self::TYPE_ID],
            'select' => ['ID'],
            'limit' => 1,
        ])->fetch();

        $type = new \CIBlockType();
        if ($exists) {
            $current = \CIBlockType::GetByIDLang(self::TYPE_ID, 'ru', true);
            if (!is_array($current) || (string)($current['NAME'] ?? '') !== $typeName) {
                $type->Update(self::TYPE_ID, ['LANG' => $lang]);
            }

            return true;
        }

        $result = $type->Add([
            'ID' => self::TYPE_ID,
            'SECTIONS' => 'Y',
            'IN_RSS' => 'N',
            'SORT' => 200,
            'LANG' => $lang,
        ]);

        return (bool)$result;
    }

    public function hasIblocks(): bool
    {
        if (!Loader::includeModule('iblock')) {
            return false;
        }

        $row = \Bitrix\Iblock\IblockTable::getList([
            'filter' => ['=IBLOCK_TYPE_ID' => self::TYPE_ID],
            'select' => ['ID'],
            'limit' => 1,
        ])->fetch();

        return (bool)$row;
    }

    public function createIblock(bool $withTimestamp = false): int|false
    {
        if (!$this->ensureType() || !Loader::includeModule('iblock')) {
            return false;
        }

        $siteIds = [];
        $sites = SiteTable::getList(['select' => ['LID']]);
        while ($site = $sites->fetch()) {
            $siteIds[] = $site['LID'];
        }

        $stamp = '';
        $codeSuffix = '';
        if ($withTimestamp) {
            $d = new DateTime();
            $stamp = (Loc::getMessage('ATMOSFERA_SEOFILTER_CREATED_AT') ?: '. Создан ') . $d->format('d.m.Y H:i:s');
            $codeSuffix = '_' . $d->format('d_m_Y_H_i_s');
        }

        $iblock = new \CIBlock();
        $iblockId = (int)$iblock->Add([
            'ACTIVE' => 'Y',
            'NAME' => (Loc::getMessage('ATMOSFERA_SEOFILTER_IBLOCK_NAME') ?: 'ЧПУ умного фильтра') . $stamp,
            'CODE' => (Loc::getMessage('ATMOSFERA_SEOFILTER_IBLOCK_CODE') ?: 'atmosfera_seofilter_rules') . $codeSuffix,
            'SORT' => 10,
            'LIST_PAGE_URL' => '',
            'DETAIL_PAGE_URL' => '#ELEMENT_CODE#',
            'INDEX_ELEMENT' => 'Y',
            'INDEX_SECTION' => 'N',
            'WORKFLOW' => 'N',
            'BIZPROC' => 'N',
            'LIST_MODE' => 'C',
            'IBLOCK_TYPE_ID' => self::TYPE_ID,
            'SITE_ID' => $siteIds,
            'FIELDS' => [
                'CODE' => [
                    'DEFAULT_VALUE' => [
                        'TRANSLITERATION' => 'Y',
                    ],
                ],
            ],
            'GROUP_ID' => [
                '2' => 'R',
            ],
        ]);

        if ($iblockId <= 0) {
            return false;
        }

        $this->createProperties($iblockId);
        $this->ensureLanguageProperties($iblockId, LanguageConfig::all());
        $this->configureElementForm($iblockId);

        return $iblockId;
    }

    private function createProperties(int $iblockId): void
    {
        $defs = [
            [
                'NAME' => Loc::getMessage('ATMOSFERA_SEOFILTER_PROP_OLD_URL'),
                'CODE' => 'OLD_URL',
                'PROPERTY_TYPE' => 'S',
                'IS_REQUIRED' => 'Y',
                'SORT' => 10,
            ],
            [
                'NAME' => Loc::getMessage('ATMOSFERA_SEOFILTER_PROP_NEW_URL'),
                'CODE' => 'NEW_URL',
                'PROPERTY_TYPE' => 'S',
                'IS_REQUIRED' => 'Y',
                'SORT' => 20,
            ],
            [
                'NAME' => Loc::getMessage('ATMOSFERA_SEOFILTER_PROP_REDIRECT'),
                'CODE' => 'REDIRECT',
                'PROPERTY_TYPE' => 'L',
                'SORT' => 22,
                'VALUES' => [
                    ['VALUE' => '301 Moved permanently', 'DEF' => 'Y', 'SORT' => 10, 'XML_ID' => '301'],
                    ['VALUE' => '302', 'DEF' => 'N', 'SORT' => 20, 'XML_ID' => '302'],
                ],
            ],
            [
                'NAME' => Loc::getMessage('ATMOSFERA_SEOFILTER_PROP_ROBOTS'),
                'CODE' => 'ROBOTS',
                'PROPERTY_TYPE' => 'L',
                'LIST_TYPE' => 'L',
                'SORT' => 23,
                'VALUES' => [
                    [
                        'VALUE' => Loc::getMessage('ATMOSFERA_SEOFILTER_ROBOTS_INDEX_FOLLOW'),
                        'DEF' => 'Y',
                        'SORT' => 10,
                        'XML_ID' => 'index_follow',
                    ],
                    [
                        'VALUE' => Loc::getMessage('ATMOSFERA_SEOFILTER_ROBOTS_NOINDEX_FOLLOW'),
                        'DEF' => 'N',
                        'SORT' => 20,
                        'XML_ID' => 'noindex_follow',
                    ],
                    [
                        'VALUE' => Loc::getMessage('ATMOSFERA_SEOFILTER_ROBOTS_NOINDEX_NOFOLLOW'),
                        'DEF' => 'N',
                        'SORT' => 30,
                        'XML_ID' => 'noindex_nofollow',
                    ],
                    [
                        'VALUE' => Loc::getMessage('ATMOSFERA_SEOFILTER_ROBOTS_INDEX'),
                        'DEF' => 'N',
                        'SORT' => 40,
                        'XML_ID' => 'index',
                    ],
                    [
                        'VALUE' => Loc::getMessage('ATMOSFERA_SEOFILTER_ROBOTS_FOLLOW'),
                        'DEF' => 'N',
                        'SORT' => 50,
                        'XML_ID' => 'follow',
                    ],
                ],
            ],
            [
                'NAME' => Loc::getMessage('ATMOSFERA_SEOFILTER_PROP_OTHER_URLS'),
                'CODE' => 'OTHER_URLS',
                'PROPERTY_TYPE' => 'S',
                'MULTIPLE' => 'Y',
                'SORT' => 27,
            ],
            [
                'NAME' => Loc::getMessage('ATMOSFERA_SEOFILTER_PROP_ADD_TO_SITEMAP'),
                'CODE' => 'ADD_TO_SITEMAP',
                'PROPERTY_TYPE' => 'L',
                'LIST_TYPE' => 'C',
                'SORT' => 30,
                'VALUES' => [
                    [
                        'VALUE' => Loc::getMessage('ATMOSFERA_SEOFILTER_YES') ?: 'Да',
                        'DEF' => 'Y',
                        'SORT' => 10,
                        'XML_ID' => 'Y',
                    ],
                ],
            ],
            [
                'NAME' => Loc::getMessage('ATMOSFERA_SEOFILTER_PROP_SEARCH_INDEX'),
                'CODE' => 'SEARCH_INDEX',
                'PROPERTY_TYPE' => 'L',
                'LIST_TYPE' => 'C',
                'SORT' => 33,
                'VALUES' => [
                    [
                        'VALUE' => Loc::getMessage('ATMOSFERA_SEOFILTER_YES') ?: 'Да',
                        'DEF' => 'Y',
                        'SORT' => 10,
                        'XML_ID' => 'Y',
                    ],
                ],
            ],
            [
                'NAME' => Loc::getMessage('ATMOSFERA_SEOFILTER_PROP_ADD_TO_CHAIN'),
                'CODE' => 'ADD_TO_CHAIN',
                'PROPERTY_TYPE' => 'L',
                'LIST_TYPE' => 'C',
                'SORT' => 35,
                'VALUES' => [
                    [
                        'VALUE' => Loc::getMessage('ATMOSFERA_SEOFILTER_YES') ?: 'Да',
                        'DEF' => 'Y',
                        'SORT' => 10,
                        'XML_ID' => 'Y',
                    ],
                ],
            ],
            [
                'NAME' => Loc::getMessage('ATMOSFERA_SEOFILTER_PROP_PARENT_CHAIN'),
                'CODE' => 'PARENT_BREADCRUMB_ITEM',
                'PROPERTY_TYPE' => 'E',
                'USER_TYPE' => 'EAutocomplete',
                'LINK_IBLOCK_ID' => $iblockId,
                'SORT' => 40,
            ],
            [
                'NAME' => Loc::getMessage('ATMOSFERA_SEOFILTER_PROP_SEO_H1'),
                'CODE' => 'SEO_H1',
                'PROPERTY_TYPE' => 'S',
                'USER_TYPE' => 'HTML',
                'SORT' => 70,
            ],
            [
                'NAME' => Loc::getMessage('ATMOSFERA_SEOFILTER_PROP_SEO_TITLE'),
                'CODE' => 'SEO_TITLE',
                'PROPERTY_TYPE' => 'S',
                'USER_TYPE' => 'HTML',
                'SORT' => 80,
            ],
            [
                'NAME' => Loc::getMessage('ATMOSFERA_SEOFILTER_PROP_SEO_KEYWORDS'),
                'CODE' => 'SEO_KEYWORDS',
                'PROPERTY_TYPE' => 'S',
                'USER_TYPE' => 'HTML',
                'SORT' => 90,
            ],
            [
                'NAME' => Loc::getMessage('ATMOSFERA_SEOFILTER_PROP_SEO_DESCRIPTION'),
                'CODE' => 'SEO_DESCRIPTION',
                'PROPERTY_TYPE' => 'S',
                'USER_TYPE' => 'HTML',
                'SORT' => 100,
            ],
        ];

        $ibp = new \CIBlockProperty();
        foreach ($defs as $field) {
            $field['ACTIVE'] = 'Y';
            $field['IBLOCK_ID'] = $iblockId;
            $ibp->Add($field);
        }
    }

    /**
     * Create/update per-language SEO description properties on all rules iblocks.
     *
     * @param list<array{name:string,code:string}>|null $langs
     * @return array{iblocks:int, created:int}
     */
    public function syncLanguageDescriptionProperties(?array $langs = null): array
    {
        self::loadLang();
        if (!Loader::includeModule('iblock')) {
            return ['iblocks' => 0, 'created' => 0];
        }

        $langs = $langs ?? LanguageConfig::all();
        $iblocks = 0;
        $created = 0;

        $res = \CIBlock::GetList(['SORT' => 'ASC'], [
            'TYPE' => self::TYPE_ID,
            'CHECK_PERMISSIONS' => 'N',
        ]);
        while ($iblock = $res->Fetch()) {
            $iblockId = (int)$iblock['ID'];
            if ($iblockId <= 0) {
                continue;
            }
            $iblocks++;
            $created += $this->ensureLanguageProperties($iblockId, $langs);
            $this->configureElementForm($iblockId);
        }

        return ['iblocks' => $iblocks, 'created' => $created];
    }

    /**
     * @param list<array{name:string,code:string}> $langs
     */
    private function ensureLanguageProperties(int $iblockId, array $langs): int
    {
        $existing = [];
        $propRes = \CIBlockProperty::GetList([], ['IBLOCK_ID' => $iblockId]);
        while ($prop = $propRes->Fetch()) {
            $existing[(string)$prop['CODE']] = (int)$prop['ID'];
        }

        $fieldLabels = [
            'h1' => Loc::getMessage('ATMOSFERA_SEOFILTER_PROP_SEO_H1') ?: 'SEO: H1',
            'title' => Loc::getMessage('ATMOSFERA_SEOFILTER_PROP_SEO_TITLE') ?: 'SEO: Title',
            'keywords' => Loc::getMessage('ATMOSFERA_SEOFILTER_PROP_SEO_KEYWORDS') ?: 'SEO: Keywords',
            'description' => Loc::getMessage('ATMOSFERA_SEOFILTER_PROP_SEO_DESCRIPTION') ?: 'SEO: Description',
            'top' => Loc::getMessage('ATMOSFERA_SEOFILTER_PROP_DESC_TOP_LANG') ?: 'SEO: описание сверху',
            'bottom' => Loc::getMessage('ATMOSFERA_SEOFILTER_PROP_DESC_BOTTOM_LANG') ?: 'SEO: описание снизу',
        ];

        $created = 0;
        $ibp = new \CIBlockProperty();
        $sortBase = 200;
        foreach ($langs as $i => $lang) {
            $code = LanguageConfig::normalizeCode($lang['code']);
            if ($code === '') {
                continue;
            }
            $fieldCodes = LanguageConfig::seoFieldCodes($code);
            $sort = $sortBase + ($i * 20);
            foreach ($fieldCodes as $fieldKey => $propCode) {
                $name = $fieldLabels[$fieldKey] ?? $propCode;
                if (isset($existing[$propCode])) {
                    $ibp->Update($existing[$propCode], [
                        'NAME' => $name,
                        'SORT' => $sort,
                    ]);
                    $sort++;
                    continue;
                }
                $newId = (int)$ibp->Add([
                    'NAME' => $name,
                    'CODE' => $propCode,
                    'PROPERTY_TYPE' => 'S',
                    'USER_TYPE' => 'HTML',
                    'SORT' => $sort,
                    'ACTIVE' => 'Y',
                    'IBLOCK_ID' => $iblockId,
                ]);
                $sort++;
                if ($newId > 0) {
                    $created++;
                    $existing[$propCode] = $newId;
                }
            }
        }

        return $created;
    }

    public function configureElementForm(int $iblockId): void
    {
        // Only technical fields on the main tab. Do NOT use IBLOCK_ELEMENT_PROP_VALUE —
        // it dumps every leftover property (including SEO_*) onto «Правило».
        $mainPropCodes = [
            'OLD_URL' => true,
            'NEW_URL' => true,
            'REDIRECT' => true,
            'ROBOTS' => true,
            'OTHER_URLS' => true,
            'ADD_TO_SITEMAP' => true,
            'SEARCH_INDEX' => true,
            'ADD_TO_CHAIN' => true,
            'PARENT_BREADCRUMB_ITEM' => true,
        ];

        /** @var array<string, array{id:int,name:string}> $propsByCode */
        $propsByCode = [];
        $res = \CIBlockProperty::GetList(['SORT' => 'ASC'], ['IBLOCK_ID' => $iblockId]);
        while ($prop = $res->Fetch()) {
            $propsByCode[(string)$prop['CODE']] = [
                'id' => (int)$prop['ID'],
                'name' => (string)$prop['NAME'],
            ];
        }

        // Official Bitrix format via CAdminFormSettings::setTabsArray —
        // tab delimiter is "--;--" (NOT plain ";"). Plain ";" was the root bug:
        // getTabsArray() never split language tabs, so everything stayed on «Правило».
        $arTabs = [
            'edit1' => [
                'TAB' => Loc::getMessage('ATMOSFERA_SEOFILTER_ELEMENT') ?: 'Правило',
                'FIELDS' => [
                    'ID' => 'ID',
                    'DATE_CREATE' => Loc::getMessage('ATMOSFERA_SEOFILTER_DATE_CREATE') ?: 'Создан',
                    'TIMESTAMP_X' => Loc::getMessage('ATMOSFERA_SEOFILTER_TIMESTAMP_X') ?: 'Изменён',
                    'ACTIVE' => Loc::getMessage('ATMOSFERA_SEOFILTER_ACTIVE') ?: 'Активность',
                    'ACTIVE_FROM' => Loc::getMessage('ATMOSFERA_SEOFILTER_ACTIVE_FROM') ?: 'Начало активности',
                    'ACTIVE_TO' => Loc::getMessage('ATMOSFERA_SEOFILTER_ACTIVE_TO') ?: 'Окончание активности',
                    'NAME' => '*' . (Loc::getMessage('ATMOSFERA_SEOFILTER_NAME') ?: 'Название'),
                    'CODE' => Loc::getMessage('ATMOSFERA_SEOFILTER_CODE') ?: 'Символьный код',
                    'SORT' => Loc::getMessage('ATMOSFERA_SEOFILTER_SORT') ?: 'Сортировка',
                ],
            ],
        ];

        foreach ($mainPropCodes as $code => $_) {
            if (!isset($propsByCode[$code])) {
                continue;
            }
            $arTabs['edit1']['FIELDS']['PROPERTY_' . $propsByCode[$code]['id']] = $propsByCode[$code]['name'];
        }

        $cedit = 1;
        foreach (LanguageConfig::all() as $lang) {
            $langCode = LanguageConfig::normalizeCode($lang['code']);
            if ($langCode === '') {
                continue;
            }
            $fields = [];
            foreach (LanguageConfig::seoFieldCodes($langCode) as $propCode) {
                if (!isset($propsByCode[$propCode])) {
                    continue;
                }
                $fields['PROPERTY_' . $propsByCode[$propCode]['id']] = $propsByCode[$propCode]['name'];
            }
            if ($fields === []) {
                continue;
            }
            $arTabs['cedit' . $cedit] = [
                'TAB' => (string)$lang['name'],
                'FIELDS' => $fields,
            ];
            $cedit++;
        }

        $this->applyElementFormTabs($iblockId, $arTabs);
    }

    /**
     * @param array<string, array{TAB:string,FIELDS:array<string,string>}> $arTabs
     */
    private function applyElementFormTabs(int $iblockId, array $arTabs): void
    {
        $formId = 'form_element_' . $iblockId;

        if (method_exists('\CUserOptions', 'DeleteOptionsByName')) {
            \CUserOptions::DeleteOptionsByName('form', $formId);
            \CUserOptions::DeleteOptionsByName('form', $formId . '_disabled');
        } else {
            \CUserOptions::DeleteOption('form', $formId, false);
            \CUserOptions::DeleteOption('form', $formId, true);
            \CUserOptions::DeleteOption('form', $formId . '_disabled', false);
            \CUserOptions::DeleteOption('form', $formId . '_disabled', true);
        }

        if (class_exists('\CAdminFormSettings')) {
            \CAdminFormSettings::setTabsArray($formId, $arTabs, true);
            global $USER;
            if (is_object($USER) && (int)$USER->GetID() > 0) {
                \CAdminFormSettings::setTabsArray($formId, $arTabs, false, (int)$USER->GetID());
            }

            return;
        }

        // Fallback: same serialization as CAdminFormSettings::setTabsArray
        $option = '';
        foreach ($arTabs as $tabId => $tab) {
            $option .= $tabId . '--#--' . $tab['TAB'];
            foreach ($tab['FIELDS'] as $fieldId => $fieldName) {
                $option .= '--,--' . $fieldId . '--#--' . $fieldName;
            }
            $option .= '--;--';
        }
        \CUserOptions::SetOption('form', $formId, ['tabs' => $option], true);
        global $USER;
        if (is_object($USER) && (int)$USER->GetID() > 0) {
            \CUserOptions::SetOption('form', $formId, ['tabs' => $option], false, (int)$USER->GetID());
        }
    }
}
