<?php

namespace Atmosfera\SeoFilter\Service;

use Atmosfera\SeoFilter\Schema\IblockInstaller;
use Bitrix\Main\Loader;

final class MigrationService
{
    public const SOURCE_TYPE = 'dwstroy_seochpulite';

    /**
     * @return array{ok:bool, created:int, skipped:int, errors:list<string>, targetIblockId:int}
     */
    public function run(?int $targetIblockId = null): array
    {
        $result = [
            'ok' => false,
            'created' => 0,
            'skipped' => 0,
            'errors' => [],
            'targetIblockId' => 0,
        ];

        if (!Loader::includeModule('iblock')) {
            $result['errors'][] = 'Модуль iblock недоступен';
            return $result;
        }

        $sourceIds = $this->getIblockIdsByType(self::SOURCE_TYPE);
        if ($sourceIds === []) {
            $result['errors'][] = 'Инфоблоки dwstroy.seochpulite не найдены (тип ' . self::SOURCE_TYPE . ')';
            return $result;
        }

        if ($targetIblockId === null || $targetIblockId <= 0) {
            $targets = $this->getIblockIdsByType(IblockInstaller::TYPE_ID);
            if ($targets === []) {
                $created = (new IblockInstaller())->createIblock();
                if (!$created) {
                    $result['errors'][] = 'Не удалось создать целевой инфоблок';
                    return $result;
                }
                $targetIblockId = (int)$created;
            } else {
                $targetIblockId = (int)$targets[0];
            }
        }

        $result['targetIblockId'] = $targetIblockId;
        $existingNewUrls = $this->collectExistingNewUrls($targetIblockId);
        $idMap = [];
        $pendingParents = [];

        foreach ($sourceIds as $sourceIblockId) {
            $res = \CIBlockElement::GetList(
                ['SORT' => 'ASC', 'ID' => 'ASC'],
                ['IBLOCK_ID' => $sourceIblockId],
                false,
                false,
                [
                    'ID', 'IBLOCK_ID', 'NAME', 'CODE', 'ACTIVE', 'SORT',
                    'PREVIEW_TEXT', 'PREVIEW_TEXT_TYPE', 'DETAIL_TEXT', 'DETAIL_TEXT_TYPE',
                    'ACTIVE_FROM', 'ACTIVE_TO',
                ]
            );

            while ($ob = $res->GetNextElement()) {
                $fields = $ob->GetFields();
                $properties = $ob->GetProperties();
                $oldId = (int)$fields['ID'];
                $newUrl = (string)($properties['NEW_URL']['VALUE'] ?? '');

                if ($newUrl !== '' && isset($existingNewUrls[$newUrl])) {
                    $idMap[$oldId] = $existingNewUrls[$newUrl];
                    $result['skipped']++;
                    continue;
                }

                $mapped = $this->mapProperties($properties, $targetIblockId);
                $element = new \CIBlockElement();
                $newId = (int)$element->Add([
                    'IBLOCK_ID' => $targetIblockId,
                    'NAME' => (string)$fields['NAME'],
                    'CODE' => (string)(($fields['CODE'] ?? '') !== '' ? $fields['CODE'] : $newUrl),
                    'ACTIVE' => (string)$fields['ACTIVE'],
                    'SORT' => (int)$fields['SORT'],
                    'ACTIVE_FROM' => $fields['ACTIVE_FROM'] ?: false,
                    'ACTIVE_TO' => $fields['ACTIVE_TO'] ?: false,
                    'PREVIEW_TEXT' => (string)($fields['~PREVIEW_TEXT'] ?? $fields['PREVIEW_TEXT'] ?? ''),
                    'PREVIEW_TEXT_TYPE' => (string)($fields['PREVIEW_TEXT_TYPE'] ?? 'html'),
                    'DETAIL_TEXT' => (string)($fields['~DETAIL_TEXT'] ?? $fields['DETAIL_TEXT'] ?? ''),
                    'DETAIL_TEXT_TYPE' => (string)($fields['DETAIL_TEXT_TYPE'] ?? 'html'),
                    'PROPERTY_VALUES' => $mapped['values'],
                ]);

                if ($newId <= 0) {
                    $result['errors'][] = 'Элемент #' . $oldId . ': ' . ($element->LAST_ERROR ?: 'ошибка добавления');
                    continue;
                }

                $idMap[$oldId] = $newId;
                if ($newUrl !== '') {
                    $existingNewUrls[$newUrl] = $newId;
                }
                if ($mapped['parentOldId'] > 0) {
                    $pendingParents[$newId] = $mapped['parentOldId'];
                }
                $result['created']++;
            }
        }

        foreach ($pendingParents as $newElementId => $oldParentId) {
            if (!isset($idMap[$oldParentId])) {
                continue;
            }
            \CIBlockElement::SetPropertyValuesEx(
                (int)$newElementId,
                $targetIblockId,
                ['PARENT_BREADCRUMB_ITEM' => $idMap[$oldParentId]]
            );
        }

        $result['ok'] = $result['errors'] === [];
        return $result;
    }

