<?php

namespace CustomAI\SeoFilter\Repository;

use CustomAI\SeoFilter\Dto\SeoRule;
use CustomAI\SeoFilter\Schema\IblockInstaller;
use CustomAI\SeoFilter\Service\LanguageConfig;
use CustomAI\SeoFilter\Service\Sanitizer;
use CustomAI\SeoFilter\Service\UrlNormalizer;
use Bitrix\Main\Loader;

final class RuleRepository
{
    /** @var list<int>|null */
    private static ?array $iblockIds = null;

    private static ?SeoRule $matched = null;
    private static bool $resolved = false;

    /** @var array<string, SeoRule|null> */
    private static array $byUrl = [];

    /**
     * @param string|array|null $siteId Bitrix search may pass SITE_ID as array
     * @return list<int>
     */
    public function getIblockIds(string|array|null $siteId = null): array
    {
        $normalizedSiteId = $this->normalizeSiteId($siteId);

        if (self::$iblockIds !== null && $normalizedSiteId === null && $siteId === null) {
            return self::$iblockIds;
        }

        if (!Loader::includeModule('iblock')) {
            return [];
        }

        // Search index often sends SITE_ID as list of sites — take union without losing matches
        if (is_array($siteId) && $siteId !== []) {
            $ids = [];
            foreach ($siteId as $sid) {
                if (!is_scalar($sid) || (string)$sid === '') {
                    continue;
                }
                $ids = array_merge($ids, $this->fetchIblockIdsBySite((string)$sid));
            }

            return array_values(array_unique($ids));
        }

        // Public lookups: do not bind to SITE_ID — rules iblock may be linked to another lid
        if ($siteId === null) {
            $ids = $this->fetchIblockIdsBySite(null);
            self::$iblockIds = $ids;
            return $ids;
        }

        return $this->fetchIblockIdsBySite($normalizedSiteId);
    }

    /** @return list<int> */
    private function fetchIblockIdsBySite(?string $siteId): array
    {
        $ids = [];
        $filter = [
            'TYPE' => IblockInstaller::TYPE_ID,
            'ACTIVE' => 'Y',
            'CHECK_PERMISSIONS' => 'N',
        ];
        if ($siteId !== null && $siteId !== '') {
            $filter['SITE_ID'] = $siteId;
        }

        $res = \CIBlock::GetList(['SORT' => 'ASC'], $filter);
        while ($row = $res->Fetch()) {
            $ids[] = (int)$row['ID'];
        }

        return $ids;
    }

    private function normalizeSiteId(string|array|null $siteId): ?string
    {
        if ($siteId === null) {
            return null;
        }

        if (is_array($siteId)) {
            foreach ($siteId as $sid) {
                if (is_scalar($sid) && (string)$sid !== '') {
                    return (string)$sid;
                }
            }

            return null;
        }

        return $siteId !== '' ? $siteId : null;
    }

    public function findForCurrentRequest(): ?SeoRule
    {
        if (self::$resolved) {
            return self::$matched;
        }

        self::$resolved = true;
        $path = UrlNormalizer::currentPath();
        self::$matched = $this->findByAnyUrl($path);

        return self::$matched;
    }

    public function findByAnyUrl(string $url): ?SeoRule
    {
        $cacheKey = UrlNormalizer::canonicalize($url);
        if ($cacheKey !== '' && array_key_exists($cacheKey, self::$byUrl)) {
            return self::$byUrl[$cacheKey];
        }

        $variants = UrlNormalizer::variants($url);
        if ($variants === [] || !Loader::includeModule('iblock')) {
            if ($cacheKey !== '') {
                self::$byUrl[$cacheKey] = null;
            }

            return null;
        }

        $found = null;
        foreach ($this->getIblockIds() as $iblockId) {
            $rule = $this->queryOne($iblockId, [
                'LOGIC' => 'OR',
                'PROPERTY_NEW_URL' => $variants,
                'PROPERTY_OLD_URL' => $variants,
                'PROPERTY_OTHER_URLS' => $variants,
            ]);
            if ($rule !== null && $this->ruleMatchesUrl($rule, $url)) {
                $found = $rule;
                break;
            }
        }

        if ($found === null) {
            $found = $this->findBySmartFilterPath(UrlNormalizer::extractSmartFilterPath($url));
        }

        if ($cacheKey !== '') {
            self::$byUrl[$cacheKey] = $found;
        }

        // Align request-level cache when lookup is for the current path
        if (!self::$resolved && UrlNormalizer::pathsEqual($url, UrlNormalizer::currentPath())) {
            self::$matched = $found;
            self::$resolved = true;
        }

        return $found;
    }

