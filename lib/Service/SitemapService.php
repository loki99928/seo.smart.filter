<?php

namespace Atmosfera\SeoFilter\Service;

use Atmosfera\SeoFilter\Schema\IblockInstaller;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;

final class SitemapService
{
    public const OPTION_DOMAIN = 'sitemap_domain';
    public const OPTION_FILENAME = 'sitemap_filename';
    public const DEFAULT_FILENAME = 'sitemap-atmosfera-seofilter.xml';

    /**
     * @return list<array{loc:string, lastmod:?string}>
     */
    public function getEntries(?string $siteId = null): array
    {
        if (!Loader::includeModule('iblock')) {
            return [];
        }

        $entries = [];
        $iblockIds = $this->getIblockIds($siteId);

        foreach ($iblockIds as $iblockId) {
            $res = \CIBlockElement::GetList(
                ['ID' => 'ASC'],
                [
                    'IBLOCK_ID' => $iblockId,
                    'ACTIVE' => 'Y',
                    'ACTIVE_DATE' => 'Y',
                    'GLOBAL_ACTIVE' => 'Y',
                    '!PROPERTY_NEW_URL' => false,
                    '!PROPERTY_ADD_TO_SITEMAP' => false,
                ],
                false,
                false,
                ['ID', 'TIMESTAMP_X', 'PROPERTY_NEW_URL']
            );

            while ($row = $res->Fetch()) {
                $url = trim((string)($row['PROPERTY_NEW_URL_VALUE'] ?? ''));
                if ($url === '' || !UrlNormalizer::isSafeLocalRedirect($url)) {
                    continue;
                }

                $lastmod = null;
                if (!empty($row['TIMESTAMP_X'])) {
                    $ts = MakeTimeStamp($row['TIMESTAMP_X']);
                    if ($ts) {
                        $lastmod = date('c', $ts);
                    }
                }

                $entries[] = [
                    'loc' => $url,
                    'lastmod' => $lastmod,
                ];
            }
        }

        return $entries;
    }

    /**
     * Paths only (for Salonatmos SitemapGenerator).
     * @return list<string>
     */
    public function getActivePaths(?string $siteId = null): array
    {
        $paths = [];
        foreach ($this->getEntries($siteId) as $entry) {
            $paths[] = $entry['loc'];
        }

        return array_values(array_unique($paths));
    }

    /**
     * @return array{ok:bool, file:string, count:int, error?:string}
     */
    public function generate(?string $domain = null, ?string $filename = null): array
    {
        $domain = rtrim($domain ?: $this->getDomain(), '/');
        $filename = $filename ?: $this->getFilename();
        $filename = basename(str_replace(['..', '\\'], '', $filename));
        if ($filename === '' || !preg_match('/^[a-zA-Z0-9._-]+\.xml$/', $filename)) {
            $filename = self::DEFAULT_FILENAME;
        }

        $entries = $this->getEntries();
        $xml = $this->buildXml($domain, $entries);
        $path = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . DIRECTORY_SEPARATOR . $filename;

        if (file_put_contents($path, $xml) === false) {
            return [
                'ok' => false,
                'file' => $filename,
                'count' => 0,
                'error' => 'Не удалось записать файл ' . $filename,
            ];
        }

        Option::set('seo.smart.filter', self::OPTION_DOMAIN, $domain);
        Option::set('seo.smart.filter', self::OPTION_FILENAME, $filename);
        $this->ensureAgent();

        return [
            'ok' => true,
            'file' => $filename,
            'count' => count($entries),
        ];
    }

    public function ensureAgent(): void
    {
        $name = '\\Atmosfera\\SeoFilter\\Service\\SitemapService::agentGenerate();';
        $exists = \CAgent::GetList([], [
            'MODULE_ID' => 'seo.smart.filter',
            'NAME' => $name,
        ])->Fetch();

        if ($exists) {
            return;
        }

        \CAgent::AddAgent($name, 'seo.smart.filter', 'N', 86400, '', 'Y', '', 100);
    }

    public function getDomain(): string
    {
        $saved = Option::get('seo.smart.filter', self::OPTION_DOMAIN, '');
        if ($saved !== '') {
            return $saved;
        }

        $request = \Bitrix\Main\Context::getCurrent()->getRequest();
        $host = $request->getHttpHost();
        $https = $request->isHttps();

        return ($https ? 'https://' : 'http://') . $host;
    }

    public function getFilename(): string
    {
        $saved = Option::get('seo.smart.filter', self::OPTION_FILENAME, self::DEFAULT_FILENAME);
        return $saved !== '' ? $saved : self::DEFAULT_FILENAME;
    }

    public static function agentGenerate(): string
    {
        if (Loader::includeModule('seo.smart.filter')) {
            (new self())->generate();
        }

        return '\\Atmosfera\\SeoFilter\\Service\\SitemapService::agentGenerate();';
    }

    /**
     * @param list<array{loc:string, lastmod:?string}> $entries
     */
    private function buildXml(string $domain, array $entries): string
    {
        $out = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $out .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($entries as $entry) {
            $loc = $entry['loc'];
            if (mb_strpos($loc, 'http://') !== 0 && mb_strpos($loc, 'https://') !== 0) {
                $loc = $domain . (mb_strpos($loc, '/') === 0 ? $loc : '/' . $loc);
            }

            $out .= "  <url>\n";
            $out .= '    <loc>' . htmlspecialchars($loc, ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</loc>\n";
            if (!empty($entry['lastmod'])) {
                $out .= '    <lastmod>' . htmlspecialchars((string)$entry['lastmod'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</lastmod>\n";
            }
            $out .= "    <changefreq>weekly</changefreq>\n";
            $out .= "    <priority>0.8</priority>\n";
            $out .= "  </url>\n";
        }

        $out .= '</urlset>';
        return $out;
    }

    /** @return list<int> */
    private function getIblockIds(?string $siteId): array
    {
        $ids = [];
        $filter = [
            'TYPE' => IblockInstaller::TYPE_ID,
            'ACTIVE' => 'Y',
        ];
        if ($siteId) {
            $filter['SITE_ID'] = $siteId;
        }

        $res = \CIBlock::GetList(['SORT' => 'ASC'], $filter);
        while ($row = $res->Fetch()) {
            $ids[] = (int)$row['ID'];
        }

        return $ids;
    }
}
