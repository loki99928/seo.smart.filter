<?php

/** @global CMain $APPLICATION */

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use CustomAI\SeoFilter\Helper;
use CustomAI\SeoFilter\Schema\IblockInstaller;
use CustomAI\SeoFilter\Service\DocsRenderer;
use CustomAI\SeoFilter\Service\LanguageConfig;
use CustomAI\SeoFilter\Service\SitemapService;

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';
Loc::loadMessages(__FILE__);

if (!Loader::includeModule('seo.smart.filter')) {
    $APPLICATION->AuthForm(Loc::getMessage('CUSTOMAI_SEOFILTER_MODULE_MISSING'));
}

$mid = isset($_REQUEST['mid']) ? htmlspecialcharsbx((string)$_REQUEST['mid']) : 'seo.smart.filter';

// POST must be handled BEFORE prolog_admin_after (headers/output). Otherwise LocalRedirect
// cannot refresh the form and the UI keeps stale values until a manual reload.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_bitrix_sessid()) {
    if (!empty($_REQUEST['create_iblock'])) {
        $createdId = Helper::createIblock(true);
        $params = [
            'mid' => 'seo.smart.filter',
            'lang' => LANGUAGE_ID,
            'tabControl_active_tab' => 'create_iblock',
        ];
        if ($createdId) {
            $params['iblock_id'] = $createdId;
        } else {
            $params['error'] = 'Y';
        }
        LocalRedirect($APPLICATION->GetCurPage() . '?' . http_build_query($params));
    }

    if (!empty($_REQUEST['migrate_dwstroy'])) {
        $result = Helper::migrateFromDwstroy();
        $params = [
            'mid' => 'seo.smart.filter',
            'lang' => LANGUAGE_ID,
            'tabControl_active_tab' => 'migrate',
            'migrated' => 'Y',
            'created' => $result['created'],
            'skipped' => $result['skipped'],
            'iblock_id' => $result['targetIblockId'],
        ];
        if ($result['errors'] !== []) {
            $params['action_error'] = implode('; ', $result['errors']);
        }
        LocalRedirect($APPLICATION->GetCurPage() . '?' . http_build_query($params));
    }

    if (!empty($_REQUEST['generate_sitemap'])) {
        $domain = isset($_REQUEST['sitemap_domain']) ? trim((string)$_REQUEST['sitemap_domain']) : '';
        $filename = isset($_REQUEST['sitemap_filename']) ? trim((string)$_REQUEST['sitemap_filename']) : '';
        $result = Helper::generateSitemap($domain !== '' ? $domain : null, $filename !== '' ? $filename : null);
        $params = [
            'mid' => 'seo.smart.filter',
            'lang' => LANGUAGE_ID,
            'tabControl_active_tab' => 'sitemap',
            'sitemap' => 'Y',
            'sitemap_count' => $result['count'],
            'sitemap_file' => $result['file'],
        ];
        if (!$result['ok']) {
            $params['action_error'] = $result['error'] ?? Loc::getMessage('CUSTOMAI_SEOFILTER_SITEMAP_ERROR');
        }
        LocalRedirect($APPLICATION->GetCurPage() . '?' . http_build_query($params));
    }

    if (!empty($_REQUEST['save_languages'])) {
        $names = isset($_REQUEST['lang_name']) && is_array($_REQUEST['lang_name']) ? $_REQUEST['lang_name'] : [];
        $codes = isset($_REQUEST['lang_code']) && is_array($_REQUEST['lang_code']) ? $_REQUEST['lang_code'] : [];
        $rows = [];
        $max = max(count($names), count($codes));
        for ($i = 0; $i < $max; $i++) {
            $rows[] = [
                'name' => isset($names[$i]) ? (string)$names[$i] : '',
                'code' => isset($codes[$i]) ? (string)$codes[$i] : '',
            ];
        }
        $savedLangs = LanguageConfig::save($rows);
        $params = [
            'mid' => 'seo.smart.filter',
            'lang' => LANGUAGE_ID,
            'tabControl_active_tab' => 'languages',
        ];
        if ($savedLangs === []) {
            $params['action_error'] = Loc::getMessage('CUSTOMAI_SEOFILTER_LANG_EMPTY');
        } else {
            $sync = (new IblockInstaller())->syncLanguageDescriptionProperties($savedLangs);
            $params['langs_saved'] = 'Y';
            $params['lang_props'] = $sync['created'];
            $params['lang_iblocks'] = $sync['iblocks'];
        }
        LocalRedirect($APPLICATION->GetCurPage() . '?' . http_build_query($params));
    }
}

