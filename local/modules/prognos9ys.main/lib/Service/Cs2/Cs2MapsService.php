<?php

namespace Prognos9ys\Main\Service\Cs2;

use Bitrix\Main\Loader;
use Prognos9ys\Main\Model\Repository\Cs2IblockRegistry;

class Cs2MapsService
{
    private Cs2IblockRegistry $registry;

    public function __construct(?Cs2IblockRegistry $registry = null)
    {
        $this->registry = $registry ?? new Cs2IblockRegistry();
    }

    /** @return list<array{id:int,code:string,name:string,image:string,description:string}> */
    public function getPoolMaps(): array
    {
        if (!Loader::includeModule('iblock')) {
            return [];
        }

        $iblockId = $this->registry->getIblockId(Cs2IblockRegistry::IBLOCK_MAPS);
        if ($iblockId <= 0) {
            return $this->fallbackMaps();
        }

        $maps = [];
        $response = \CIBlockElement::GetList(
            ['SORT' => 'ASC', 'NAME' => 'ASC'],
            [
                'IBLOCK_ID' => $iblockId,
                'ACTIVE' => 'Y',
            ],
            false,
            false,
            ['ID', 'NAME', 'CODE', 'PREVIEW_PICTURE', 'PREVIEW_TEXT']
        );

        while ($row = $response->GetNext()) {
            $code = strtolower((string)($row['CODE'] ?? ''));
            if ($code === '') {
                continue;
            }

            $image = '';
            if (!empty($row['PREVIEW_PICTURE'])) {
                $image = (string)\CFile::GetPath($row['PREVIEW_PICTURE']);
            }

            $maps[] = [
                'id' => (int)$row['ID'],
                'code' => $code,
                'name' => (string)$row['NAME'],
                'image' => $image,
                'description' => (string)($row['PREVIEW_TEXT'] ?? ''),
            ];
        }

        return $maps ?: $this->fallbackMaps();
    }

    /** @return array<string, array{id:int,code:string,name:string}> */
    public function getMapsByCode(): array
    {
        $indexed = [];
        foreach ($this->getPoolMaps() as $map) {
            $indexed[$map['code']] = $map;
        }

        return $indexed;
    }

    /** @return list<array{id:int,code:string,name:string,image:string,description:string}> */
    private function fallbackMaps(): array
    {
        $defs = [
            ['ancient', 'Ancient'],
            ['anubis', 'Anubis'],
            ['dust2', 'Dust II'],
            ['inferno', 'Inferno'],
            ['mirage', 'Mirage'],
            ['nuke', 'Nuke'],
            ['overpass', 'Overpass'],
        ];

        $maps = [];
        foreach ($defs as $index => [$code, $name]) {
            $maps[] = [
                'id' => 0,
                'code' => $code,
                'name' => $name,
                'image' => '',
                'description' => '',
            ];
        }

        return $maps;
    }
}
