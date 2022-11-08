<? if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();

use \Bitrix\Main\Localization\Loc,
    \Bitrix\Main\Loader,
    \Bitrix\Main\Application,
    \Bitrix\Main\Mail\Event,
    \Bitrix\Main\Config\Option,
    \Bitrix\Highloadblock as HL,
    \Bitrix\Main\Entity,
    \Bitrix\Main\Type\DateTime;

class DianaPromo extends CBitrixComponent
{
    protected $requestsIblockId = 0;
    protected $feedbackIblockId = 0;
    protected $faqIblockId = 0;
    protected $prizesIblockId = 0;
    protected $winnersIblockId = 0;
    protected $logHlblockId = 0;
    protected $token = '';
    protected $apiPoint = '';
    protected $drawId = 0;

    public function __construct($component = null)
    {
        parent::__construct($component);

        if (!Loader::includeModule('iblock')) {
            ShowError('Модуль Информационных блоков не установлен');
            return;
        };

        if (!Loader::includeModule('highloadblock')) {
            ShowError('Модуль Highload блоков не установлен');
            return;
        };

        $this->requestsIblockId = \CIBlock::GetList([], ['TYPE' => 'promo', 'CODE' => 'requests', 'SITE_ID' => SITE_ID], false)->Fetch()['ID'];
        $this->feedbackIblockId = \CIBlock::GetList([], ['TYPE' => 'promo', 'CODE' => 'feedback', 'SITE_ID' => SITE_ID], false)->Fetch()['ID'];
        $this->faqIblockId = \CIBlock::GetList([], ['TYPE' => 'promo', 'CODE' => 'faq', 'SITE_ID' => SITE_ID], false)->Fetch()['ID'];
        $this->prizesIblockId = \CIBlock::GetList([], ['TYPE' => 'promo', 'CODE' => 'prizes', 'SITE_ID' => SITE_ID], false)->Fetch()['ID'];
        $this->winnersIblockId = \CIBlock::GetList([], ['TYPE' => 'promo', 'CODE' => 'winners', 'SITE_ID' => SITE_ID], false)->Fetch()['ID'];
        $this->logHlblockId = HL\HighloadBlockTable::getList(['filter' => ['TABLE_NAME' => 'log_draw_api'], 'select'=>['ID']])->fetch()['ID'];

        $this->token = Option::get("grain.customsettings", "promo_api_token");
        $this->apiPoint = Option::get("grain.customsettings", "promo_api_endpoint");
        $this->drawId = Option::get("grain.customsettings", "promo_id_draw");
    }

    public function onPrepareComponentParams($params)
    {
        return $params;
    }

