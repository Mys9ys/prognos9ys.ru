<?//
//
//use Bitrix\Main\Type\Collection;
//use Bitrix\Currency\CurrencyTable;
//
//if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();
//
///** @var CBitrixComponentTemplate $this */
///** @var array $arParams */
///** @var array $arResult */
//
//$arParams['IBLOCK_ID'] = 5;
//
//$now = date($DB->DateFormatToPHP("DD.MM.YYYY HH:MI:SS"));
//
//$result = [];
//$res = CIBlockElement::GetList(
//    array("SORT"=>"ASC"),
//    array(
//        "IBLOCK_ID" => $arParams['IBLOCK_ID'],
//        "ACTIVE" => "Y",
//        '!PROPERTY_SHOW_IN_PERSONAL_VALUE' => 'Да',
//        'PROPERTY_REGION' => ($GLOBALS['REGION_ID'] ?: 14),
//        "<=DATE_ACTIVE_FROM"=>$now,
//        ">DATE_ACTIVE_TO"=>$now
//    ),
//    false,
////    array("nTopCount" => 5),// количество товаров в блоке
//    array(),
//    array(
//        'PREVIEW_PICTURE',
//        'DETAIL_PAGE_URL',
//        'NAME',
//        'PREVIEW_TEXT',
//        'DATE_ACTIVE_FROM',
//        'PROPERTY_SERVICE_PICTURE',
//        'PROPERTY_ACTION_TYPE',
//        'PROPERTY_ALT_LINK',
//    ));
//while ($response = $res->GetNext()) {
//    $elem = [];
//    $elem["title"] = strlen($response["NAME"])>31 ? iconv_substr($response["NAME"], 0, 31, "UTF-8") . '...' : $response["NAME"];
//    $elem["text"] = strlen($response["PREVIEW_TEXT"])>111 ? iconv_substr($response["PREVIEW_TEXT"], 0, 111, "UTF-8") . '...' : $response["PREVIEW_TEXT"];
//
//    $elem["mob_img"] = imageFormatActionSlider($response["PROPERTY_SERVICE_PICTURE_VALUE"], 500, 191, 85);
//    $elem["desc_img"] = imageFormatActionSlider($response["PREVIEW_PICTURE"], 350, 222,  85);
//
//    $elem["url"] = $response["PROPERTY_ALT_LINK_VALUE"] ? : $response["DETAIL_PAGE_URL"];
//
//    if($response["PROPERTY_ACTION_TYPE_VALUE"] === 'Заглушка'){
//        $result['plug'][] = $elem;
//    } else {
//        $result['items'][] = $elem;
//    }
//
//
//}
//
//$arResult = [];
//
//
//// обработчик подстановки акций заглушек
//switch (count($result['items'])){
//    case 4:
//        $arResult = array_merge($arResult, $result['items']);;
//        break;
//
//    case 3:
//        $arResult = array_merge($arResult, $result['items'], getCountPlug($result['plug'], 1));
//        break;
//
//    case 2:
//        $arResult = array_merge($arResult, $result['items'], getCountPlug($result['plug'], 2));
//        break;
//
//    case 1:
//        $arResult = array_merge($arResult, $result['items'], getCountPlug($result['plug'], 3));
//        break;
//
//    default:
//        $arResult = array_merge($arResult, $result['items'], getCountPlug($result['plug'], 4));
//}
//
//
//
//// форматирование изображения
//function imageFormatActionSlider($id, $width, $height, $jpgQuality)
//{
//
//    $arFileTmp = CFile::ResizeImageGet(
//        $id,
//        array("width" => $width, "height" => $height),
//        BX_RESIZE_IMAGE_PROPORTIONAL,
//        true,
//        array(
//            "name" => "sharpen",
//            "precision" => 15
//        ),
//        false,
//        $jpgQuality
//    );
//    return $arFileTmp["src"];
//}
//
//
//function getCountPlug($arr, $count){
//
//    $res = [];
//
//    for ($i = 0; $i<$count; $i++){
//        if(count($arr)>0){
//            $id = rand(0, count($arr)-1);
//            $res[] = $arr[$id];
//            unset($arr[$id]);
//            $arr = array_values($arr);
//        }
//    }
//
//    return $res;
//}