$iblockInstaller = new IblockInstaller();
$iblockInstaller->ensureType();
$iblockInstaller->syncLanguageDescriptionProperties(LanguageConfig::all());

$APPLICATION->SetTitle(Loc::getMessage('CUSTOMAI_SEOFILTER_OPTIONS_PAGE_TITLE'));
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';

$iblockId = isset($_REQUEST['iblock_id']) ? (int)$_REQUEST['iblock_id'] : 0;
$error = isset($_REQUEST['error']) && $_REQUEST['error'] === 'Y';
$migrated = isset($_REQUEST['migrated']) && $_REQUEST['migrated'] === 'Y';
$sitemapDone = isset($_REQUEST['sitemap']) && $_REQUEST['sitemap'] === 'Y';
$langsSaved = isset($_REQUEST['langs_saved']) && $_REQUEST['langs_saved'] === 'Y';
$createdCount = isset($_REQUEST['created']) ? (int)$_REQUEST['created'] : 0;
$skippedCount = isset($_REQUEST['skipped']) ? (int)$_REQUEST['skipped'] : 0;
$sitemapCount = isset($_REQUEST['sitemap_count']) ? (int)$_REQUEST['sitemap_count'] : 0;
$sitemapFile = isset($_REQUEST['sitemap_file']) ? (string)$_REQUEST['sitemap_file'] : '';
$langPropsCreated = isset($_REQUEST['lang_props']) ? (int)$_REQUEST['lang_props'] : 0;
$langIblocks = isset($_REQUEST['lang_iblocks']) ? (int)$_REQUEST['lang_iblocks'] : 0;
$actionError = isset($_REQUEST['action_error']) ? (string)$_REQUEST['action_error'] : '';

$sitemapService = new SitemapService();
$sitemapDomain = $sitemapService->getDomain();
$sitemapFilename = $sitemapService->getFilename();
$languages = LanguageConfig::all();

$tabs = [
    [
        'DIV' => 'create_iblock',
        'TAB' => Loc::getMessage('CUSTOMAI_SEOFILTER_OPTIONS_TAB'),
        'ICON' => 'main_settings',
        'TITLE' => Loc::getMessage('CUSTOMAI_SEOFILTER_OPTIONS_TITLE'),
    ],
    [
        'DIV' => 'languages',
        'TAB' => Loc::getMessage('CUSTOMAI_SEOFILTER_LANG_TAB'),
        'ICON' => 'main_settings',
        'TITLE' => Loc::getMessage('CUSTOMAI_SEOFILTER_LANG_TITLE'),
    ],
    [
        'DIV' => 'migrate',
        'TAB' => Loc::getMessage('CUSTOMAI_SEOFILTER_MIGRATE_TAB'),
        'ICON' => 'main_settings',
        'TITLE' => Loc::getMessage('CUSTOMAI_SEOFILTER_MIGRATE_TITLE'),
    ],
    [
        'DIV' => 'sitemap',
        'TAB' => Loc::getMessage('CUSTOMAI_SEOFILTER_SITEMAP_TAB'),
        'ICON' => 'main_settings',
        'TITLE' => Loc::getMessage('CUSTOMAI_SEOFILTER_SITEMAP_TITLE'),
    ],
    [
        'DIV' => 'docs',
        'TAB' => Loc::getMessage('CUSTOMAI_SEOFILTER_DOCS_TAB'),
        'ICON' => 'main_settings',
        'TITLE' => Loc::getMessage('CUSTOMAI_SEOFILTER_DOCS_TITLE'),
    ],
];
$tabControl = new CAdminTabControl('tabControl', $tabs);
$docsHtml = DocsRenderer::readmeHtml();

if ($iblockId > 0 && !$migrated) {
    echo CAdminMessage::ShowNote(Loc::getMessage('CUSTOMAI_SEOFILTER_CREATE_OK'));
    echo '<p><a href="/bitrix/admin/iblock_edit.php?type=customai_seofilter&lang='
        . LANGUAGE_ID . '&ID=' . $iblockId . '&admin=Y">'
        . htmlspecialcharsbx(Loc::getMessage('CUSTOMAI_SEOFILTER_EDIT_IBLOCK'))
        . '</a></p>';
} elseif ($error) {
    echo CAdminMessage::ShowMessage(Loc::getMessage('CUSTOMAI_SEOFILTER_CREATE_ERROR'));
}