    public function executeComponent()
    {
        $this->arResult['ERRORS'] = [];

        $request = Application::getInstance()->getContext()->getRequest();

        if ($request->isAjaxRequest()) {
            $this->arResult['ajax_form'] = 'Y';
            $this->arResult['type_form'] = $request->getPost("type_form");

            /*
             * Обработка формы добавления заявки на участие
             */
            if($this->arResult['type_form'] == 'registration') {
                $this->arResult['VALUES'] = [
                    'fio' => htmlspecialchars(trim($request->getPost("fio"))),
                    'order' => htmlspecialchars(trim($request->getPost("order"))),
                    'phone' => htmlspecialchars(trim($request->getPost("phone"))),
                    'email' => htmlspecialchars(trim($request->getPost("email"))),
                    'politics' => htmlspecialchars(trim($request->getPost("politics"))),
                ];

                if(empty($this->arResult['VALUES']['fio'])) {
                    $this->arResult['ERRORS']['registration']['fio'] = 'Заполните обязательное поле';
                } elseif(!preg_match('/^[а-яА-ЯёЁ\s\-]+$/iu', $this->arResult['VALUES']['fio'])) {
                    $this->arResult['ERRORS']['registration']['fio'] = 'ФИО должно содержать только русские буквы';
                }

                if(empty($this->arResult['VALUES']['order'])) {
                    $this->arResult['ERRORS']['registration']['order'] = 'Заполните обязательное поле';
                } elseif(!preg_match('/^[0-9]+$/iu', $this->arResult['VALUES']['order'])) {
                    $this->arResult['ERRORS']['registration']['order'] = 'Номер заказа должен содержать только цифры';
                }

                if(empty($this->arResult['VALUES']['phone'])) {
                    $this->arResult['ERRORS']['registration']['phone'] = 'Заполните обязательное поле';
                } else {
                    $phone = preg_replace('/[^0-9]/', '', $this->arResult['VALUES']['phone']);
                    if(strlen($phone) != 11) {
                        $this->arResult['ERRORS']['registration']['phone'] = 'Некорректный номер телефона';
                    }
                }

                if(empty($this->arResult['VALUES']['email'])) {
                    $this->arResult['ERRORS']['registration']['email'] = 'Заполните обязательное поле';
                } elseif(!check_email($this->arResult['VALUES']['email'])) {
                    $this->arResult['ERRORS']['registration']['email'] = 'Некорректный e-mail';
                }

                if(empty($this->arResult['VALUES']['politics'])) {
                    $this->arResult['ERRORS']['registration']['politics'] = 'Подтвердите Ваше согласие';
                }

                if(empty($this->arResult['ERRORS']['registration'])) {
                    $client = new \Sebekon\DrawApi\RestClient($this->apiPoint, $this->token);

                    try {
                        //Получение розыгрыша
                        $arDraw = $client->getDraw($this->drawId);
                        //Проверка квитанции на участие в розыгрыше, регистрация квитанции
                        $arInvoice = $client->getInvoice($this->arResult['VALUES']['order'], $this->drawId);
                        //Регистрация квитанции
                        if (!$client->registerInvoice($this->arResult['VALUES']['order'], $this->drawId)) {
                            throw new \Exception('Не удалось зарегистрировать квитанцию');
                        }
                    } catch (\Exception $e) {
                        $this->arResult['ERRORS']['registration']['submit'] = 'Ошибка регистрации на розыгрыш: ' . $e->getMessage();

                        if(empty($arDraw)) {
                            $this->log(str_replace('{drawId}', $this->drawId, '/draw/{drawId}'), $e->getMessage(), $e->getCode());
                        } elseif(empty($arInvoice)) {
                            $this->log(str_replace(['{drawId}', '{invoiceId}'], [$this->drawId, $this->arResult['VALUES']['order']], '/invoice/{invoiceId}/checkDraw/{drawId}'), $e->getMessage(), $e->getCode());
                        } else {
                            $this->log(str_replace(['{drawId}', '{invoiceId}'], [$this->drawId, $this->arResult['VALUES']['order']], '/draw/{drawId}/register/{invoiceId}'), $e->getMessage(), $e->getCode());
                        }
                    }
                }

                //Добавление заявки в ИБ, отправка письма клиенту
                if(empty($this->arResult['ERRORS']['registration'])) {
                    $idPropertyByCode = [];
                    $rsProperty = \CIBlockProperty::GetList([], ['IBLOCK_ID' => $this->requestsIblockId]);
                    while($property = $rsProperty->Fetch()) {
                        $idPropertyByCode[$property['CODE']] = $property['ID'];
                    }

                    $el = new \CIBlockElement;

                    if(!$el->Add([
                        'IBLOCK_ID' => $this->requestsIblockId,
                        'IBLOCK_SECTION_ID' => false,
                        'NAME' => $this->arResult['VALUES']['fio'],
                        'PROPERTY_VALUES' => [
                            $idPropertyByCode['ORDER'] => $this->arResult['VALUES']['order'],
                            $idPropertyByCode['PHONE'] => preg_replace('/[^0-9+]/', '', $this->arResult['VALUES']['phone']),
                            $idPropertyByCode['EMAIL'] => $this->arResult['VALUES']['email'],
                        ]
                    ])) {
                        $this->arResult['ERRORS']['registration']['submit'] = $el->LAST_ERROR;
                    } else {
                        //отправка письма клиенту
                        Event::send(array(
                            "EVENT_NAME" => "PROMO",
                            "LID" => "s1",
                            "C_FIELDS" => array(
                                "NAME" => $this->arResult['VALUES']['fio'],
                                "PHONE" => preg_replace('/[^0-9+]/', '', $this->arResult['VALUES']['phone']),
                                "EMAIL" => $this->arResult['VALUES']['email'],
                                "ORDER" => $this->arResult['VALUES']['order']
                            ),
                        ));

                        $this->arResult['VALUES'] = [];
                    }
                }
            }

            /*
             * Обработка формы обратной связи
             */
            if($this->arResult['type_form'] == 'feedback') {
                $this->arResult['VALUES'] = [
                    'fio' => htmlspecialchars(trim($request->getPost("fio"))),
                    'phone' => htmlspecialchars(trim($request->getPost("phone"))),
                    'question' => htmlspecialchars($request->getPost("question"))
                ];

                if(empty($this->arResult['VALUES']['fio'])) {
                    $this->arResult['ERRORS']['feedback']['fio'] = 'Заполните обязательное поле';
                } elseif (!preg_match('/^[а-яА-ЯёЁ\s\-]+$/iu', $this->arResult['VALUES']['fio'])) {
                    $this->arResult['ERRORS']['feedback']['fio'] = 'ФИО должно содержать только русские буквы';
                }

                if(empty($this->arResult['VALUES']['phone'])) {
                    $this->arResult['ERRORS']['feedback']['phone'] = 'Заполните обязательное поле';
                } else {
                    $phone = preg_replace('/[^0-9]/', '', $this->arResult['VALUES']['phone']);
                    if(strlen($phone) != 11) {
                        $this->arResult['ERRORS']['feedback']['phone'] = 'Некорректный номер телефона';
                    }
                }

                if(empty($this->arResult['ERRORS']['feedback'])) {
                    $idPropertyByCode = [];
                    $rsProperty = \CIBlockProperty::GetList([], ['IBLOCK_ID' => $this->feedbackIblockId]);
                    while($property = $rsProperty->Fetch()) {
                        $idPropertyByCode[$property['CODE']] = $property['ID'];
                    }

                    $el = new \CIBlockElement;

                    if(!$el->Add([
                        'IBLOCK_ID' => $this->feedbackIblockId,
                        'IBLOCK_SECTION_ID' => false,
                        'NAME' => $this->arResult['VALUES']['fio'],
                        'PREVIEW_TEXT' => $this->arResult['VALUES']['question'],
                        'PROPERTY_VALUES' => [
                            $idPropertyByCode['PHONE'] => preg_replace('/[^0-9+]/', '', $this->arResult['VALUES']['phone']),
                        ]
                    ])) {
                        $this->arResult['ERRORS']['feedback']['submit'] = $el->LAST_ERROR;
                    } else {
                        //отправка письма менеджеру
                        Event::send(array(
                            "EVENT_NAME" => "PROMO_FEEDBACK",
                            "LID" => "s1",
                            "C_FIELDS" => array(
                                "NAME" => $this->arResult['VALUES']['fio'],
                                "PHONE" => preg_replace('/[^0-9+]/', '', $this->arResult['VALUES']['phone']),
                                "PREVIEW_TEXT" => $this->arResult['VALUES']['question']
                            ),
                        ));

                        $this->arResult['VALUES'] = [];
                    }
                }
            }

            if($this->arResult['type_form'] == 'winner_search') {
                $rsWinners = \CIBlockElement::GetList(['SORT' => 'ASC', 'ID' => 'ASC'], [
                    'IBLOCK_ID' => $this->winnersIblockId,
                    'ACTIVE' => 'Y',
                    'PROPERTY_ORDER' => htmlspecialchars(trim($request->getPost("val"))) . '%'
                ], false, false, ['ID', 'NAME', 'PROPERTY_PHONE', 'PROPERTY_PRIZE', 'PROPERTY_ORDER', 'PROPERTY_REGION']);
                while($item = $rsWinners->Fetch()) {
                    $this->arResult['winners'][] = [
                        'fio' => $item['NAME'],
                        'prize' => $item['PROPERTY_PRIZE_VALUE'],
                        'phone' => $item['PROPERTY_PHONE_VALUE'],
                        'order' => $item['PROPERTY_ORDER_VALUE'],
                        'region' => $item['PROPERTY_REGION_VALUE']
                    ];
                }
            }

            $this->includeComponentTemplate();
        }

        elseif ($this->startResultCache()) {
            if($this->faqIblockId) {
                $rsFaq = \CIBlockElement::GetList(['SORT' => 'ASC', 'ID' => 'ASC'], [
                    'IBLOCK_ID' => $this->faqIblockId,
                    'ACTIVE' => 'Y'
                ], false, false, ['ID', 'NAME', 'PREVIEW_TEXT']);
                while($item = $rsFaq->Fetch()) {
                    $this->arResult['faq'][] = [
                        'name' => $item['NAME'],
                        'text' => $item['PREVIEW_TEXT']
                    ];
                }
            }

            if($this->prizesIblockId) {
                $rsPrizes = \CIBlockElement::GetList(['SORT' => 'ASC', 'ID' => 'ASC'], [
                    'IBLOCK_ID' => $this->prizesIblockId,
                    'ACTIVE' => 'Y'
                ], false, false, ['ID', 'NAME', 'PREVIEW_PICTURE']);
                while($item = $rsPrizes->Fetch()) {
                    $image = '';
                    if($item['PREVIEW_PICTURE']) {
                        $arFile = \CFile::GetFileArray($item['PREVIEW_PICTURE']);
                        if($arFile) {
                            $image = $arFile["SRC"];
                        }
                    }

                    $this->arResult['prizes'][] = [
                        'text' => $item['NAME'],
                        'img' => $image
                    ];
                }
            }

            if($this->winnersIblockId) {
                $rsWinners = \CIBlockElement::GetList(['SORT' => 'ASC', 'ID' => 'ASC'], [
                    'IBLOCK_ID' => $this->winnersIblockId,
                    'ACTIVE' => 'Y'
                ], false, false, ['ID', 'NAME', 'PROPERTY_PHONE', 'PROPERTY_PRIZE', 'PROPERTY_ORDER', 'PROPERTY_REGION']);
                while($item = $rsWinners->Fetch()) {
                    $this->arResult['winners'][] = [
                        'fio' => $item['NAME'],
                        'prize' => $item['PROPERTY_PRIZE_VALUE'],
                        'phone' => $item['PROPERTY_PHONE_VALUE'],
                        'order' => $item['PROPERTY_ORDER_VALUE'],
                        'region' => $item['PROPERTY_REGION_VALUE']
                    ];
                }
            }

            $this->includeComponentTemplate();
        }
    }

    protected function log($request, $message, $code) {
        $hlblock = HL\HighloadBlockTable::getById($this->logHlblockId)->fetch();

        if($hlblock) {
            $entity = HL\HighloadBlockTable::compileEntity($hlblock);
            $entity_data_class = $entity->getDataClass();
            $entity_data_class::add([
                'UF_REQUEST' => $request,
                'UF_DATETIME' => new DateTime(),
                'UF_CODE' => $code,
                'UF_MESSAGE' => $message,
            ]);
        }
    }
}
