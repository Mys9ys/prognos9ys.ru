<?php

use Bitrix\Main\Loader;

class GenValuesBotFootball
{

    protected $arFields;

    public function __construct()
    {
        if (!Loader::includeModule('iblock')) {
            ShowError('Модуль Информационных блоков не установлен');
            return;
        }

        $this->setGoals();
        $this->setCorner();
        $this->setDomination();
        $this->setYellowCards();
        $this->setRedCards();
        $this->setPenalty();
        $this->setOffsides();
    }

    protected function setGoals(){

        $arScore = [
            114=>'1-1',
            219=>'1-0',
            304=>'2-1',
            381=>'0-0',
            455=>'0-1',
            528=>'2-0',
            591=>'1-2',
            638=>'2-2',
            682=>'0-2',
            725=>'3-0',
            767=>'3-1',
            794=>'1-3',
            819=>'3-2',
            842=>'0-3',
            862=>'4-0',
            881=>'2-3',
            899=>'4-1',
            909=>'1-4',
            919=>'4-2',
            929=>'3-3',
            938=>'0-4',
            946=>'5-0',
            953=>'5-1',
            960=>'2-4',
            964=>'4-3',
            968=>'0-5',
            972=>'5-2',
            976=>'1-5',
            979=>'6-0',
            982=>'3-4',
            985=>'6-1',
            987=>'2-5',
            989=>'7-0',
            991=>'0-6',
            992=>'1-6',
            993=>'4-4',
            994=>'6-2',
            995=>'5-3',
            996=>'7-1',
            997=>'3-5',
            998=>'8-0',
            999=>'0-7',
            1000=>'2-6',
        ];
        $max = 1000;

        $rand = random_int(1, $max);
        $findScore = '';

        for($i=$rand; $i<$max; $i++){
            if($arScore[$i]) {
                $findScore = $arScore[$i];
                break;
            }
        }

        $arRandScore = explode('-', $findScore);

        $this->arFields[15] = $arRandScore[0]; // home
        $this->arFields[16] = $arRandScore[1]; // guest

        $this->setMatchResult($arRandScore[0], $arRandScore[1]);

    }

    protected function setCorner(){
        $arCorner = [
            12=>'9',
            23=>'8',
            34=>'10',
            44=>'7',
            54=>'11',
            62=>'6',
            70=>'12',
            76=>'13',
            81=>'14',
            86=>'5',
            89=>'15',
            91=>'4',
            93=>'3',
            95=>'16',
            96=>'17',
            97=>'18',
            98=>'19',
            99=>'2',
            100=>'1',
        ];

        $max = 100;

        $rand = random_int(1, $max);
        $findCorner = '';

        for($i=$rand; $i<$max; $i++){
            if($arCorner[$i]) {
                $findCorner = $arCorner[$i];
                break;
            }
        }
        $this->arFields[20] = $findCorner;

    }

    protected function setDomination(){
        $arDomination = [
            10=>'25',
            22=>'26',
            36=>'27',
            52=>'28',
            70=>'29',
            90=>'30',
            112=>'31',
            136=>'32',
            162=>'33',
            190=>'34',
            220=>'35',
            252=>'36',
            286=>'37',
            322=>'38',
            360=>'39',
            400=>'40',
            442=>'41',
            486=>'42',
            532=>'43',
            580=>'44',
            630=>'45',
            682=>'46',
            736=>'47',
            792=>'48',
            850=>'49',
            910=>'50',
            968=>'51',
            1024=>'52',
            1078=>'53',
            1130=>'54',
            1180=>'55',
            1228=>'56',
            1274=>'57',
            1318=>'58',
            1360=>'59',
            1400=>'60',
            1438=>'61',
            1474=>'62',
            1508=>'63',
            1540=>'64',
            1570=>'65',
            1598=>'66',
            1624=>'67',
            1648=>'68',
            1670=>'69',
            1690=>'70',
            1708=>'71',
            1724=>'72',
            1738=>'73',
            1750=>'74',
            1760=>'75',
        ];
        $max = 1760;

        $rand = random_int(1, $max);
        $findDomination = '';

        for($i=$rand; $i<$max; $i++){
            if($arDomination[$i]) {
                $findDomination = $arDomination[$i];
                break;
            }
        }
        $this->arFields[32] = $findDomination;
    }

    protected function setYellowCards(){
        $arCards = [
            32=>'00',
            77=>'1',
            127=>'2',
            177=>'3',
            227=>'4',
            272=>'5',
            317=>'6',
            357=>'7',
            387=>'8',
            407=>'9',
            417=>'10',
            422=>'11',
            425=>'12',
        ];

        $max = 425;

        $rand = random_int(1, $max);
        $findCard = '';

        for($i=$rand; $i<$max; $i++){
            if($arCards[$i]) {
                $findCard = $arCards[$i];
                break;
            }
        }
        $this->arFields[21] = (int)$findCard;
    }

    protected function setRedCards(){
        $arCards = [
            245=>'00',
            285=>'1',
            295=>'2',
            300=>'3',
        ];

        $max = 300;

        $rand = random_int(1, $max);
        $findCard = '';

        for($i=$rand; $i<$max; $i++){

            if($arCards[$i]) {
                $findCard = $arCards[$i];
                break;
            }
        }
        $this->arFields[22] = (int)$findCard;
    }

    protected function setPenalty(){
        $arPenalty = [
            180=>'00',
            355=>'1',
            385=>'2',
            395=>'3',
            400=>'4',
        ];

        $max = 400;

        $rand = random_int(1, $max);
        $findValue = '';

        for($i=$rand; $i<$max; $i++){

            if($arPenalty[$i]) {
                $findValue = $arPenalty[$i];
                break;
            }
        }
        $this->arFields[23] = (int)$findValue;
    }

    protected function setMatchResult($home, $guest){
        $this->arFields[19] = $home-$guest;
        $this->arFields[28] = $home+$guest;

        if($this->arFields[19] > 0) $this->arFields[18] = 'п1';
        if($this->arFields[19] < 0) $this->arFields[18] = 'п2';
        if($this->arFields[19] == 0) $this->arFields[18] = 'н';
    }

    protected function setOffsides(){
        $this->arFields[29] = '';
    }

    /**
     * @return mixed
     */
    public function getArFields()
    {
        return $this->arFields;
    }
}