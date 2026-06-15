<?php

namespace Prognos9ys\Main\Model;

use Bitrix\Iblock\Iblock;
use Bitrix\Iblock\IblockTable;
use Bitrix\Main\Loader;
use Bitrix\Main\ORM\Query\Query;

/**
 * @mixin \Bitrix\Main\ORM\Data\DataManager
 */
abstract class BaseIblockRepository implements Model
{
    public const IBLOCK_CODE = '';

    public const SELECT_FIELDS = ['*'];

    protected array $selectFields = self::SELECT_FIELDS;

    protected string $dataClass;

    public function __construct()
    {
        if (!Loader::includeModule('iblock')) {
            throw new \RuntimeException('Модуль iblock не установлен');
        }

        $this->dataClass = Iblock::wakeUp($this->getIblockId())->getEntityDataClass();
    }

    public function getIblockId(): int
    {
        $row = IblockTable::getRow([
            'filter' => ['=CODE' => static::IBLOCK_CODE],
            'select' => ['ID'],
        ]);

        if (!$row) {
            throw new \RuntimeException('Инфоблок ' . static::IBLOCK_CODE . ' не найден');
        }

        return (int)$row['ID'];
    }

    public function setSelect(array $fields, bool $merge = true): self
    {
        $this->selectFields = $merge
            ? array_merge($this->selectFields, $fields)
            : $fields;

        return $this;
    }

    public function queryObject(): Query
    {
        return $this->dataClass::query()->setSelect($this->selectFields);
    }

    public static function __callStatic(string $name, array $arguments)
    {
        return (new static())->dataClass::$name(...$arguments);
    }
}
