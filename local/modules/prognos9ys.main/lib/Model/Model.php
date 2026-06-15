<?php

namespace Prognos9ys\Main\Model;

use Bitrix\Main\ORM\Query\Query;

interface Model
{
    public function setSelect(array $fields, bool $merge = true): self;

    public function queryObject(): Query;

    public function dataObjectBuilder(): Query;
}