    private function ruleMatchesUrl(SeoRule $rule, string $url): bool
    {
        if (UrlNormalizer::pathsEqual($rule->newUrl, $url)) {
            return true;
        }
        if (UrlNormalizer::sameSmartFilterPath($rule->oldUrl, $url)
            || UrlNormalizer::pathsEqual($rule->oldUrl, $url)
        ) {
            return true;
        }
        foreach ($rule->otherUrls as $other) {
            if (UrlNormalizer::sameSmartFilterPath($other, $url)
                || UrlNormalizer::pathsEqual($other, $url)
            ) {
                return true;
            }
        }

        return false;
    }

    public function findBySmartFilterPath(?string $filterPath): ?SeoRule
    {
        if ($filterPath === null || $filterPath === '' || $filterPath === 'clear') {
            return null;
        }
        if (!Loader::includeModule('iblock')) {
            return null;
        }

        $needle = '/filter/' . $filterPath . '/apply';
        foreach ($this->getIblockIds() as $iblockId) {
            $res = \CIBlockElement::GetList(
                ['SORT' => 'ASC', 'ID' => 'ASC'],
                [
                    'IBLOCK_ID' => $iblockId,
                    'ACTIVE' => 'Y',
                    'ACTIVE_DATE' => 'Y',
                    'GLOBAL_ACTIVE' => 'Y',
                    [
                        'LOGIC' => 'OR',
                        '%PROPERTY_OLD_URL' => $needle,
                        '%PROPERTY_OTHER_URLS' => $needle,
                    ],
                ],
                false,
                ['nTopCount' => 5],
                ['ID', 'IBLOCK_ID']
            );
            while ($ob = $res->GetNextElement()) {
                $rule = $this->hydrate($ob);
                if (UrlNormalizer::extractSmartFilterPath($rule->oldUrl) === $filterPath) {
                    return $rule;
                }
                foreach ($rule->otherUrls as $other) {
                    if (UrlNormalizer::extractSmartFilterPath($other) === $filterPath) {
                        return $rule;
                    }
                }
            }
        }

        return null;
    }

    public function findByNewUrl(string $url): ?SeoRule
    {
        $variants = UrlNormalizer::variants($url);
        if ($variants === [] || !Loader::includeModule('iblock')) {
            return null;
        }

        foreach ($this->getIblockIds() as $iblockId) {
            $rule = $this->queryOne($iblockId, [
                'PROPERTY_NEW_URL' => $variants,
            ]);
            if ($rule !== null && UrlNormalizer::pathsEqual($rule->newUrl, $url)) {
                return $rule;
            }
        }

        return null;
    }

    /**
     * @param list<string> $urls
     * @return array<string, SeoRule> map old/other url => rule
     */
    public function findByOldUrls(array $urls): array
    {
        $urls = array_values(array_unique(array_filter($urls)));
        if ($urls === [] || !Loader::includeModule('iblock')) {
            return [];
        }

        $expanded = [];
        $filterPaths = [];
        foreach ($urls as $url) {
            foreach (UrlNormalizer::variants((string)$url) as $variant) {
                $expanded[] = $variant;
            }
            $fp = UrlNormalizer::extractSmartFilterPath((string)$url);
            if ($fp !== null) {
                $filterPaths[$fp] = true;
            }
        }
        $expanded = array_values(array_unique($expanded));

        $map = [];
        $indexRule = static function (SeoRule $rule) use (&$map): void {
            if ($rule->newUrl === '') {
                return;
            }
            foreach (UrlNormalizer::variants($rule->oldUrl) as $variant) {
                $map[$variant] = $rule;
            }
            foreach ($rule->otherUrls as $other) {
                foreach (UrlNormalizer::variants($other) as $variant) {
                    $map[$variant] = $rule;
                }
            }
        };

        foreach ($this->getIblockIds() as $iblockId) {
            $res = \CIBlockElement::GetList(
                ['SORT' => 'ASC', 'ID' => 'ASC'],
                [
                    'IBLOCK_ID' => $iblockId,
                    'ACTIVE' => 'Y',
                    'ACTIVE_DATE' => 'Y',
                    'GLOBAL_ACTIVE' => 'Y',
                    [
                        'LOGIC' => 'OR',
                        'PROPERTY_OLD_URL' => $expanded,
                        'PROPERTY_OTHER_URLS' => $expanded,
                    ],
                    '!PROPERTY_NEW_URL' => false,
                ],
                false,
                false,
                ['ID', 'IBLOCK_ID']
            );

            while ($ob = $res->GetNextElement()) {
                $indexRule($this->hydrate($ob));
            }
        }

        // Fallback: Bitrix may emit /catalog/0/filter/... while rule has section code path
        foreach (array_keys($filterPaths) as $fp) {
            $already = false;
            foreach ($map as $rule) {
                if (UrlNormalizer::extractSmartFilterPath($rule->oldUrl) === $fp) {
                    $already = true;
                    break;
                }
            }
            if ($already) {
                continue;
            }
            $rule = $this->findBySmartFilterPath($fp);
            if ($rule !== null) {
                $indexRule($rule);
            }
        }

        return $map;
    }

