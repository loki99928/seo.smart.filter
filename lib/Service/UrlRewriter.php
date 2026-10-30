<?php

namespace CustomAI\SeoFilter\Service;

use CustomAI\SeoFilter\Dto\SeoRule;
use CustomAI\SeoFilter\Repository\RuleRepository;
use Bitrix\Main\Context;
use Bitrix\Main\HttpRequest;

final class UrlRewriter
{
    private static bool $applied = false;
    private static ?array $initialGet = null;
    private static ?SeoRule $activeRule = null;

    public static function getActiveRule(): ?SeoRule
    {
        return self::$activeRule;
    }

    public static function wasApplied(): bool
    {
        return self::$applied;
    }

    public static function applyInternalFilterUrl(): void
    {
        if (self::$applied || RequestGuard::shouldSkipRewrite()) {
            return;
        }

        $repo = new RuleRepository();
        $path = UrlNormalizer::currentPath();
        // Physical landing pages (catalog/keramogranit/index.php) set SMART_FILTER_PATH themselves
        if (UrlNormalizer::isPhysicalCatalogLanding($path)) {
            return;
        }

        $rule = $repo->findByAnyUrl($path);
        if ($rule === null || $rule->oldUrl === '') {
            return;
        }

        self::$activeRule = $rule;
        self::$applied = true;

        if (UrlNormalizer::pathsEqual($rule->newUrl, $path)) {
            self::reinit($rule->oldUrl);
            return;
        }

        $filterPath = UrlNormalizer::extractSmartFilterPath($rule->oldUrl);
        if ($filterPath !== null) {
            $_GET['SMART_FILTER_PATH'] = $filterPath;
            $_REQUEST['SMART_FILTER_PATH'] = $filterPath;
        }
    }

    public static function restorePublicUrl(): void
    {
        if (self::$activeRule === null || self::$activeRule->newUrl === '') {
            $repo = new RuleRepository();
            $rule = $repo->findForCurrentRequest();
            if ($rule === null || $rule->newUrl === '') {
                return;
            }
            self::$activeRule = $rule;
        }

        self::reinit(self::$activeRule->newUrl);
    }

    private static function reinit(string $url): void
    {
        global $APPLICATION;

        $context = Context::getCurrent();
        $server = $context->getServer();
        $serverArray = $server->toArray();

        if (self::$initialGet === null) {
            self::$initialGet = is_array($_GET) ? $_GET : [];
        }
        $originalGet = self::$initialGet;

        $_SERVER['REQUEST_URI_NEW'] = $_SERVER['REQUEST_URI'] ?? '';
        $_SERVER['OLD_GET'] = $originalGet;

        $qPos = mb_strpos($url, '?');
        $queryPart = $qPos !== false ? mb_substr($url, $qPos) : '';

        $_SERVER['REQUEST_URI'] = $url;
        $_SERVER['QUERY_STRING'] = $qPos !== false ? mb_substr($queryPart, 1) : '';

        $serverArray['REQUEST_URI'] = $_SERVER['REQUEST_URI'];
        $serverArray['REQUEST_URI_NEW'] = $_SERVER['REQUEST_URI_NEW'];
        $serverArray['OLD_GET'] = $_SERVER['OLD_GET'];
        $server->set($serverArray);

        $parsedGet = [];
        if ($_SERVER['QUERY_STRING'] !== '') {
            parse_str($_SERVER['QUERY_STRING'], $parsedGet);
        }

        $effectiveGet = array_merge($originalGet, $parsedGet);
        $effectiveQuery = http_build_query($effectiveGet, '', '&');
        $_SERVER['QUERY_STRING'] = $effectiveQuery;
        $serverArray['QUERY_STRING'] = $effectiveQuery;
        $server->set($serverArray);

        foreach ($_SERVER['OLD_GET'] as $key => $value) {
            unset($_REQUEST[$key]);
        }

        $_GET = $effectiveGet;
        $_REQUEST = array_merge($_REQUEST, $_GET);

        // catalog/index.php → atmPrepareSefFolder() runs before OnBeforeProlog and may
        // mistreat SEO NEW_URL (/catalog/italkero/) as ELEMENT_CODE — drop it on rewrite.
        foreach (['ELEMENT_CODE', 'ELEMENT_ID', 'SECTION_CODE', 'SECTION_ID', 'SECTION_CODE_PATH'] as $junk) {
            unset($_GET[$junk], $_REQUEST[$junk]);
        }

        if (self::$activeRule !== null) {
            $filterPath = UrlNormalizer::extractSmartFilterPath(self::$activeRule->oldUrl);
            if ($filterPath !== null) {
                $_GET['SMART_FILTER_PATH'] = $filterPath;
                $_REQUEST['SMART_FILTER_PATH'] = $filterPath;
            }
        }

        $context->initialize(
            new HttpRequest($server, $_GET, $_POST, $_FILES, $_COOKIE),
            $context->getResponse(),
            $server
        );

        $APPLICATION->SetCurPage($url);
    }
}
