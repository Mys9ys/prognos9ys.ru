<?php
declare(strict_types=1);

/**
 * Алиасы команд Liquipedia → cs2teams + метаданные для сида.
 *
 * @return array<string, array{code:string,name:string,tag:string,slug:string,region:string,sort:int}>
 */
function cs2_iem_team_catalog(): array
{
    return [
        '9z' => ['code' => '9z', 'name' => '9z Team', 'tag' => '9z', 'slug' => '9z', 'region' => 'SA', 'sort' => 160],
        'astralis' => ['code' => 'astralis', 'name' => 'Astralis', 'tag' => 'AST', 'slug' => 'astralis', 'region' => 'EU', 'sort' => 180],
        'aurora' => ['code' => 'aurora', 'name' => 'Aurora', 'tag' => 'AUR', 'slug' => 'aurora', 'region' => 'EU', 'sort' => 120],
        'b8' => ['code' => 'b8', 'name' => 'B8', 'tag' => 'B8', 'slug' => 'b8', 'region' => 'EU', 'sort' => 190],
        'betboom' => ['code' => 'betboom', 'name' => 'BetBoom Team', 'tag' => 'BB', 'slug' => 'betboom', 'region' => 'CIS', 'sort' => 150],
        'big' => ['code' => 'big', 'name' => 'BIG', 'tag' => 'BIG', 'slug' => 'big', 'region' => 'EU', 'sort' => 200],
        'falcons' => ['code' => 'falcons', 'name' => 'Team Falcons', 'tag' => 'FLC', 'slug' => 'falcons', 'region' => 'EU', 'sort' => 140],
        'flyquest' => ['code' => 'flyquest', 'name' => 'FlyQuest', 'tag' => 'FLY', 'slug' => 'flyquest', 'region' => 'NA', 'sort' => 210],
        'furia' => ['code' => 'furia', 'name' => 'FURIA', 'tag' => 'FURIA', 'slug' => 'furia', 'region' => 'BR', 'sort' => 110],
        'fut' => ['code' => 'fut', 'name' => 'FUT Esports', 'tag' => 'FUT', 'slug' => 'fut', 'region' => 'EU', 'sort' => 220],
        'g2' => ['code' => 'g2', 'name' => 'G2 Esports', 'tag' => 'G2', 'slug' => 'g2', 'region' => 'EU', 'sort' => 170],
        'gaimin' => ['code' => 'gaimin', 'name' => 'Gaimin Gladiators', 'tag' => 'GG', 'slug' => 'gaimin-gladiators', 'region' => 'EU', 'sort' => 310],
        'gamerlegion' => ['code' => 'gamerlegion', 'name' => 'GamerLegion', 'tag' => 'GL', 'slug' => 'gamerlegion', 'region' => 'EU', 'sort' => 230],
        'heroic' => ['code' => 'heroic', 'name' => 'HEROIC', 'tag' => 'HERO', 'slug' => 'heroic', 'region' => 'EU', 'sort' => 240],
        'legacy' => ['code' => 'legacy', 'name' => 'Legacy', 'tag' => 'LEG', 'slug' => 'legacy', 'region' => 'BR', 'sort' => 250],
        'liquid' => ['code' => 'liquid', 'name' => 'Team Liquid', 'tag' => 'TL', 'slug' => 'liquid', 'region' => 'NA', 'sort' => 260],
        'lynnvision' => ['code' => 'lynnvision', 'name' => 'Lynn Vision', 'tag' => 'LVG', 'slug' => 'lynn-vision', 'region' => 'CN', 'sort' => 270],
        'm80' => ['code' => 'm80', 'name' => 'M80', 'tag' => 'M80', 'slug' => 'm80', 'region' => 'NA', 'sort' => 280],
        'mibr' => ['code' => 'mibr', 'name' => 'MIBR', 'tag' => 'MIBR', 'slug' => 'mibr', 'region' => 'BR', 'sort' => 290],
        'mongolz' => ['code' => 'mongolz', 'name' => 'The MongolZ', 'tag' => 'MGLZ', 'slug' => 'the-mongolz', 'region' => 'AS', 'sort' => 300],
        'monte' => ['code' => 'monte', 'name' => 'Monte', 'tag' => 'MON', 'slug' => 'monte', 'region' => 'EU', 'sort' => 320],
        'mouz' => ['code' => 'mouz', 'name' => 'MOUZ', 'tag' => 'MOUZ', 'slug' => 'mouz', 'region' => 'EU', 'sort' => 330],
        'navi' => ['code' => 'navi', 'name' => 'Natus Vincere', 'tag' => 'NAVI', 'slug' => 'natus-vincere', 'region' => 'UA', 'sort' => 340],
        'nrg' => ['code' => 'nrg', 'name' => 'NRG', 'tag' => 'NRG', 'slug' => 'nrg', 'region' => 'NA', 'sort' => 350],
        'pain' => ['code' => 'pain', 'name' => 'paiN', 'tag' => 'paiN', 'slug' => 'pain', 'region' => 'BR', 'sort' => 360],
        'parivision' => ['code' => 'parivision', 'name' => 'PARIVISION', 'tag' => 'PV', 'slug' => 'parivision', 'region' => 'RU', 'sort' => 370],
        'sharks' => ['code' => 'sharks', 'name' => 'Sharks', 'tag' => 'SHK', 'slug' => 'sharks', 'region' => 'BR', 'sort' => 380],
        'sinners' => ['code' => 'sinners', 'name' => 'SINNERS', 'tag' => 'SIN', 'slug' => 'sinners', 'region' => 'EU', 'sort' => 390],
        'spirit' => ['code' => 'spirit', 'name' => 'Team Spirit', 'tag' => 'TS', 'slug' => 'spirit', 'region' => 'CIS', 'sort' => 100],
        'thunder' => ['code' => 'thunder', 'name' => 'THUNDER dOWNUNDER', 'tag' => 'THD', 'slug' => 'thunder-downunder', 'region' => 'OC', 'sort' => 400],
        'tyloo' => ['code' => 'tyloo', 'name' => 'TYLOO', 'tag' => 'TYLOO', 'slug' => 'tyloo', 'region' => 'CN', 'sort' => 410],
        'vitality' => ['code' => 'vitality', 'name' => 'Team Vitality', 'tag' => 'VIT', 'slug' => 'vitality', 'region' => 'FR', 'sort' => 130],
    ];
}
