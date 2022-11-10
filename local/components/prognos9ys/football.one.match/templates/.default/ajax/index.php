<?php use Bitrix\Main\Loader;

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

file_put_contents('debug_request.json',json_encode($_REQUEST));
//
//$_REQUEST = file_get_contents('debug_request.json');

//$_REQUEST = json_decode($_REQUEST, true);
//
//var_dump($_REQUEST);

if ($_REQUEST['type'] === 'match') {

    $res = new AddPrognosisInfo($_REQUEST);

    if($res->getResult()){
        $request = ["status" => "ok", "mes" => "Данные переданы"];
    } else {
        $request = ["status" => "err", "mes" => "Что то пошло не так"];
    }

    echo json_encode($request);
}


class AddPrognosisInfo
{

    protected $prognosisIb;
    protected $data = [];
    protected $result;

    protected $prop;
    protected $now;

    public function __construct($arr)
    {

        if (!Loader::includeModule('iblock')) {
            ShowError('Модуль Информационных блоков не установлен');
            return;
        }

        $this->prognosisIb = \CIBlock::GetList([], ['CODE' => 'prognosis'], false)->Fetch()['ID'] ?: 6;

        $this->data = $arr;

        $this->now = date(\CDatabase::DateFormatToPHP("DD.MM.YYYY HH:MI:SS"), time());
        $this->prop = [
            15 => $this->data["m_goal_home"],
            16 => $this->data["m_goal_guest"],
            17 => $this->data["m_id"], // матч id
            18 => $this->data["m_result"],
            19 => $this->data["m_diff"],
            20 => $this->data["m_corner"],
            21 => $this->data["m_yellow"],
            22 => $this->data["m_red"],
            23 => $this->data["m_penalty"],
            28 => $this->data["m_sum"],
            29 => $this->data["m_offside"],
            30 => $this->data["m_number"],
            31 => $this->data["m_user"],
            32 => $this->data["m_domination"],
        ];

        $check = $this->checkOldPrognosis();
        if($check) {
            $this->updatePrognosis($check);
        } else {
            $this->setPrognosis();
        }

    }

    protected function checkOldPrognosis(){

        $this->arFilter["IBLOCK_ID"] = $this->prognosisIb;
        $this->arFilter["PROPERTY_USER_ID"] = $this->data["m_user"];
        $this->arFilter["PROPERTY_ID"] = $this->data["m_id"];

        $res = CIBlockElement::GetList(
            [],
            $this->arFilter,
            false,
            [],
            [   "ID",
            ]
        );

        $response = $res->GetNext();

        return $response["ID"];

    }

    protected function updatePrognosis($id){

        $ib = new CIBlockElement;
        $data = [
            "IBLOCK_ID" => $this->prognosisIb,
            'DATE_ACTIVE_FROM' => $this->now,
            "PROPERTY_VALUES"=> $this->prop,
        ];

        $this->result = $ib->Update($id, $data);
    }

    protected function setPrognosis()
    {


               $ib = new CIBlockElement;
        $data = [
            "NAME" => "Участник: " .$this->prop[31] . " Прогноз на матч: " . $this->prop[17],
            "IBLOCK_ID" => $this->prognosisIb,
            'DATE_ACTIVE_FROM' => $this->now,
            "PROPERTY_VALUES"=>$this->prop
        ];

        $this->result = $ib->Add($data);

    }

    /**
     * @return mixed
     */
    public function getResult()
    {
        return $this->result;
    }
}