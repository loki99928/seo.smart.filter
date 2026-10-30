<?php

use Bitrix\Main\Localization\Loc;

/** @global CMain $APPLICATION */

if (!check_bitrix_sessid()) {
    return;
}

Loc::loadMessages(__DIR__ . '/index.php');

echo \CAdminMessage::ShowNote(Loc::getMessage('ATMOSFERA_SEOFILTER_INSTALL_OK'));
?>
<form action="<?= htmlspecialcharsbx($APPLICATION->GetCurPage()) ?>">
    <input type="hidden" name="lang" value="<?= LANGUAGE_ID ?>">
    <input type="submit" name="" value="<?= htmlspecialcharsbx(Loc::getMessage('ATMOSFERA_SEOFILTER_MOD_BACK')) ?>">
</form>
