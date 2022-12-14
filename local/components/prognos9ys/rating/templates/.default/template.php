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
    "otime"=>'Доп. время',
    "spenalty"=>'Серия пенальти',
    "best_score" => "Лучшие прогнозы"
];

?>
<?//=dump($arResult["all_number"])?>
<?//=dump($arResult["all_change"])?>

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
            <?$allCalc = 'all-calc'?>
            <button class="r_prog_btn nav-link <?if($show===0) echo 'active'; ?>" id="nav-profile-tab" data-bs-toggle="tab" data-bs-target="#nav-<?=$allCalc?>" type="button" role="tab" aria-controls="nav-<?=$allCalc?>">
                <div class="r_prog_btn_text">Прогрес рейтинга</div>
            </button>
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
                                <?if($item["match"]){?>
                                    <td><?=$item['match'] - 42?></td>
                                <?} else {?>
                                    <th><?=$key+1?></th>
                                <?}?>
                                <td><?=$item['nick']?></td>
                                <td><?=$item['score']?></td>
                            </tr>
                        <?php endforeach;?>
                    </tbody>
                </table>
            </div><?$show++ ?>
        <?php endforeach;?>
        <?$show++ ?>

        <div class="tab-pane fade <?if($show===0) echo 'show active'; ?>" id="nav-<?=$allCalc?>" role="tabpanel" aria-labelledby="nav-home-tab" tabindex="0">
            <div class="r_title_box">
                <div class="r_title">Прогресс рейтинга</div>
            </div>

            <?$optionEl = $tabEl = $controlCount = count($arResult["all_result"]);?>
            <div class="tab_progress_rating_select_wrapper">
                <select class="tab_progress_rating_select">
                    <?for ($optionEl; $optionEl>0;$optionEl--):?>
                        <option value="<?=$optionEl?>">Матч <?=$optionEl?></option>
                    <?endfor;?>
                </select>
            </div>
            <div class="tab_progress_rating_box">
                <?for ($tabEl; $tabEl>0;$tabEl--):?>
                    <div class="tab tab_progress_rating <?if($controlCount === $tabEl) echo "tpr_active"?>">
                        <table class="table table-dark table-hover">
                            <thead>
                            <tr>
                                <th scope="col">#</th>
                                <th scope="col">  <i class="fa fa-line-chart" aria-hidden="true"></i>  </th>
                                <th scope="col">Ник</th>
                                <th scope="col">Баллы</th>

                            </tr>
                            </thead>
                            <tbody>
                            <? foreach ($arResult["all_result"][$tabEl] as $item): ?>
                                <tr>
                                    <th><?=$item['place']?></th>
                                    <td>
                                        <?
                                        if($item['diff']>0) echo "<span class='text-success'><i class='fa fa-long-arrow-up' aria-hidden='true'></i> " . $item['diff'] . "</span>";
                                        if($item['diff']<0) echo "<span class='text-danger'><i class='fa fa-long-arrow-down' aria-hidden='true'></i> " . abs ($item['diff']) . "</span>";
                                        if($item['diff']===0) echo "<span class='text-info'><i class='fa fa-minus' aria-hidden='true'></i></span>";
                                        ?>
                                    </td>
                                    <td><?=$item['nick']?></td>
                                    <td><?=$item['score']?></td>

                                </tr>
                            <? endforeach;?>

                            </tbody>
                        </table>
                    </div>
                <?endfor;?>
            </div>
        </div>
    </div>
</div>

<script>
    document.querySelector('select').addEventListener('change', function() {
        document.querySelectorAll('.tab').forEach((n, i) => {
            n.classList.toggle('tpr_active', i === this.selectedIndex);
        });
    });
</script>
