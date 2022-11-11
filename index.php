<?
define("NEED_AUTH", true);
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/header.php');
$APPLICATION->SetTitle('Главная');
?>

    <p><a href="/p/logout">Выйти</a></p>
<?
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/footer.php');
?>