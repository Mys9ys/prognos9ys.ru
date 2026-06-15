<?php

namespace Prognos9ys\Main\Controller;

use Prognos9ys\Main\Service\Catalog\CatalogEventsService;

class CatalogController extends BaseController
{
    public function configureActions(): array
    {
        return [
            'getEvents' => $this->getDefaultConfigureForPostPublic(),
        ];
    }

    /**
     * Каталог / список соревнований — legacy /mob_app/ajax/events/
     */
    public function getEventsAction(string $type = 'catalog'): array
    {
        return (new CatalogEventsService())->getList($type);
    }
}
