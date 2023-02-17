<?php if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die(); ?>
<?php
var_dump($arResult);
$comp = new FillKVNGameList()
?>


<div class="kvn_game_wrapper">
    <?= $comp->fillTitleBlock($arResult["main"]) ?>
    <?= $comp->fillGameStage($arResult["main"]) ?>
    <div class="kg_prognos_wrapper">
        <div class="kg_prognos_stage_block">

        </div>
    </div>
</div>


<?php

class FillKVNGameList {

    public function fillTitleBlock($data){

        $html = '<div class="kvn_game_title_wrapper"><div class="kvn_game_title_block">';

        $date = ' <div class="kgtb_date kgtb_cell">
                    <i class="bi bi-calendar4-event"></i> '.$data["date"].'
                  </div>';

        $time = ' <div class="kgtb_time kgtb_cell">
                    <i class="bi bi-alarm"></i> '.$data["time"].'
                 </div>';

        $number = '<div class="kgtb_number kgtb_cell">#
                   '.$data["number"].'
                   </div>';

        $name = ' <div class="kgtb_name kgtb_cell">
                    <i class="bi bi-joystick"></i> '.$data["name"].'
                  </div>';

        $score = '';

        if($data["score"]) {
            $score = '<div class="kgtb_score kgtb_cell">
                        <i class="fa fa-sticky-note" aria-hidden="true"></i> '.$data["score"].'
                      </div>';
        }

        $html .= $date . $time . $number . $name .  $score .'</div></div>';

        return $html;
    }

    public function fillGameStage($data){
        $html = '<div class="kg_prognos_wrapper">
                    <div class="kgps_title_block">
                        <div class="kgps_title kgps_elem">Приветствие</div>
                        <div class="kgps_max_score kgps_elem">Максимум 5 баллов</div>
                    </div>                    
                    <div class="kg_prognos_stage_block">';

        $table = '<div class="kgps_table">                            
                        <div class="kgps_table_line kgps_table_title">
                            <div class="kgps_team_name">Команда</div>
                            <div class="kgps_input"><i class="bi bi-pencil"></i></div>
                            <div class="kgps_popular">Популярный счет</div>                                    
                        </div>';
        $count = 0;
        foreach ($data["teams"] as $team){
            $el = '<div class="kgps_table_line">
                        <div class="kgps_team_name">'.$team["NAME"].'</div>
                        <input class="kgps_input" type="text">
                        <div class="kgps_popular kgps_popular_block">
                                <div class="kgps_popular_btn">5</div>
                                <div class="kgps_popular_btn">4.9</div>
                                <div class="kgps_popular_btn">4.8</div>
                                <div class="kgps_popular_btn">4.7</div>
                        </div>                       
                    </div>';
            $table .= $el;
            $count ++;
        }

        $table .='</tbody></div>';

        $html .= $table .'</div></div>';

        return $html;
    }

}