if ($migrated) {
    $msg = Loc::getMessage('CUSTOMAI_SEOFILTER_MIGRATE_RESULT', [
        '#CREATED#' => $createdCount,
        '#SKIPPED#' => $skippedCount,
    ]);
    echo CAdminMessage::ShowNote($msg);
    if ($iblockId > 0) {
        echo '<p><a href="/bitrix/admin/iblock_element_admin.php?IBLOCK_ID=' . $iblockId
            . '&type=customai_seofilter&lang=' . LANGUAGE_ID . '&find_section_section=0">'
            . htmlspecialcharsbx(Loc::getMessage('CUSTOMAI_SEOFILTER_OPEN_ELEMENTS'))
            . '</a></p>';
    }
}

if ($sitemapDone) {
    echo CAdminMessage::ShowNote(Loc::getMessage('CUSTOMAI_SEOFILTER_SITEMAP_RESULT', [
        '#COUNT#' => $sitemapCount,
        '#FILE#' => htmlspecialcharsbx($sitemapFile),
    ]));
}

if ($langsSaved) {
    echo CAdminMessage::ShowNote(Loc::getMessage('CUSTOMAI_SEOFILTER_LANG_SAVED', [
        '#CREATED#' => $langPropsCreated,
        '#IBLOCKS#' => $langIblocks,
    ]));
}

if ($actionError !== '') {
    echo CAdminMessage::ShowMessage(htmlspecialcharsbx($actionError));
}
?>
<form method="post" action="<?= htmlspecialcharsbx($APPLICATION->GetCurPage()) ?>?mid=<?= urlencode($mid) ?>&lang=<?= LANGUAGE_ID ?>">
    <?= bitrix_sessid_post() ?>
    <?php
    $tabControl->Begin();
    $tabControl->BeginNextTab();
    ?>
    <tr>
        <td colspan="2">
            <p><?= Loc::getMessage('CUSTOMAI_SEOFILTER_CREATE_HINT') ?></p>
            <input type="submit" name="create_iblock" value="<?= htmlspecialcharsbx(Loc::getMessage('CUSTOMAI_SEOFILTER_CREATE_BTN')) ?>" class="adm-btn-save">
        </td>
    </tr>
    <?php
    $tabControl->BeginNextTab();
    ?>
    <tr>
        <td colspan="2">
            <p><?= Loc::getMessage('CUSTOMAI_SEOFILTER_LANG_HINT') ?></p>
            <table class="internal atm-seo-lang-table" id="atm-seo-lang-table" style="width: auto; margin: 12px 0;">
                <thead>
                <tr class="heading">
                    <td><?= htmlspecialcharsbx(Loc::getMessage('CUSTOMAI_SEOFILTER_LANG_NAME')) ?></td>
                    <td><?= htmlspecialcharsbx(Loc::getMessage('CUSTOMAI_SEOFILTER_LANG_CODE')) ?></td>
                    <td></td>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($languages as $langRow): ?>
                    <tr>
                        <td>
                            <input type="text" name="lang_name[]" value="<?= htmlspecialcharsbx($langRow['name']) ?>" size="30" placeholder="Русский">
                        </td>
                        <td>
                            <input type="text" name="lang_code[]" value="<?= htmlspecialcharsbx($langRow['code']) ?>" size="10" placeholder="ru">
                        </td>
                        <td>
                            <button type="button" class="adm-btn atm-seo-lang-remove"><?= htmlspecialcharsbx(Loc::getMessage('CUSTOMAI_SEOFILTER_LANG_REMOVE')) ?></button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p>
                <button type="button" class="adm-btn" id="atm-seo-lang-add"><?= htmlspecialcharsbx(Loc::getMessage('CUSTOMAI_SEOFILTER_LANG_ADD')) ?></button>
                <input type="submit" name="save_languages" value="<?= htmlspecialcharsbx(Loc::getMessage('CUSTOMAI_SEOFILTER_LANG_SAVE')) ?>" class="adm-btn-save">
            </p>
            <script>
            (function () {
                var table = document.getElementById('atm-seo-lang-table');
                var addBtn = document.getElementById('atm-seo-lang-add');
                if (!table || !addBtn) return;
                var tbody = table.querySelector('tbody');
                function bindRemove(btn) {
                    btn.addEventListener('click', function () {
                        var rows = tbody.querySelectorAll('tr');
                        if (rows.length <= 1) {
                            var inputs = rows[0].querySelectorAll('input');
                            for (var i = 0; i < inputs.length; i++) inputs[i].value = '';
                            return;
                        }
                        btn.closest('tr').remove();
                    });
                }
                tbody.querySelectorAll('.atm-seo-lang-remove').forEach(bindRemove);
                addBtn.addEventListener('click', function () {
                    var tr = document.createElement('tr');
                    tr.innerHTML = '<td><input type="text" name="lang_name[]" value="" size="30" placeholder="Русский"></td>'
                        + '<td><input type="text" name="lang_code[]" value="" size="10" placeholder="ru"></td>'
                        + '<td><button type="button" class="adm-btn atm-seo-lang-remove"><?= CUtil::JSEscape(Loc::getMessage('CUSTOMAI_SEOFILTER_LANG_REMOVE')) ?></button></td>';
                    tbody.appendChild(tr);
                    bindRemove(tr.querySelector('.atm-seo-lang-remove'));
                });
            })();
            </script>
        </td>
    </tr>
    <?php
    $tabControl->BeginNextTab();
    ?>
    <tr>
        <td colspan="2">
            <p><?= Loc::getMessage('CUSTOMAI_SEOFILTER_MIGRATE_HINT') ?></p>
            <input type="submit" name="migrate_dwstroy" value="<?= htmlspecialcharsbx(Loc::getMessage('CUSTOMAI_SEOFILTER_MIGRATE_BTN')) ?>" class="adm-btn-save" onclick="return confirm('<?= CUtil::JSEscape(Loc::getMessage('CUSTOMAI_SEOFILTER_MIGRATE_CONFIRM')) ?>');">
        </td>
    </tr>
    <?php
    $tabControl->BeginNextTab();
    ?>
    <tr>
        <td width="40%"><?= htmlspecialcharsbx(Loc::getMessage('CUSTOMAI_SEOFILTER_SITEMAP_DOMAIN')) ?></td>
        <td width="60%">
            <input type="text" name="sitemap_domain" value="<?= htmlspecialcharsbx($sitemapDomain) ?>" size="50">
        </td>
    </tr>
    <tr>
        <td><?= htmlspecialcharsbx(Loc::getMessage('CUSTOMAI_SEOFILTER_SITEMAP_FILENAME')) ?></td>
        <td>
            <input type="text" name="sitemap_filename" value="<?= htmlspecialcharsbx($sitemapFilename) ?>" size="50">
        </td>
    </tr>
    <tr>
        <td colspan="2">
            <p><?= Loc::getMessage('CUSTOMAI_SEOFILTER_SITEMAP_HINT') ?></p>
            <input type="submit" name="generate_sitemap" value="<?= htmlspecialcharsbx(Loc::getMessage('CUSTOMAI_SEOFILTER_SITEMAP_BTN')) ?>" class="adm-btn-save">
        </td>
    </tr>
    <?php
    $tabControl->BeginNextTab();
    ?>
    <tr>
        <td colspan="2">
            <p class="atm-seo-docs-source"><?= htmlspecialcharsbx(Loc::getMessage('CUSTOMAI_SEOFILTER_DOCS_SOURCE')) ?></p>
            <div class="atm-seo-docs"><?= $docsHtml ?></div>
        </td>
    </tr>
    <?php
    $tabControl->Buttons([]);
    $tabControl->End();
    ?>