    public function getChain(int $itemId, int $iblockId): array
    {
        $chain = [];
        $this->collectChain($itemId, $iblockId, $chain);

        return $chain;
    }

    private function collectChain(int $itemId, int $iblockId, array &$chain): void
    {
        if ($itemId <= 0 || !Loader::includeModule('iblock')) {
            return;
        }

        $res = \CIBlockElement::GetList(
            ['SORT' => 'ASC', 'ID' => 'ASC'],
            [
                'IBLOCK_ID' => $iblockId,
                'ACTIVE' => 'Y',
                'ACTIVE_DATE' => 'Y',
                'GLOBAL_ACTIVE' => 'Y',
                'ID' => $itemId,
                '!PROPERTY_SEO_H1' => false,
                '!PROPERTY_ADD_TO_CHAIN' => false,
                '!PROPERTY_NEW_URL' => false,
            ],
            false,
            ['nTopCount' => 1],
            [
                'ID',
                'PROPERTY_SEO_H1',
                'PROPERTY_PARENT_BREADCRUMB_ITEM',
                'PROPERTY_NEW_URL',
            ]
        );

        if (!$data = $res->Fetch()) {
            return;
        }

        $text = Sanitizer::propertyValue($data['PROPERTY_SEO_H1_VALUE'] ?? '');
        array_unshift($chain, [
            'NAME' => Sanitizer::plainText($text),
            'URL' => (string)($data['PROPERTY_NEW_URL_VALUE'] ?? ''),
        ]);

        $parentId = (int)($data['PROPERTY_PARENT_BREADCRUMB_ITEM_VALUE'] ?? 0);
        if ($parentId > 0) {
            $this->collectChain($parentId, $iblockId, $chain);
        }
    }

    private function queryOne(int $iblockId, array $urlFilter): ?SeoRule
    {
        $filter = [
            'IBLOCK_ID' => $iblockId,
            'ACTIVE' => 'Y',
            'ACTIVE_DATE' => 'Y',
            'GLOBAL_ACTIVE' => 'Y',
            $urlFilter,
        ];

        $res = \CIBlockElement::GetList(
            ['SORT' => 'ASC', 'ID' => 'ASC'],
            $filter,
            false,
            ['nTopCount' => 5]
        );

        while ($ob = $res->GetNextElement()) {
            return $this->hydrate($ob);
        }

        return null;
    }

    private function hydrate(object $ob): SeoRule
    {
        $fields = $ob->GetFields();
        $properties = $ob->GetProperties();

        $rule = new SeoRule();
        $rule->id = (int)$fields['ID'];
        $rule->iblockId = (int)$fields['IBLOCK_ID'];
        $rule->name = (string)$fields['NAME'];
        // GetNext* returns escaped text in PREVIEW_TEXT; raw HTML is in ~PREVIEW_TEXT
        $rule->previewText = (string)($fields['~PREVIEW_TEXT'] ?? $fields['PREVIEW_TEXT'] ?? '');
        $rule->detailText = (string)($fields['~DETAIL_TEXT'] ?? $fields['DETAIL_TEXT'] ?? '');
        $this->ensureTexts($rule);
        $rule->fields = $fields;

        $rule->oldUrl = (string)($properties['OLD_URL']['VALUE'] ?? '');
        $rule->newUrl = (string)($properties['NEW_URL']['VALUE'] ?? '');

        $other = $properties['OTHER_URLS']['VALUE'] ?? [];
        $rule->otherUrls = is_array($other) ? array_values(array_filter(array_map('strval', $other))) : [];

        $redirectXml = (string)($properties['REDIRECT']['VALUE_XML_ID'] ?? '');
        $rule->redirectCode = in_array($redirectXml, ['301', '302'], true)
            ? (int)$redirectXml
            : 0;
        if ($rule->redirectCode <= 0) {
            $redirectValue = (string)($properties['REDIRECT']['VALUE'] ?? '');
            if (preg_match('/\b(301|302)\b/', $redirectValue, $m)) {
                $rule->redirectCode = (int)$m[1];
            }
        }

        $rule->robotsXmlId = (string)($properties['ROBOTS']['VALUE_XML_ID'] ?? '');
        $rule->seoH1 = $this->extractHtmlProperty($properties, 'SEO_H1');
        $rule->seoTitle = $this->extractHtmlProperty($properties, 'SEO_TITLE');
        $rule->seoKeywords = $this->extractHtmlProperty($properties, 'SEO_KEYWORDS');
        $rule->seoDescription = $this->extractHtmlProperty($properties, 'SEO_DESCRIPTION');
        $rule->addToChain = !empty($properties['ADD_TO_CHAIN']['VALUE']);
        $rule->addToSitemap = !empty($properties['ADD_TO_SITEMAP']['VALUE']);
        $rule->searchIndex = !empty($properties['SEARCH_INDEX']['VALUE']);
        $rule->parentChainId = (int)($properties['PARENT_BREADCRUMB_ITEM']['VALUE'] ?? 0);

        foreach ($properties as $code => $property) {
            if (isset($property['VALUE']['TEXT'])) {
                $rule->clearedProperties[$code] = $property['VALUE']['TEXT'];
                $rule->clearedProperties['~' . $code] = $property['~VALUE']['TEXT'] ?? $property['VALUE']['TEXT'];
            } elseif (!empty($property['VALUE'])) {
                $rule->clearedProperties[$code] = $property['VALUE'];
                $rule->clearedProperties['~' . $code] = $property['~VALUE'] ?? $property['VALUE'];
            }
        }

        $this->applyLocalizedDescriptions($rule, $properties);

        return $rule;
    }

