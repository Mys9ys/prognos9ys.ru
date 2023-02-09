<?php if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die(); ?>

<?php $news = new FillNews();
?>

<div class="main_banner_wrapper">
    <?php foreach ($arResult["active"] as $key => $item): ?>
        <?=$news->fillNewsItem($item)?>
    <?php endforeach; ?>

    <div class="accordion news_block_old_news" id="accordionExample">
        <div class="accordion-item">
            <h6 class="accordion-header" id="heading1">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                        data-bs-target="#collapse1"
                        aria-expanded="false" aria-controls="collapse1">Неактуальные новости
                </button>
            </h6>
            <div id="collapse1" class="accordion-collapse collapse"
                 aria-labelledby="heading1"
                 data-bs-parent="#accordionExample">
                <div class="accordion-body">
                    <?php foreach ($arResult["old"] as $key => $item): ?>
                        <?= $news->fillNewsItem($item) ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>



<?

class FillNews
{

    public function fillNewsItem($item)
    {

        $html = '';

        $style = '';
        if ($item["bg_color"]) $style = 'style="background: ' . $item["bg_color"] . '"';

        $wrapperClass = 'mb_banner_wrapper';
        if ($item["bcgrnd"]) $wrapperClass = 'mb_full_banner_wrapper';

        if ($item["link"]) {
            $html .= '<a class="'.$wrapperClass.'" href="' . $item["link"] . '" ' . $style . '>';
        } else {
            $html .= '<div class="'.$wrapperClass.'" ' . $style . '>';
        }
        if ($item["bcgrnd"]) {
            $html .= '<img src="' . $item["bcgrnd"] . '" alt="">';
        } else {
            if ($item["img"]) $html .= '<div class="mb_banner_img"><img src="' . $item["img"] . '" alt=""></div>';
            if ($item["title"]) $html .= '<div class="mb_banner_title">' . $item["title"] . '</div>';
            if ($item["small_title"]) $html .= '<div class="mb_banner_small_title">' . $item["small_title"] . '</div>';
            if ($item["btn"]) $html .= '<div class="mb_btn_box"><div class="mb_banner_btn">' . $item["btn"] . '</div></div>';
        }

        if ($item["link"]) {
            $html .= '</a>';
        } else {
            $html .= '</div>';
        }

        return $html;

    }

}

?>


