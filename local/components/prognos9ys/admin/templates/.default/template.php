<?php if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die(); ?>

<div class="admin_wrapper">



    <br>
    <h3>Количество прогнозов</h3>

    <table class="table table-dark table-hover">
        <thead>
        <tr>
            <th scope="col">#</th>
            <th scope="col">Ник</th>
            <th scope="col">Количество</th>
        </tr>
        </thead>
        <tbody>
        <?php $key = 1;
        foreach ($arResult["prognosis"] as $name=>$item):?>
            <tr>
                <th><?=$key++?></th>
                <td><?=$name?></td>
                <td><?=count($item)?></td>
            </tr>
        <?php endforeach;?>
        </tbody>
    </table>

    <br>

    <h3>Рефералы</h3>

    <table class="table table-dark table-hover">
        <thead>
        <tr>
            <th scope="col">#</th>
            <th scope="col">Ник</th>
            <th scope="col">Количество</th>
        </tr>
        </thead>
        <tbody>
        <?php $key = 1;
        foreach ($arResult["ref"] as $name=>$item):?>
            <tr>
                <th><?=$key++?></th>
                <td><?=$name?></td>
                <td><?=count($item)?></td>
            </tr>
        <?php endforeach;?>
        </tbody>
    </table>

    <h3>Матч кол-во прогнозов</h3>

    <table class="table table-dark table-hover">
        <thead>
        <tr>
            <th scope="col">#</th>
            <th scope="col">Матч</th>
            <th scope="col">Количество</th>
        </tr>
        </thead>
        <tbody>
        <?php $key = 1;
        foreach ($arResult["matches"] as $name=>$item):?>
            <tr>
                <th><?=$key++?></th>
                <td><?=$name?></td>
                <td><?=count($item)?></td>
            </tr>
        <?php endforeach;?>
        </tbody>
    </table>


</div>


