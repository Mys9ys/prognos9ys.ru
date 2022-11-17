<?php
require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/header.php');
?>

<div class="faq_wrapper">
    <p>Здравствуйте уважаемые любители футбола и прогнозов!</p>

    <p>В очередной раз перед стартом мирового первенства запускаем проект со ставками на интерес.</p>

    <p>Каждый прогноз, в зависимости от полноты заполнения, будет участвовать в различных рейтингах.</p>

    <p>Итак – как же это реализовано.</p>

    <img src="/p/faq/img/sc1.png" alt="">

    <p>Вы выбираете матч (ближайший)</p>

    <p>Заходите в режим прогноза нажимая <b class="text-success"><i class="bi bi-pencil-square"></i></b> и заполняете
        результат матча и, по желанию, значения важных статистических событий матча.</p>

    <img src="/p/faq/img/sc2.jpg" alt="">

    <h6>В режиме <b class="text-success">любитель</b> заполняете:</h6>
    <ul>
        <li><b class="text-success"><i class="fa fa-futbol-o" aria-hidden="true"></i></b> счет матча</li>
    </ul>
    <h6>Автоматически проставляются:</h6>
    <ul>
        <li><b class="text-success"><i class="fa fa-trophy" aria-hidden="true"></i></b> результат матча</li>
        <li><b class="text-success">sum</b> сумма всех голов матча</li>
        <li><b class="text-success">+/-</b> разница забитых мячей</li>

    </ul>

    <h6>Профессиональный режим позволяет:</h6>

    <h6>Изменить выставленные значения:</h6>
    <ul>
        <li><b class="text-success"><i class="fa fa-trophy" aria-hidden="true"></i></b> результат матча</li>
        <li><b class="text-success">sum</b> сумма всех голов матча</li>
        <li><b class="text-success">+/-</b> разница забитых мячей</li>
    </ul>
    <h6>Проставить значения:</h6>

    <ul>
        <li><b class="text-success">%</b> процент владения мячом (бегунком или в верхнее поле ввода) (рекомендуем от 25
            до 75)
        </li>
        <li><b style="color: yellow"><i class="bi bi-file-fill"></i></b> количество желтых карточек (рекомендуем от 0 до
            8/9)
        </li>
        <li><b style="color: red"><i class="bi bi-file-fill"></i></b> количество красных карточек (рекомендуем от 0 до
            1/2)
        </li>
        <li><b class="text-success"><i class="bi bi-flag"></i></b> количество угловых на все команды (рекомендуем от 0
            до 16)
        </li>
        <li><b class="text-success">pen</b> количество пенальти на все команды (рекомендуем от 0 до 2/3) (важное
            примечание – в стадии на вылет количество пенальти может превышать 10)
        </li>
    </ul>
    <p class="text-info">все рекомендации несут информативную нагрузку для участников не владеющих высокими познаниями в
        футбольной
        статистике, но желающими поучаствовать во всех рейтингах</p>
    <br>
    <h6>Описание рейтингов</h6>


    <ul>
        <li>главный рейтинг <b><i>сводный составляется по сумме баллов за все остальные рейтинги.</i></b></li>
        <li>рейтинг по счету <b><i>(за точно предсказанный счет начисляется 10 баллов)</i></b></li>
        <li>рейтинг по разнице мячей <b><i>(при счете 4-1 и при счете 3-0 разница будет одинаковой +3 – 5 баллов в
                    рейтинг)</i></b></li>
        <li>рейтинг по сумме голов в матче <b><i>(при счете 2-0 и 0-2 сумма будет одинаковой 2 – 5 баллов
                    рейтинг)</i></b></li>
        <li>рейтинг результата матча <b><i>(п1(победа1)-н(ничья)-п2(победа2) – 5 баллов в рейтинг)</i></b></li>
        <li>рейтинг по проценту владения мячом <b><i>(идеальное попадание – 5 баллов, +/- 5% от результата – 3 балла,
                    +/- 10% от результата – 1 балл)</i></b></li>
        <li>рейтинг по количеству желтых карточек <b><i>(идеальное попадание – 5 баллов, +/- 1 карточка – 3 балла, +/- 2
                    карточки – 1 балл)</i></b></li>
        <li>рейтинг по количеству красных карточек <b><i>(при верном прогнозе 0 карточек – 0,5 балла, при верном
                    прогнозе 1 карточка – 5 баллов, при верном 2 и выше – 5 + 2 балла за каждую дополнительную карточку,
                    так же при значении прогноза выше 2 и присутствии в итоговом протоколе матча красной карточки
                    начисляется 0,5 балла)</i></b>
        </li>
        <li>рейтинг по количеству угловых ударов в матче <b><i>(идеальное попадание – 5 баллов, +/- 1 угловой удар – 3
                    балла, +/-
                    2 угловых удара – 1 балл)</i></b></li>
        <li>рейтинг по количеству пенальти <b><i>(при верном прогнозе 0 пенальти – 0,5 балла, при верном прогнозе 1
                    пенальти – 5
                    баллов, при верном 2 и выше – 5 + 2 балла за каждое дополнительное пенальти в групповом этапе, так
                    же при значении
                    прогноза выше 2 и присутствии в итоговом протоколе матча группового этапа пенальти начисляется 0,5
                    балла)</i></b></li>
    </ul>
    <p class="text-danger" style="font-size: 11px;">* Если вы хотите чтобы значение какого то параметра было 0(ноль), то вам потребуется его
        проставить, ибо система посчитает, что вы пропустии данный параметр</p>
</div>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
<style>
    .faq_wrapper {
        width: 400px;
        max-width: 98%;
        margin: 0 auto;
        color: #fff;
        background: #253133;
        border-radius: 5px;
        padding: 5px;
        font-size: 14px;
    }

    .faq_wrapper img {
        max-width: 98%;
        margin: 0 auto;

    }
</style>