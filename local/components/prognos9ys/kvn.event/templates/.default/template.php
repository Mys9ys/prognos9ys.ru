<?php if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die(); ?>
<?php
$fillCl = new FillKVNGameList();
//var_dump($arResult);
?>
<div class="kvn_event_wrapper">
    <?php $gameID = 1; ?>
    <?php foreach ($arResult["items"] as $item):?>
    <div class="kvn_game_box">

        <?= $fillCl->fillTitleBlock($item)?>
        <?= $fillCl->fillDescriptionBlock($item)?>
        <?= $fillCl->fillBtnBlock($item)?>

    </div>
    <?php endforeach;?>
</div>

<?php
class FillKVNGameList
{

    public function fillTitleBlock($data){

        $html = '<div class="kvn_game_title_block">';

        $date = ' <div class="kgtb_date kgtb_cell">
                    <i class="bi bi-calendar4-event"></i> '.$data["date"].'
                  </div>';

        $time = ' <div class="kgtb_time kgtb_cell">
                    <i class="bi bi-alarm"></i> '.$data["time"].'
                 </div>';

        $number = '<div class="kgtb_number kgtb_cell">
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

        $html .= $date . $time . $number . $name .  $score .'</div>';

        return $html;
    }

    public function fillDescriptionBlock($data){

        $html = '<div class="accordion kg_description_block" id="accordionExample"><div class="accordion-item">';
        $AccordTitle = '<h6 class="accordion-header" id="heading'.$data["number"].'game">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                            data-bs-target="#collapse'.$data["number"].'game"
                            aria-expanded="false" aria-controls="collapse'.$data["number"].'game">Подробнее
                    </button>
                </h6>';

        $AccordBody = '<div id="collapse'.$data["number"].'game" class="accordion-collapse collapse"
                     aria-labelledby="heading'.$data["number"].'game"
                     data-bs-parent="#accordionExample">
                    <div class="accordion-body">';

        $table = '<table class="table table-striped-columns kvn_table">
                            <thead>
                                <tr>
                                    <th scope="col">Команда</th>
                                    <th scope="col"><span class="kvn_span_show">1</span> <span class="kvn_span_hide">Приветствие</span></th>
                                    <th scope="col"><span class="kvn_span_show">2</span> <span class="kvn_span_hide">Биатлон</span></th>
                                    <th scope="col"><span class="kvn_span_show">3</span> <span class="kvn_span_hide">Домашка</span></th>
                                    <th scope="col"><span class="kvn_span_show">S</span> <span class="kvn_span_hide">Результат</span></th>
                                </tr>
                            </thead>
                            <tbody>';
        $count = 0;
        foreach ($data["teams"] as $team){
            $el = '<tr>
                        <td>'.$team["NAME"].'</td>
                        <td>'.$data["stage1"][$count].'</td>
                        <td>'.$data["stage2"][$count].'</td>
                        <td>'.$data["stage3"][$count].'</td>
                        <td>'.$data["result"][$count].'</td>
                    </tr>';
            $table .= $el;
            $count ++;
        }

        $table .='</tbody></table>';

        $AccordBody .= $table . '</div></div>';

        $html .= $AccordTitle . $AccordBody .'</div></div>';

        return $html;
    }

    public function fillBtnBlock($data)
    {
        $html = '<div class="kg_btn_box">';
        $write = '<div class="kgb_status no_write">не заполнено</div>';
        $btn = '<a class="kgb_write_btn" href="/p/kvngame/'.$data["number"].'/"> Заполнить <i class="bi bi-pencil"></i></a>';
        if($data['write']) {
            $write = '<div class="kgb_status yes_write"><i class="bi bi-check-lg"></i> '. $data["write"] . '</div>';
            $btn = '<a class="kgb_write_btn" href="/p/kvngame/'.$data["number"].'/"> Изменить <i class="bi bi-pencil-square"></i></a>';
        }
        if ($data["active"] === 'N') $btn = '<a class="kgb_write_btn kgb_watch" href="/p/kvngame/'.$data["number"].'/"> Посмотреть <i class="bi bi-eye-fill"></i></a>';
        $html .= $write . $btn . '</div>';

        return $html;
    }

}

?>




