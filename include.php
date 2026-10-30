<?php

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

Loader::registerAutoLoadClasses('seo.smart.filter', [
    'CustomAI\\SeoFilter\\Events' => 'lib/Events.php',
    'CustomAI\\SeoFilter\\Helper' => 'lib/Helper.php',
    'CustomAI\\SeoFilter\\Dto\\SeoRule' => 'lib/Dto/SeoRule.php',
    'CustomAI\\SeoFilter\\Repository\\RuleRepository' => 'lib/Repository/RuleRepository.php',
    'CustomAI\\SeoFilter\\Schema\\IblockInstaller' => 'lib/Schema/IblockInstaller.php',
    'CustomAI\\SeoFilter\\Service\\FilterUrlMapper' => 'lib/Service/FilterUrlMapper.php',
    'CustomAI\\SeoFilter\\Service\\RedirectService' => 'lib/Service/RedirectService.php',
    'CustomAI\\SeoFilter\\Service\\RequestGuard' => 'lib/Service/RequestGuard.php',
    'CustomAI\\SeoFilter\\Service\\Sanitizer' => 'lib/Service/Sanitizer.php',
    'CustomAI\\SeoFilter\\Service\\SeoApplicator' => 'lib/Service/SeoApplicator.php',
    'CustomAI\\SeoFilter\\Service\\UrlNormalizer' => 'lib/Service/UrlNormalizer.php',
    'CustomAI\\SeoFilter\\Service\\UrlRewriter' => 'lib/Service/UrlRewriter.php',
    'CustomAI\\SeoFilter\\Service\\MigrationService' => 'lib/Service/MigrationService.php',
    'CustomAI\\SeoFilter\\Service\\SitemapService' => 'lib/Service/SitemapService.php',
    'CustomAI\\SeoFilter\\Service\\DocsRenderer' => 'lib/Service/DocsRenderer.php',
    'CustomAI\\SeoFilter\\Service\\LanguageConfig' => 'lib/Service/LanguageConfig.php',
]);
