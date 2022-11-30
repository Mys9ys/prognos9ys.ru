<?php if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();
$APPLICATION->SetTitle("Профиль " . $arResult["name"]);
?>

<div class="profile_wrapper">
    <h2>Профиль <?= $arResult["name"] ?></h2>
    <div class="ref_wrapper">
        <div class="ref_box">
            <?php if ($arResult['ref_nik']): ?>
                <div class="ref_box">
                    Приглашен: <?= $arResult['ref_nik'] ?>
                </div>
            <?php endif; ?>

            <div class="ref_box">
                <span>Приглашено: <?= $arResult["you_ref"]["count"] ?: 0 ?></span>
                <span>Из них активны: <?= $arResult["you_ref"]["active"] ?: 0 ?></span>
            </div>
        </div>


        <div class="profile_prognosis_wrapper">
            <div class="profile_prognosis_title">
                Прогнозы пользователя
            </div>
            <div class="accordion" id="accordionExample">
                <?php foreach ($arResult["items"] as $key=>$item):?>
                    <div class="accordion-item">
                    <h6 class="accordion-header" id="headingTwo">
                        <button class="accordion-button" type="button" data-bs-toggle="collapse"
                                data-bs-target="#collapse<?=$key?>" aria-expanded="true" aria-controls="collapse<?=$key?>">
                            Матч № <?=$key?> <span class="badge_pr badge"><?= $arResult["active_count"] ?></span>
                        </button>
                    </h6>
                    <div id="collapse<?=$key?>" class="accordion-collapse collapse" aria-labelledby="heading<?=$key?>"
                         data-bs-parent="#accordionExample">
                        <div class="accordion-body">
                            <table class="table table-dark table-hover om_table_box">
                                <thead>
                                <tr>
                                    <th class="pr_table_col pr_table_th" >#</th>
                                    <th class="pr_table_col" >Счет</th>
                                    <th class="pr_table_col" ><i class="fa fa-trophy" aria-hidden="true"></i></th>
                                    <th class="pr_table_col" >sum</th>
                                    <th class="pr_table_col" >+/-</th>
                                    <th class="pr_table_col" >%</th>
                                    <th class="pr_table_col" ><i class="bi bi-file-fill" style="color:yellow"></i></th>
                                    <th class="pr_table_col" ><i class="bi bi-file-fill" style="color:red"></i></th>
                                    <th class="pr_table_col" ><i class="bi bi-flag"></i></th>
                                    <th class="pr_table_col" >pen</th>
                                    <th class="pr_table_col" >all</th>
                                </tr>
                                </thead>
                                <tbody>
                                <tr>
                                    <th class="pr_table_col pr_table_th">Ставка</th>
                                        <?php $arPrognosis = $item["match_prognosis"];?>

                                        <td class="pr_table_col" ><?=$arPrognosis["home_goals"]?> - <?=$arPrognosis["guest_goals"]?></td>
                                        <td class="pr_table_col" ><?= $arPrognosis["result"]?></td>
                                        <td class="pr_table_col" ><?= $arPrognosis["sum"]?></td>
                                        <td class="pr_table_col" ><?= $arPrognosis["diff"]?></td>
                                        <td class="pr_table_col" ><?= $arPrognosis["domination"] ?> - <?=100- $arResult["main"]["domination"] ?></td>
                                        <td class="pr_table_col" ><?= $arPrognosis["yellow"]?></td>
                                        <td class="pr_table_col" ><?= $arPrognosis["red"]?></td>
                                        <td class="pr_table_col" ><?= $arPrognosis["corner"]?></td>
                                        <td class="pr_table_col" ><?= $arPrognosis["penalty"]?></td>
                                        <td class="pr_table_col" ></td>

                                </tr>

                                <tr>
                                    <th class="pr_table_col pr_table_th">Итог матча</th>
                                    <?$mResult = $item["match_result"]?>

                                    <td class="pr_table_col" ><span class="text-info"><?=$mResult['score']?></span></td>
                                    <td class="pr_table_col" ><span class="text-info"><?= $mResult["result"]?></span></td>
                                    <td class="pr_table_col" ><span class="text-info"><?= $mResult["sum"]?></span></td>
                                    <td class="pr_table_col" ><span class="text-info"><?= $mResult["diff"]?></span></td>
                                    <td class="pr_table_col" ><span class="text-info"><?= $mResult["domination"]?></span></td>
                                    <td class="pr_table_col" ><span class="text-info"><?= $mResult["yellow"]?></span></td>
                                    <td class="pr_table_col" ><span class="text-info"><?= $mResult["red"]?></span></td>
                                    <td class="pr_table_col" ><span class="text-info"><?= $mResult["corner"]?></span></td>
                                    <td class="pr_table_col" ><span class="text-info"><?= $mResult["penalty"]?></span></td>
                                    <td class="pr_table_col" ></td>
                                </tr>

                                <tr>
                                    <th class="pr_table_col pr_table_th">Баллы</th>
                                    <?$uScore = $item["user_score"]?>
                                    <td class="pr_table_col" ><?=$uScore['score']?></td>
                                    <td class="pr_table_col" ><?= $uScore["result"]?></td>
                                    <td class="pr_table_col" ><?= $uScore["sum"]?></td>
                                    <td class="pr_table_col" ><?= $uScore["diff"]?></td>
                                    <td class="pr_table_col" ><?= $uScore["domination"]?></td>
                                    <td class="pr_table_col" ><?= $uScore["yellow"]?></td>
                                    <td class="pr_table_col" ><?= $uScore["red"]?></td>
                                    <td class="pr_table_col" ><?= $uScore["corner"]?></td>
                                    <td class="pr_table_col"><?= $uScore["penalty"]?></td>
                                    <td class="pr_table_col" ><?=$uScore['all']?></td>
                                </tr>

                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endforeach;?>
            </div>
        </div>

    </div>


