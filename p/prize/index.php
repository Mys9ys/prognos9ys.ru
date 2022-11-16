<?php
require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/header.php');
?>

<div class="prize_wrapper">
    <img src="img/big.jpg" alt="">
    <h6>Условия:</h6>
    <p>По условиям конкурса участники соревнуются друг с другом за лидерство в рейтинге прогнозистов. Каждый матч
        состоит из множества событий, которые предлагается предсказать.
        Некоторые события имеют жесткие рамки, а некоторые – прогрессивную шкалу. Правильные предсказания приносят
        баллы, которые суммируются в результат за матч и в итоговый результат за весь турнир.
    </p>

    <h6>Участвуют следующие события:</h6>

    <ol>
        <li>Счет матча – <b>(точное совпадение)</b> – <i class="text-success">10 баллов</i></li>
        <li>Исход матча – <b>(основное время) – (точное совпадение п1/н/п2)</b> - <i class="text-success">5 баллов</i>
        </li>
        <li>Сумма голов за матч – <b>(точное совпадение)</b> - <i class="text-success">5 баллов</i></li>
        <li>Разница мячей – <b>(точное совпадение. пример:-1/0/2)</b> - <i class="text-success">5 баллов</i></li>
        <li>Количество желтых карточек в матче - <b>(прогрессивная шкала)</b> - <i class="text-success">5 баллов точное
                совпадение, разница не более
                чем в 1 карточку – 3 балла, разница не более чем в 2 карточки – 1 балл</i>
        </li>
        <li>Количество красных карточек в матче – <b>(прогрессивная шкала)</b> - <i class="text-success">при значении в предсказании в 0(ноль)
                карточек и таком же исходе матча – 0,5(пол балла), при значении в предсказании в 1 карточку и таком же в
                исходе матча – 5 баллов, если предсказание и количество карт в матче больше 1 карточки, то начисляется 5
                баллов + 2 балла за каждую следующую карточку </i></li>
        <li>-<b></b> - <i class="text-success"></i></li>
        <li>-<b></b> - <i class="text-success"></i></li>
        <li>-<b></b> - <i class="text-success"></i></li>
        <li>-<b></b> - <i class="text-success"></i></li>
        <li>-<b></b> - <i class="text-success"></i></li>
    </ol>
</div>

<p>
    Счет матча – (точное совпадение) – 10 баллов
    Исход матча – (основное время) – (точное совпадение п1/н/п2) – 5 баллов
    Сумма голов за матч – (точное совпадение) – 5 баллов
    Разница мячей – (точное совпадение. пример:-1/0/2) – 5 баллов
    % владения мячом – (прогрессивная шкала) – 5 баллов за точное совпадение, 3 балла за отклонение не более чем в 5%, 1 балл за отклонение не более чем в 10%
    Количество желтых карточек в матче (прогрессивная шкала) – 5 баллов точное совпадение, разница не более чем в 1 карточку – 3 балла, разница не более чем в 2 карточки – 1 балл
    Количество красных карточек в матче (прогрессивная шкала) – при значении в предсказании в 0(ноль) карточек и таком же исходе матча – 0,5(пол балла), при значении в предсказании в 1 карточку и таком же в исходе матча – 5 баллов, если предсказание и количество карт в матче больше 1 карточки, то начисляется 5 баллов + 2 балла за каждую следующую карточку
    Количество угловых за матч – (прогрессивная шкала) – 5 баллов точное совпадение, разница не более чем в 1 угловой – 3 балла, разница не более чем в 2 угловых – 1 балл
    Количество пенальти – (прогрессивная шкала):
    А) Вариант группового этапа – при значении в предсказании в 0(ноль) пенальти и таком же в исходе матча 0,5(пол балла), при значении в предсказании в 1 пенальти и таком же исходе матча – 5 баллов, если предсказание и количество пенальти в матче больше 1 пенальти, то начисляется 5 баллов + 2 балла за каждое следующее пенальти
    Б) Вариант матчей на вылет – 1 балл за предсказанное отсутствие пенальти (0 пенальти в соответствующей графе), 5 баллов если серия послематчевых пенальти будет (обычные пенальти в матче не учитываются на данном этапе).

    Так же следует учесть что не проставленное нулевое значение интерпретируется системой как пропуск соответствующего пункта предсказания.

    Призовые места:

    1.	Место – 10 000 рублей
    2.	Место – 5 000 рублей
    3.	Место – 3 000 рублей
    4-10 места – 1 000 рублей
    11-20 места – 500 рублей

    Выплаты производятся банковским переводом на карту сбербанка или другой российский банк через быстрые платежи СБП

</p>

<style>
    .prize_wrapper {
        width: 400px;
        max-width: 98%;
        margin: 0 auto;
        color: #fff;
        background: #253133;
        border-radius: 5px;
        padding: 5px;
        font-size: 14px;
    }

    .prize_wrapper img {
        max-width: 98%;
        margin: 0 auto;

    }
</style>