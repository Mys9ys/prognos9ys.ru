<?
use \Bitrix\Main\Loader,
    \Bitrix\Main\Config\Option;

if(Loader::includeModule('iblock')) {
    $rsItems = \CIBlockElement::GetList([], [
        'IBLOCK_ID' => \DrawHandler::getDrawIblockId(),
        'ID' => Option::get("grain.customsettings", "promo_id_draw")
    ], ['ID', 'ACTIVE_FROM', 'ACTIVE_TO']);
    if($item = $rsItems->Fetch()) {
        $active_from = FormatDate("d F", MakeTimeStamp($item['ACTIVE_FROM']));
        $active_to = FormatDate("d F Y", MakeTimeStamp($item['ACTIVE_TO']));
    }
}
?>

АКЦИЯ ПРОВОДИТСЯ С <?=$active_from?> по <?=$active_to?>