</form>
<style>
.atm-seo-docs-source { color: #667; margin: 0 0 16px; }
.atm-seo-docs { max-width: 960px; line-height: 1.55; color: #1f2a37; }
.atm-seo-docs h1 { font-size: 22px; margin: 0 0 12px; }
.atm-seo-docs h2 { font-size: 18px; margin: 28px 0 10px; padding-bottom: 4px; border-bottom: 1px solid #e3e9ef; }
.atm-seo-docs h3 { font-size: 15px; margin: 20px 0 8px; }
.atm-seo-docs p { margin: 0 0 10px; }
.atm-seo-docs ul, .atm-seo-docs ol { margin: 0 0 12px 22px; }
.atm-seo-docs li { margin: 4px 0; }
.atm-seo-docs hr { border: 0; border-top: 1px solid #dce7f1; margin: 20px 0; }
.atm-seo-docs code { background: #f3f6f9; padding: 1px 5px; border-radius: 3px; font-size: 12px; }
.atm-seo-docs pre { background: #f3f6f9; border: 1px solid #e3e9ef; border-radius: 4px; padding: 12px 14px; overflow: auto; margin: 0 0 14px; }
.atm-seo-docs pre code { background: none; padding: 0; }
.atm-seo-docs-table { border-collapse: collapse; width: 100%; margin: 0 0 14px; background: #fff; }
.atm-seo-docs-table th,
.atm-seo-docs-table td { border: 1px solid #dce7f1; padding: 7px 10px; text-align: left; vertical-align: top; }
.atm-seo-docs-table th { background: #f5f8fb; font-weight: 600; }
.atm-seo-docs a { color: #2067b0; }
</style>
<?php
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
