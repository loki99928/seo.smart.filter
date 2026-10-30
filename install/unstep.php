<?php

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__DIR__ . '/index.php');

global $APPLICATION, $step;

$step = (int)$step;
if ($step < 2) {
    ?>
    <form action="<?= htmlspecialcharsbx($APPLICATION->GetCurPage()) ?>" method="post">
        <?= bitrix_sessid_post() ?>
        <input type="hidden" name="lang" value="<?= LANGUAGE_ID ?>">
        <input type="hidden" name="id" value="seo.smart.filter">
        <input type="hidden" name="uninstall" value="Y">
        <input type="hidden" name="step" value="2">
        <p>
            <label>
                <input type="checkbox" name="savedata" value="Y" checked>
                <?= htmlspecialcharsbx(Loc::getMessage('CUSTOMAI_SEOFILTER_SAVE_DATA')) ?>
            </label>
        </p>
        <input type="submit" name="inst" value="<?= htmlspecialcharsbx(Loc::getMessage('CUSTOMAI_SEOFILTER_UNINSTALL_TITLE')) ?>">
    </form>
    <?php
    return;
}

echo \CAdminMessage::ShowNote(Loc::getMessage('CUSTOMAI_SEOFILTER_UNINSTALL_OK'));
?>
<form action="<?= htmlspecialcharsbx($APPLICATION->GetCurPage()) ?>">
    <input type="hidden" name="lang" value="<?= LANGUAGE_ID ?>">
    <input type="submit" name="" value="<?= htmlspecialcharsbx(Loc::getMessage('CUSTOMAI_SEOFILTER_MOD_BACK')) ?>">
</form>
