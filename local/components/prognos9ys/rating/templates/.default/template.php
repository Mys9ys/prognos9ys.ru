<?php if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die(); ?>

<?php
$arrSelector = [
    "all" => "Обший",
    "score" => 'Счет',
    "result" =>'Исход',
    "sum" =>'Сумма',
    "diff"=>'Разница',
    "domination"=>'% владения',
    "yellow"=>'Желтые',
    "red"=>'Красные',
    "corner"=>'Угловые',
    "penalty"=>'Пенальти',
    "best_score" => "лучшие прогнозы"
];

?>

<div class="rating_wrapper">
 <div class="r_title_box">
     <div class="r_title r_title_big">Рейтинги после <?=$arResult["count"]?> матча(чей)</div>
 </div>
    <nav>
        <div class="nav nav-tabs" id="nav-tab" role="tablist">
            <?php $show = 0;
            foreach ($arrSelector as $selector=>$name):?>
                <button class="r_prog_btn nav-link <?if($show===0) echo 'active'; ?>" id="nav-profile-tab" data-bs-toggle="tab" data-bs-target="#nav-<?=$selector?>" type="button" role="tab" aria-controls="nav-<?=$selector?>">
                    <div class="r_prog_btn_text"><?=$name?></div>
                </button>
            <?$show++ ?>
            <?php endforeach;?>
      </div>
    </nav>
    <div class="tab-content" id="nav-tabContent">
        <?php $show = 0;
        foreach ($arrSelector as $selector=>$name):?>
            <div class="tab-pane fade <?if($show===0) echo 'show active'; ?>" id="nav-<?=$selector?>" role="tabpanel" aria-labelledby="nav-home-tab" tabindex="0">
                <div class="r_title_box">
                    <div class="r_title"><?=$name?> - рейтинг</div>
                </div>
                <table class="table table-dark table-hover">
                    <thead>
                        <tr>
                            <th scope="col">#</th>
                            <th scope="col">Ник</th>
                            <th scope="col">Баллы</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($arResult[$selector] as $key=>$item):?>
                            <tr>
                                <th><?=$key+1?></th>
                                <td><?=$item['nick']?></td>
                                <td><?=$item['score']?></td>
                            </tr>
                        <?php endforeach;?>
                    </tbody>
                </table>
            </div><?$show++ ?>
        <?php endforeach;?>
    </div>
</div>
