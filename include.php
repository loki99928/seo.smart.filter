<?php

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

Loader::registerAutoLoadClasses('seo.smart.filter', [
    'Atmosfera\\SeoFilter\\Events' => 'lib/Events.php',
    'Atmosfera\\SeoFilter\\Helper' => 'lib/Helper.php',
    'Atmosfera\\SeoFilter\\Dto\\SeoRule' => 'lib/Dto/SeoRule.php',
    'Atmosfera\\SeoFilter\\Repository\\RuleRepository' => 'lib/Repository/RuleRepository.php',
    'Atmosfera\\SeoFilter\\Schema\\IblockInstaller' => 'lib/Schema/IblockInstaller.php',
    'Atmosfera\\SeoFilter\\Service\\FilterUrlMapper' => 'lib/Service/FilterUrlMapper.php',
    'Atmosfera\\SeoFilter\\Service\\RedirectService' => 'lib/Service/RedirectService.php',
    'Atmosfera\\SeoFilter\\Service\\RequestGuard' => 'lib/Service/RequestGuard.php',
    'Atmosfera\\SeoFilter\\Service\\Sanitizer' => 'lib/Service/Sanitizer.php',
    'Atmosfera\\SeoFilter\\Service\\SeoApplicator' => 'lib/Service/SeoApplicator.php',
    'Atmosfera\\SeoFilter\\Service\\UrlNormalizer' => 'lib/Service/UrlNormalizer.php',
    'Atmosfera\\SeoFilter\\Service\\UrlRewriter' => 'lib/Service/UrlRewriter.php',
    'Atmosfera\\SeoFilter\\Service\\MigrationService' => 'lib/Service/MigrationService.php',
    'Atmosfera\\SeoFilter\\Service\\SitemapService' => 'lib/Service/SitemapService.php',
    'Atmosfera\\SeoFilter\\Service\\DocsRenderer' => 'lib/Service/DocsRenderer.php',
    'Atmosfera\\SeoFilter\\Service\\LanguageConfig' => 'lib/Service/LanguageConfig.php',
]);