    /**
     * Overlay SEO fields for the current public language only (kz↔kk aliases).
     * Never pull another language's tab (e.g. RU texts on /kz/). Empty → keep base SEO_*.
     */
    private function applyLocalizedDescriptions(SeoRule $rule, array $properties): void
    {
        $langs = LanguageConfig::codesToTry(LanguageConfig::currentCode());
        if ($langs === []) {
            return;
        }

        $map = [
            'h1' => 'seoH1',
            'title' => 'seoTitle',
            'keywords' => 'seoKeywords',
            'description' => 'seoDescription',
            'top' => 'previewText',
            'bottom' => 'detailText',
        ];

        foreach ($map as $fieldKey => $ruleProp) {
            foreach ($langs as $lang) {
                $code = LanguageConfig::seoFieldCodes($lang)[$fieldKey];
                $value = $this->extractHtmlProperty($properties, $code);
                if ($value === '' || Sanitizer::isVisuallyEmpty($value)) {
                    continue;
                }
                $rule->{$ruleProp} = $value;
                break;
            }
        }
    }

    public static function resetRequestCache(): void
    {
        self::$matched = null;
        self::$resolved = false;
        self::$iblockIds = null;
        self::$byUrl = [];
    }

    public function ensureTexts(SeoRule $rule): void
    {
        if ($rule->id <= 0 || $rule->textsEnsured) {
            return;
        }

        $needPreview = $rule->previewText === '';
        $needDetail = $rule->detailText === '';
        $needH1 = $rule->seoH1 === '';
        if (!$needPreview && !$needDetail && !$needH1) {
            $rule->textsEnsured = true;

            return;
        }

        // GetArrayByID is the most reliable source for DETAIL_TEXT / PREVIEW_TEXT
        if (($needPreview || $needDetail) && is_callable(['\CIBlockElement', 'GetArrayByID'])) {
            $arr = \CIBlockElement::GetArrayByID($rule->id);
            if (is_array($arr)) {
                if ($needPreview) {
                    $rule->previewText = (string)($arr['PREVIEW_TEXT'] ?? '');
                }
                if ($needDetail) {
                    $rule->detailText = (string)($arr['DETAIL_TEXT'] ?? '');
                }
            }
        }

        $needPreview = $rule->previewText === '';
        $needDetail = $rule->detailText === '';
        if ($needPreview || $needDetail || $needH1) {
            $ob = \CIBlockElement::GetByID($rule->id)->GetNextElement();
            if ($ob) {
                $fields = $ob->GetFields();
                if ($needPreview) {
                    $rule->previewText = (string)($fields['~PREVIEW_TEXT'] ?? $fields['PREVIEW_TEXT'] ?? '');
                }
                if ($needDetail) {
                    $rule->detailText = (string)($fields['~DETAIL_TEXT'] ?? $fields['DETAIL_TEXT'] ?? '');
                }
                $properties = $ob->GetProperties();
                if ($needH1) {
                    $rule->seoH1 = $this->extractHtmlProperty($properties, 'SEO_H1');
                }
                // Re-apply language overlay after late text load
                $this->applyLocalizedDescriptions($rule, $properties);
            }
        }

        $rule->textsEnsured = true;
    }

    private function extractHtmlProperty(array $properties, string $code): string
    {
        if (empty($properties[$code])) {
            return '';
        }

        $property = $properties[$code];
        $value = Sanitizer::propertyValue($property['~VALUE'] ?? null);
        if ($value === '') {
            $value = Sanitizer::propertyValue($property['VALUE'] ?? null);
        }
        if ($value === '' && isset($property['VALUE']['TEXT'])) {
            $value = html_entity_decode((string)$property['VALUE']['TEXT']);
        }
        if ($value === '' && isset($property['~VALUE']['TEXT'])) {
            $value = (string)$property['~VALUE']['TEXT'];
        }

        return trim($value);
    }
}
