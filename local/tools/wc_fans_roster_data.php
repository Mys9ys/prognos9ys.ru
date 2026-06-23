<?php
declare(strict_types=1);

/**
 * Болельщики (М/Ж) и правитель на каждую из 48 сборных ЧМ-2026.
 * Популярные имена/фамилии страны + узнаваемый правитель.
 *
 * @return list<array{
 *   team:string,
 *   slug:string,
 *   fanM:array{name:string,login:string},
 *   fanF:array{name:string,login:string},
 *   ruler:array{name:string,login:string}
 * }>
 */
function wc_fans_roster(): array
{
    return [
        ['team' => 'Австралия', 'slug' => 'aus', 'fanM' => ['name' => 'Дж.Смит', 'login' => 'fanmaus'], 'fanF' => ['name' => 'Ш.Уилсон', 'login' => 'fanfaus'], 'ruler' => ['name' => 'Э.Албаниз', 'login' => 'ruleraus']],
        ['team' => 'Австрия', 'slug' => 'aut', 'fanM' => ['name' => 'Т.Грубер', 'login' => 'fanmaut'], 'fanF' => ['name' => 'А.Хубер', 'login' => 'fanfaut'], 'ruler' => ['name' => 'К.Нехаммер', 'login' => 'ruleraut']],
        ['team' => 'Алжир', 'slug' => 'alg', 'fanM' => ['name' => 'М.Бенали', 'login' => 'fanmalg'], 'fanF' => ['name' => 'Ф.Кадри', 'login' => 'fanfalg'], 'ruler' => ['name' => 'А.Теббун', 'login' => 'ruleralg']],
        ['team' => 'Англия', 'slug' => 'eng', 'fanM' => ['name' => 'О.Смит', 'login' => 'fanmeng'], 'fanF' => ['name' => 'О.Джонс', 'login' => 'fanfeng'], 'ruler' => ['name' => 'К.Стармер', 'login' => 'rulereng']],
        ['team' => 'Аргентина', 'slug' => 'arg', 'fanM' => ['name' => 'Х.Гарсия', 'login' => 'fanmarg'], 'fanF' => ['name' => 'М.Лопес', 'login' => 'fanfarg'], 'ruler' => ['name' => 'Х.Милей', 'login' => 'rulerarg']],
        ['team' => 'Бельгия', 'slug' => 'bel', 'fanM' => ['name' => 'Л.Питерс', 'login' => 'fanmbel'], 'fanF' => ['name' => 'Э.Дюбуа', 'login' => 'fanfbel'], 'ruler' => ['name' => 'А.Де Кроо', 'login' => 'rulerbel']],
        ['team' => 'Босния и Герцеговина', 'slug' => 'bih', 'fanM' => ['name' => 'А.Хаджич', 'login' => 'fanmbih'], 'fanF' => ['name' => 'Л.Ковачевич', 'login' => 'fanfbih'], 'ruler' => ['name' => 'Д.Бечирович', 'login' => 'rulerbih']],
        ['team' => 'Бразилия', 'slug' => 'bra', 'fanM' => ['name' => 'Ж.Силва', 'login' => 'fanmbra'], 'fanF' => ['name' => 'А.Сантос', 'login' => 'fanfbra'], 'ruler' => ['name' => 'Л.Лула', 'login' => 'rulerbra']],
        ['team' => 'Гаити', 'slug' => 'hai', 'fanM' => ['name' => 'Ж.Батист', 'login' => 'fanmhai'], 'fanF' => ['name' => 'М.Пьер', 'login' => 'fanfhai'], 'ruler' => ['name' => 'А.Анри', 'login' => 'rulerhai']],
        ['team' => 'Гана', 'slug' => 'gha', 'fanM' => ['name' => 'К.Менса', 'login' => 'fanmgha'], 'fanF' => ['name' => 'А.Осеи', 'login' => 'fanfgha'], 'ruler' => ['name' => 'Н.Акуфо-Аддо', 'login' => 'rulergha']],
        ['team' => 'Германия', 'slug' => 'ger', 'fanM' => ['name' => 'Т.Мюллер', 'login' => 'fanmger'], 'fanF' => ['name' => 'А.Шмидт', 'login' => 'fanfger'], 'ruler' => ['name' => 'О.Шольц', 'login' => 'rulerger']],
        ['team' => 'ДР Конго', 'slug' => 'cod', 'fanM' => ['name' => 'Ж.Кабила', 'login' => 'fanmcod'], 'fanF' => ['name' => 'М.Тшиломбо', 'login' => 'fanfcod'], 'ruler' => ['name' => 'Ф.Чисекеди', 'login' => 'rulercod']],
        ['team' => 'Египет', 'slug' => 'egy', 'fanM' => ['name' => 'М.Хасан', 'login' => 'fanmegy'], 'fanF' => ['name' => 'Ф.Али', 'login' => 'fanfegy'], 'ruler' => ['name' => 'А.Сиси', 'login' => 'ruleregy']],
        ['team' => 'Иордания', 'slug' => 'jor', 'fanM' => ['name' => 'А.Хасан', 'login' => 'fanmjor'], 'fanF' => ['name' => 'Л.Масри', 'login' => 'fanfjor'], 'ruler' => ['name' => 'Абдалла II', 'login' => 'rulerjor']],
        ['team' => 'Ирак', 'slug' => 'irq', 'fanM' => ['name' => 'А.Малики', 'login' => 'fanmirq'], 'fanF' => ['name' => 'Н.Рашид', 'login' => 'fanfirq'], 'ruler' => ['name' => 'М.Судани', 'login' => 'rulerirq']],
        ['team' => 'Иран', 'slug' => 'irn', 'fanM' => ['name' => 'Р.Ахмади', 'login' => 'fanmirn'], 'fanF' => ['name' => 'М.Карими', 'login' => 'fanfirn'], 'ruler' => ['name' => 'А.Хаменеи', 'login' => 'rulerirn']],
        ['team' => 'Испания', 'slug' => 'esp', 'fanM' => ['name' => 'К.Гарсия', 'login' => 'fanmesp'], 'fanF' => ['name' => 'М.Фернандес', 'login' => 'fanfesp'], 'ruler' => ['name' => 'П.Санчес', 'login' => 'ruleresp']],
        ['team' => 'Кабо-Верде', 'slug' => 'cpv', 'fanM' => ['name' => 'М.Делгадо', 'login' => 'fanmcpv'], 'fanF' => ['name' => 'К.Монтейру', 'login' => 'fanfcpv'], 'ruler' => ['name' => 'Ж.Невиш', 'login' => 'rulercpv']],
        ['team' => 'Канада', 'slug' => 'can', 'fanM' => ['name' => 'Л.Трамбле', 'login' => 'fanmcan'], 'fanF' => ['name' => 'С.Мартин', 'login' => 'fanfcan'], 'ruler' => ['name' => 'Ж.Трюдо', 'login' => 'rulercan']],
        ['team' => 'Катар', 'slug' => 'qat', 'fanM' => ['name' => 'М.Аль-Тани', 'login' => 'fanmqat'], 'fanF' => ['name' => 'Ф.Аль-Куваири', 'login' => 'fanfqat'], 'ruler' => ['name' => 'Т.Аль-Тани', 'login' => 'rulerqat']],
        ['team' => 'Колумбия', 'slug' => 'col', 'fanM' => ['name' => 'К.Родригес', 'login' => 'fanmcol'], 'fanF' => ['name' => 'И.Мартинес', 'login' => 'fanfcol'], 'ruler' => ['name' => 'Г.Петро', 'login' => 'rulercol']],
        ['team' => 'Кот-д`Ивуар', 'slug' => 'civ', 'fanM' => ['name' => 'К.Яо', 'login' => 'fanmciv'], 'fanF' => ['name' => 'А.Диалло', 'login' => 'fanfciv'], 'ruler' => ['name' => 'А.Уаттара', 'login' => 'rulerciv']],
        ['team' => 'Кюрасао', 'slug' => 'cuw', 'fanM' => ['name' => 'Ж.Мари', 'login' => 'fanmcuw'], 'fanF' => ['name' => 'К.де Корт', 'login' => 'fanfcuw'], 'ruler' => ['name' => 'Г.Писас', 'login' => 'rulercuw']],
        ['team' => 'Марокко', 'slug' => 'mar', 'fanM' => ['name' => 'Ю.Бенали', 'login' => 'fanmmar'], 'fanF' => ['name' => 'Х.Эль-Амрани', 'login' => 'fanfmar'], 'ruler' => ['name' => 'Мохаммед VI', 'login' => 'rulermar']],
        ['team' => 'Мексика', 'slug' => 'mex', 'fanM' => ['name' => 'Х.Эрнандес', 'login' => 'fanmmex'], 'fanF' => ['name' => 'Г.Рамирес', 'login' => 'fanfmex'], 'ruler' => ['name' => 'К.Шейнбаум', 'login' => 'rulermex']],
        ['team' => 'Нидерланды', 'slug' => 'ned', 'fanM' => ['name' => 'Д.де Врис', 'login' => 'fanmned'], 'fanF' => ['name' => 'С.ван Берг', 'login' => 'fanfned'], 'ruler' => ['name' => 'Д.Шуф', 'login' => 'rulerned']],
        ['team' => 'Новая Зеландия', 'slug' => 'nzl', 'fanM' => ['name' => 'Дж.Уилсон', 'login' => 'fanmnzl'], 'fanF' => ['name' => 'О.Браун', 'login' => 'fanfnzl'], 'ruler' => ['name' => 'К.Лаксон', 'login' => 'rulernzl']],
        ['team' => 'Норвегия', 'slug' => 'nor', 'fanM' => ['name' => 'Э.Хансен', 'login' => 'fanmnor'], 'fanF' => ['name' => 'И.Олсен', 'login' => 'fanfnor'], 'ruler' => ['name' => 'Й.Сторе', 'login' => 'rulernor']],
        ['team' => 'Панама', 'slug' => 'pan', 'fanM' => ['name' => 'К.Родригес', 'login' => 'fanmpan'], 'fanF' => ['name' => 'М.Гомес', 'login' => 'fanfpan'], 'ruler' => ['name' => 'Х.Мулино', 'login' => 'rulerpan']],
        ['team' => 'Парагвай', 'slug' => 'par', 'fanM' => ['name' => 'Д.Бенитес', 'login' => 'fanmpar'], 'fanF' => ['name' => 'К.Вера', 'login' => 'fanfpar'], 'ruler' => ['name' => 'С.Пенья', 'login' => 'rulerpar']],
        ['team' => 'Португалия', 'slug' => 'por', 'fanM' => ['name' => 'Ж.Сантос', 'login' => 'fanmpor'], 'fanF' => ['name' => 'А.Феррейра', 'login' => 'fanfpor'], 'ruler' => ['name' => 'М.Ребелу', 'login' => 'rulerpor']],
        ['team' => 'С. Аравия', 'slug' => 'ksa', 'fanM' => ['name' => 'А.Аль-Кахтани', 'login' => 'fanmksa'], 'fanF' => ['name' => 'Н.Аль-Шехри', 'login' => 'fanfksa'], 'ruler' => ['name' => 'Салман', 'login' => 'rulerksa']],
        ['team' => 'США', 'slug' => 'usa', 'fanM' => ['name' => 'М.Джонсон', 'login' => 'fanmusa'], 'fanF' => ['name' => 'Э.Дэвис', 'login' => 'fanfusa'], 'ruler' => ['name' => 'Д.Трамп', 'login' => 'rulerusa']],
        ['team' => 'Сенегал', 'slug' => 'sen', 'fanM' => ['name' => 'И.Диоп', 'login' => 'fanmsen'], 'fanF' => ['name' => 'А.Ндиай', 'login' => 'fanfsen'], 'ruler' => ['name' => 'Б.Фай', 'login' => 'rulersen']],
        ['team' => 'Тунис', 'slug' => 'tun', 'fanM' => ['name' => 'М.Трабелси', 'login' => 'fanmtun'], 'fanF' => ['name' => 'А.Бен Салах', 'login' => 'fanftun'], 'ruler' => ['name' => 'К.Саид', 'login' => 'rulertun']],
        ['team' => 'Турция', 'slug' => 'tur', 'fanM' => ['name' => 'М.Йылмаз', 'login' => 'fanmtur'], 'fanF' => ['name' => 'А.Демир', 'login' => 'fanftur'], 'ruler' => ['name' => 'Р.Эрдоган', 'login' => 'rulertur']],
        ['team' => 'Узбекистан', 'slug' => 'uzb', 'fanM' => ['name' => 'Р.Каримов', 'login' => 'fanmuzb'], 'fanF' => ['name' => 'Д.Рахимова', 'login' => 'fanfuzb'], 'ruler' => ['name' => 'Ш.Мирзиёев', 'login' => 'ruleruzb']],
        ['team' => 'Уругвай', 'slug' => 'uru', 'fanM' => ['name' => 'Д.Фернандес', 'login' => 'fanmuru'], 'fanF' => ['name' => 'В.Родригес', 'login' => 'fanfuru'], 'ruler' => ['name' => 'Л.Лакалье', 'login' => 'ruleruru']],
        ['team' => 'Франция', 'slug' => 'fra', 'fanM' => ['name' => 'П.Мартен', 'login' => 'fanmfra'], 'fanF' => ['name' => 'К.Дюбуа', 'login' => 'fanffra'], 'ruler' => ['name' => 'Э.Макрон', 'login' => 'rulerfra']],
        ['team' => 'Хорватия', 'slug' => 'cro', 'fanM' => ['name' => 'И.Хорват', 'login' => 'fanmcro'], 'fanF' => ['name' => 'П.Ковач', 'login' => 'fanfcro'], 'ruler' => ['name' => 'А.Пленкович', 'login' => 'rulercro']],
        ['team' => 'Чехия', 'slug' => 'cze', 'fanM' => ['name' => 'Я.Новак', 'login' => 'fanmcze'], 'fanF' => ['name' => 'Т.Свободова', 'login' => 'fanfcze'], 'ruler' => ['name' => 'П.Фиала', 'login' => 'rulercze']],
        ['team' => 'Швейцария', 'slug' => 'sui', 'fanM' => ['name' => 'Л.Майер', 'login' => 'fanmsui'], 'fanF' => ['name' => 'Л.Бруннер', 'login' => 'fanfsui'], 'ruler' => ['name' => 'В.Амхерд', 'login' => 'rulersui']],
        ['team' => 'Швеция', 'slug' => 'swe', 'fanM' => ['name' => 'Э.Андерссон', 'login' => 'fanmswe'], 'fanF' => ['name' => 'А.Линдстрем', 'login' => 'fanfswe'], 'ruler' => ['name' => 'У.Кристерссон', 'login' => 'rulerswe']],
        ['team' => 'Шотландия', 'slug' => 'sco', 'fanM' => ['name' => 'Дж.Макдональд', 'login' => 'fanmsco'], 'fanF' => ['name' => 'Ф.Кэмпбелл', 'login' => 'fanfsco'], 'ruler' => ['name' => 'Дж.Свинни', 'login' => 'rulersco']],
        ['team' => 'Эквадор', 'slug' => 'ecu', 'fanM' => ['name' => 'Л.Мендоса', 'login' => 'fanmecu'], 'fanF' => ['name' => 'К.Вега', 'login' => 'fanfecu'], 'ruler' => ['name' => 'Д.Нобоа', 'login' => 'rulerecu']],
        ['team' => 'Ю. Корея', 'slug' => 'kor', 'fanM' => ['name' => 'Ким Мин Джун', 'login' => 'fanmkor'], 'fanF' => ['name' => 'Пак Чи Ён', 'login' => 'fanfkor'], 'ruler' => ['name' => 'Ли Чжэ Мён', 'login' => 'rulerkor']],
        ['team' => 'ЮАР', 'slug' => 'rsa', 'fanM' => ['name' => 'С.Нкози', 'login' => 'fanmrsa'], 'fanF' => ['name' => 'Н.Дламини', 'login' => 'fanfrsa'], 'ruler' => ['name' => 'С.Рамафоса', 'login' => 'rulerrsa']],
        ['team' => 'Япония', 'slug' => 'jpn', 'fanM' => ['name' => 'Х.Сато', 'login' => 'fanmjpn'], 'fanF' => ['name' => 'Ю.Танака', 'login' => 'fanfjpn'], 'ruler' => ['name' => 'С.Исиба', 'login' => 'rulerjpn']],
    ];
}

/**
 * @return list<array{team:string,role:string,name:string,login:string,mail:string}>
 */
function wc_fans_roster_people(): array
{
    $people = [];

    foreach (wc_fans_roster() as $row) {
        $team = (string)$row['team'];

        foreach ([
            ['key' => 'fanM', 'role' => 'Бол'],
            ['key' => 'fanF', 'role' => 'Бол♀'],
            ['key' => 'ruler', 'role' => 'Пр'],
        ] as $spec) {
            $person = $row[$spec['key']] ?? null;
            if (!is_array($person)) {
                continue;
            }

            $login = strtolower((string)($person['login'] ?? ''));
            if ($login === '') {
                continue;
            }

            $people[] = [
                'team' => $team,
                'role' => $spec['role'],
                'name' => (string)($person['name'] ?? ''),
                'login' => $login,
                'mail' => $login . '@prognos9ys.ru',
            ];
        }
    }

    return $people;
}

if (PHP_SAPI === 'cli' && in_array('--json', $argv ?? [], true)) {
    echo json_encode(wc_fans_roster_people(), JSON_UNESCAPED_UNICODE);

    exit(0);
}
