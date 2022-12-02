<?php
require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/header.php');
$APPLICATION->SetTitle("Инструкция к прогнозам");
?>


    <div class="faq_wrapper">
        <p>Здравствуйте уважаемые любители футбола и прогнозов!</p>

        <p>В очередной раз перед стартом мирового первенства запускаем проект со ставками на интерес.</p>

        <p>Каждый прогноз, в зависимости от полноты заполнения, будет участвовать в различных рейтингах.</p>

        <p>Итак – как же это реализовано.</p>

        <div class="accordion" id="accordionExample">
            <div class="accordion-item">
                <h6 class="accordion-header" id="headingOne">
                    <button class="accordion-button" type="button" data-bs-toggle="collapse"
                            data-bs-target="#collapseOne"
                            aria-expanded="true" aria-controls="collapseOne">
                        Общая информация
                    </button>
                </h6>
                <div id="collapseOne" class="accordion-collapse collapse show" aria-labelledby="headingOne"
                     data-bs-parent="#accordionExample">
                    <div class="accordion-body">

                        <img src="/p/faq/img/sc11.png" alt="">

                        <p>Вы выбираете матч (ближайший)</p>

                        <p>Заходите в режим прогноза нажимая <b class="text-success"><i class="bi bi-pencil-square"></i></b>
                            и заполняете
                            результат матча и, по желанию, значения важных статистических событий матча.</p>

                        <img src="/p/faq/img/sc12.jpg" alt="">

                        <h6>В режиме <b class="text-success">любитель</b> заполняете:</h6>
                        <ul>
                            <li><b class="text-success"><i class="fa fa-futbol-o" aria-hidden="true"></i></b> счет матча
                            </li>
                        </ul>
                        <h6>Автоматически проставляются:</h6>
                        <ul>
                            <li><b class="text-success"><i class="fa fa-trophy" aria-hidden="true"></i></b> результат
                                матча
                            </li>
                            <li><b class="text-success">sum</b> сумма всех голов матча</li>
                            <li><b class="text-success">+/-</b> разница забитых мячей</li>
                        </ul>

                        <h6>Профессиональный режим позволяет:</h6>

                        <h6>Изменить выставленные значения:</h6>
                        <ul>
                            <li><b class="text-success"><i class="fa fa-trophy" aria-hidden="true"></i></b> результат
                                матча
                            </li>
                            <li><b class="text-success">sum</b> сумма всех голов матча</li>
                            <li><b class="text-success">+/-</b> разница забитых мячей</li>
                        </ul>
                        <h6>Проставить значения:</h6>

                        <ul>
                            <li><b class="text-success">%</b> процент владения мячом (бегунком или в верхнее поле ввода)
                                (рекомендуем от 25
                                до 75)
                            </li>
                            <li><b style="color: yellow"><i class="bi bi-file-fill"></i></b> количество желтых карточек
                                (рекомендуем от 0 до
                                8/9)
                            </li>
                            <li><b style="color: red"><i class="bi bi-file-fill"></i></b> количество красных карточек
                                (рекомендуем от 0 до
                                1/2)
                            </li>
                            <li><b class="text-success"><i class="bi bi-flag"></i></b> количество угловых на все команды
                                (рекомендуем от 0
                                до 16)
                            </li>
                            <li><b class="text-success">pen</b> количество пенальти на все команды (рекомендуем от 0 до
                                2/3)
                                (важное
                                примечание – в стадии на вылет количество пенальти может превышать 10)
                            </li>
                        </ul>
                        <p class="text-info">все рекомендации несут информативную нагрузку для участников не владеющих
                            высокими познаниями в
                            футбольной
                            статистике, но желающими поучаствовать во всех рейтингах</p>
                        <br>
                    </div>
                </div>
            </div>
            <div class="accordion-item">
                <h6 class="accordion-header" id="headingTwo">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                            data-bs-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
                        Описание рейтингов
                    </button>
                </h6>
                <div id="collapseTwo" class="accordion-collapse collapse" aria-labelledby="headingTwo"
                     data-bs-parent="#accordionExample">
                    <div class="accordion-body">
                        <ol>
                            <li>Сводный рейтинг - рейтинг состоящий и суммы значений рейтингов представленных ниже</li>
                            <li>Счет матча – <b>(точное совпадение)</b> – <i class="text-success">10 баллов</i></li>
                            <li>Исход матча – <b>(основное время) – (точное совпадение п1/н/п2)</b> - <i class="text-success">5 баллов</i>
                            </li>
                            <li>Сумма голов за матч – <b>(точное совпадение)</b> - <i class="text-success">5 баллов</i></li>
                            <li>Разница мячей – <b>(точное совпадение. пример:-1/0/2)</b> - <i class="text-success">5 баллов</i></li>
                            <li> % владения мячом – <b>(прогрессивная шкала)</b> - <i class="text-success">5 баллов за точное совпадение, 3 балла за отклонение не более чем в 5%, 1 балл за
                                    отклонение не более чем в 10%</i></li>
                            <li>Количество желтых карточек в матче - <b>(прогрессивная шкала)</b> - <i class="text-success">5 баллов точное
                                    совпадение, разница не более
                                    чем в 1 карточку – 3 балла, разница не более чем в 2 карточки – 1 балл</i>
                            </li>
                            <li>Количество красных карточек в матче – <b>(прогрессивная шкала)</b> - <i class="text-success">при значении в
                                    предсказании в 0(ноль)
                                    карточек и таком же исходе матча – 0,5(пол балла), при значении в предсказании в 1 карточку и таком же в
                                    исходе матча – 5 баллов, если предсказание и количество карт в матче больше 1 карточки, то начисляется 5
                                    баллов + 2 балла за каждую следующую карточку </i></li>

                            <li>Количество угловых за матч – <b>(прогрессивная шкала)</b> - <i class="text-success">5 баллов точное совпадение, разница не более чем в 1 угловой –
                                    3 балла, разница не более чем в 2 угловых – 1 балл</i></li>
                            <li>Количество пенальти – <b>(прогрессивная шкала):</b></li>
                        </ol>
                        <ul>
                            <li>Вариант группового этапа – <i class="text-success">при значении в предсказании в 0(ноль) пенальти и таком же в исходе матча 0,5(пол
                                    балла), при значении в предсказании в 1 пенальти и таком же исходе матча – 5 баллов, если предсказание и количество
                                    пенальти в матче больше 1 пенальти, то начисляется 5 баллов + 2 балла за каждое следующее пенальти</i></li>
                            <li>Вариант матчей на вылет – <i class="text-success">1 балл за предсказанное отсутствие пенальти (0 пенальти в соответствующей графе), 5
                                    баллов если серия послематчевых пенальти будет (обычные пенальти в матче не учитываются на данном этапе)</i></li>
                        </ul>

                        <p class="text-danger" style="font-size: 11px;">* Если вы хотите чтобы значение какого то параметра было
                            0(ноль), то вам потребуется его
                            проставить, ибо система посчитает, что вы пропустии данный параметр</p>
                    </div>
                </div>
            </div>
            <div class="accordion-item">
                <h6 class="accordion-header" id="headingThree">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                            data-bs-target="#collapseThree" aria-expanded="false" aria-controls="collapseThree">
                        Методика заполнения прогнозов
                    </button>
                </h6>
                <div id="collapseThree" class="accordion-collapse collapse" aria-labelledby="headingThree"
                     data-bs-parent="#accordionExample">
                    <div class="accordion-body">
                        <h6>Верхний блок – предпочтительнее для заполнения с компьютера.</h6>
                        <img src="/p/faq/img/21.png" alt="">
                        <h6>Значения вводятся в поля – input :</h6>

                        <p>&#127942; (п1/н/п2 - исход матча),sum(сумма голов),+/-(разница мячей) – заполняются автоматически, но вы можете их менять по своему усмотрению.
                            &#9917;(голы обеих команд) – по умолчанию 0-0</p>
                        
                       <p> <i class="bi bi-file-fill"></i>(карточки – желтые и красные), <i class="bi bi-flag"></i>(угловые), pen(пенальти) – проставляются участником – пустые поля означают что данное событие проигнорировано
                       </p>
                        
                       <p> %(процент владения мячом) – заполняется только верхнее поле – бегунок и нижнее значение вычисляются из верхнего</p>

                        <h6>Нижний блок – упрощенный ввод для мобильной версии сайта</h6>
                        <img src="/p/faq/img/22.png" alt="">

                        <p>Все значения кроме % владения мячом проставляются последовательностью нажатия кнопок «+1», «+3» и так далее.
                            Например количество угловых требуется указать 7 – это +3+3+1</p>
                        <p>Если надо сбросить значение – то жмем 0</p>
                        <p>Отрицательные значения сознательно не выводились на панель дабы еще больше ее не перегружать элементами</p>

                    </div>
                </div>
            </div>
            <div class="accordion-item">
                <h6 class="accordion-header" id="headingThree">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                            data-bs-target="#collapse4" aria-expanded="false" aria-controls="collapse4">
                        Правила расчета игра плей-офф
                    </button>
                </h6>
                <div id="collapse4" class="accordion-collapse collapse" aria-labelledby="heading4"
                     data-bs-parent="#accordionExample">
                    <div class="accordion-body">
                        <h6 class="text-info">Игры плей-офф не подразумевают наличие ничьи как исхода матча.</h6>
                        <p>В случае ничьи в основное время, выигрывает одна команда, либо в фазе дополнительного времени, либо в серии пенальти.</p>

                        <p class="text-danger">Технически запрет на проставление ничьи не будет реализован – так что если вы оставите ничью – вы потеряете баллы.</p>
                        <h6>Итоговый счет матча будет состоять из:</h6>
                        <ul>
                            <li>суммы голов основного времени;</li>
                            <li>дополнительного времени;</li>
                            <li>+1 гол победителю в серии пенальти.</li>
                        </ul>

                        <p class="text-info">Все это вы вписываете в счет матча без разделения на этапы матча.</p>

                        <h6 class="text-success">Также вводятся 2 поощряемых дополнительных события:</h6>

                        <ul>
                            <li>Дополнительное время – в случае его отсутствия и ставки что его не будет – начисляется 0,5 балла, в случае если доп. время будет назначено и в ставке также будет указано его наличие – 5 баллов.
                            </li>
                            <li>Серия пенальти - в случае отсутствия серии и ставки что серии не будет – начисляется 0,5 балла, в случае если серия пенальти будет назначена и в ставке также будет указано ее наличие – 5 баллов.
                            </li>
                        </ul>

                    </div>
                </div>
            </div>
        </div>
    </div>
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

<? require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php");