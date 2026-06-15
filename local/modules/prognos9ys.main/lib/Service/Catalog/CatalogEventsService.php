<?php

namespace Prognos9ys\Main\Service\Catalog;

class CatalogEventsService
{
    public function getList(string $type = 'catalog'): array
    {
        $handler = new \CatalogEvents(['type' => $type]);

        return $handler->result();
    }
}