    /**
     * @return array{values: array<string,mixed>, parentOldId: int}
     */
    private function mapProperties(array $properties, int $targetIblockId): array
    {
        $values = [];
        $parentOldId = 0;

        foreach (['OLD_URL', 'NEW_URL'] as $code) {
            if (!empty($properties[$code]['VALUE'])) {
                $values[$code] = (string)$properties[$code]['VALUE'];
            }
        }

        if (!empty($properties['OTHER_URLS']['VALUE']) && is_array($properties['OTHER_URLS']['VALUE'])) {
            $others = [];
            foreach ($properties['OTHER_URLS']['VALUE'] as $url) {
                if ((string)$url !== '') {
                    $others[] = ['VALUE' => (string)$url];
                }
            }
            if ($others !== []) {
                $values['OTHER_URLS'] = $others;
            }
        }

        foreach (['SEO_H1', 'SEO_TITLE', 'SEO_KEYWORDS', 'SEO_DESCRIPTION'] as $code) {
            if (empty($properties[$code]['VALUE'])) {
                continue;
            }
            $raw = $properties[$code]['VALUE'];
            if (is_array($raw)) {
                $values[$code] = [
                    'VALUE' => [
                        'TEXT' => (string)($raw['TEXT'] ?? $raw['HTML'] ?? ''),
                        'TYPE' => (string)($raw['TYPE'] ?? 'HTML'),
                    ],
                ];
            } else {
                $values[$code] = [
                    'VALUE' => [
                        'TEXT' => (string)$raw,
                        'TYPE' => 'HTML',
                    ],
                ];
            }
        }

        foreach (['REDIRECT', 'ROBOTS'] as $code) {
            $xmlId = (string)($properties[$code]['VALUE_XML_ID'] ?? '');
            if ($xmlId === '') {
                continue;
            }
            $enumId = $this->findEnumId($targetIblockId, $code, $xmlId);
            if ($enumId > 0) {
                $values[$code] = $enumId;
            }
        }

        foreach (['ADD_TO_SITEMAP', 'SEARCH_INDEX', 'ADD_TO_CHAIN'] as $code) {
            if (empty($properties[$code]['VALUE'])) {
                continue;
            }
            $enumId = $this->findEnumId($targetIblockId, $code, 'Y');
            if ($enumId > 0) {
                $values[$code] = $enumId;
            }
        }

        if (!empty($properties['PARENT_BREADCRUMB_ITEM']['VALUE'])) {
            $parentOldId = (int)$properties['PARENT_BREADCRUMB_ITEM']['VALUE'];
        }

        return ['values' => $values, 'parentOldId' => $parentOldId];
    }

    /** @return list<int> */
    private function getIblockIdsByType(string $typeId): array
    {
        $ids = [];
        $res = \CIBlock::GetList(['SORT' => 'ASC'], ['TYPE' => $typeId, 'ACTIVE' => 'Y']);
        while ($row = $res->Fetch()) {
            $ids[] = (int)$row['ID'];
        }

        return $ids;
    }

    /** @return array<string,int> */
    private function collectExistingNewUrls(int $iblockId): array
    {
        $map = [];
        $res = \CIBlockElement::GetList(
            ['ID' => 'ASC'],
            ['IBLOCK_ID' => $iblockId, '!PROPERTY_NEW_URL' => false],
            false,
            false,
            ['ID', 'PROPERTY_NEW_URL']
        );
        while ($row = $res->Fetch()) {
            $url = (string)($row['PROPERTY_NEW_URL_VALUE'] ?? '');
            if ($url !== '') {
                $map[$url] = (int)$row['ID'];
            }
        }

        return $map;
    }

    private function findEnumId(int $iblockId, string $code, string $xmlId): int
    {
        static $cache = [];
        $key = $iblockId . ':' . $code . ':' . $xmlId;
        if (isset($cache[$key])) {
            return $cache[$key];
        }

        $prop = \CIBlockProperty::GetList([], ['IBLOCK_ID' => $iblockId, 'CODE' => $code])->Fetch();
        if (!$prop) {
            return $cache[$key] = 0;
        }

        $enum = \CIBlockPropertyEnum::GetList([], [
            'PROPERTY_ID' => $prop['ID'],
            'XML_ID' => $xmlId,
        ])->Fetch();

        return $cache[$key] = (int)($enum['ID'] ?? 0);
    }
}
