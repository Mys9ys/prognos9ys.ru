<?php if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die(); ?>
<?php

$comp = new FillKVNGameList()
?>


    <div class="kvn_game_wrapper">
        <?= $comp->fillTitleBlock($arResult["main"]) ?>

<!--        --><?php //foreach ($arResult["main"]["stage"] as $id => $stage): ?>
<!--            --><?//= $comp->fillGameStage($stage, $arResult["main"]["teams"]) ?>
<!--        --><?php //endforeach; ?>
        <div class="kgps_score_block">
            <div class="kgpss_max">5</div>
            <div class="kgpss_plus">+</div>
            <div class="kgpss_score">0</div>
            <div class="kgpss_minus">-</div>
            <div class="kgpss_min">4</div>
        </div>
    </div>


<?php

class FillKVNGameList
{

    public function fillTitleBlock($data)
    {

        $html = '<div class="kvn_game_title_wrapper"><div class="kvn_game_title_block">';

        $date = ' <div class="kgtb_date kgtb_cell">
                    <i class="bi bi-calendar4-event"></i> ' . $data["date"] . '
                  </div>';

        $time = ' <div class="kgtb_time kgtb_cell">
                    <i class="bi bi-alarm"></i> ' . $data["time"] . '
                 </div>';

        $number = '<div class="kgtb_number kgtb_cell">#
                   ' . $data["number"] . '
                   </div>';

        $name = ' <div class="kgtb_name kgtb_cell">
                    <i class="bi bi-joystick"></i> ' . $data["name"] . '
                  </div>';

        $score = '';

        if ($data["score"]) {
            $score = '<div class="kgtb_score kgtb_cell">
                        <i class="fa fa-sticky-note" aria-hidden="true"></i> ' . $data["score"] . '
                      </div>';
        }

        $html .= $date . $time . $number . $name . $score . '</div></div>';

        return $html;
    }

    public function fillGameStage($data, $teams)
    {

        $max = $data['max'];
        $min = $max - 1;
        $html = '<div class="kg_prognos_wrapper">
                    <div class="kgps_title_block">
                        <div class="kgps_title kgps_elem">'.$data['name'].'</div>
                        <div class="kgps_max_score kgps_elem">Максимум ' . $max . ' баллов</div>
                    </div>                    
                    <div class="kg_prognos_stage_block">';

        $table = '<div class="kgps_table">                            
                        <div class="kgps_table_line kgps_table_title">
                            <div class="kgps_team_name kgps_elem">Команда</div>
                            <div class="kgps_input kgps_elem"><i class="bi bi-pencil"></i></div>
                            <div class="kgps_popular kgps_elem">Популярный счет</div>                                    
                            <div class="kgps_place kgps_elem">№</div>                                    
                        </div>';
        $count = 0;
        $pop_btn = '<div class="kgps_popular kgps_popular_block">';
        for ($i = $max; $i > $min; $i -= 0.2) {
            $pop_btn .= '<div class="kgps_popular_btn">' . $i . '</div>';
        }
        $pop_btn .= '</div>';
        foreach ($teams as $team) {
            $el = '<div class="kgps_table_line">
                        <div class="kgps_team_name" title="' . $team["NAME"] . '">' . $team["NAME"] . '</div>
                        <input class="kgps_input" type="number" step="0.1" min="4" max="5">'
                . $pop_btn .
                '<div class="kgps_place kgps_elem">0</div>                   
                    </div>';
            $table .= $el;
            $count++;
        }

        $table .= '</tbody></div>';

        $html .= $table . '</div></div>';

        return $html;
    }

}