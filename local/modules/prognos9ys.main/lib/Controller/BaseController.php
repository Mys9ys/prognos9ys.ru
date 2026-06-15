<?php

namespace Prognos9ys\Main\Controller;

use Bitrix\Main\Engine\Action;
use Bitrix\Main\Engine\ActionFilter;
use Prognos9ys\Main\Controller\Filter\TokenAuthFilter;

class BaseController extends \Bitrix\Main\Engine\Controller
{
    public function configureActions(): array
    {
        return [];
    }

    protected function getDefaultConfigureForGet(bool $disabledAuth = false): array
    {
        return [
            'prefilters' => $this->getDefaultPreFilters($disabledAuth),
            'postfilters' => [],
        ];
    }

    protected function getDefaultConfigureForPost(bool $disabledAuth = false): array
    {
        $prefilters = [
            new ActionFilter\HttpMethod([ActionFilter\HttpMethod::METHOD_POST]),
            new ActionFilter\ContentType([ActionFilter\ContentType::JSON]),
        ];

        if (!$disabledAuth) {
            $prefilters[] = new ActionFilter\Authentication();
        }

        return [
            'prefilters' => $prefilters,
            '-prefilters' => [ActionFilter\Csrf::class],
            'postfilters' => [],
        ];
    }

    protected function getDefaultConfigureForGetToken(): array
    {
        return [
            'prefilters' => [
                new ActionFilter\HttpMethod([ActionFilter\HttpMethod::METHOD_GET]),
                new TokenAuthFilter(),
            ],
            'postfilters' => [],
        ];
    }

    protected function getDefaultConfigureForPostToken(): array
    {
        return [
            'prefilters' => [
                new ActionFilter\HttpMethod([ActionFilter\HttpMethod::METHOD_POST]),
                new ActionFilter\ContentType([ActionFilter\ContentType::JSON]),
                new TokenAuthFilter(),
            ],
            '-prefilters' => [ActionFilter\Csrf::class],
            'postfilters' => [],
        ];
    }

    protected function getDefaultConfigureForPostPublic(): array
    {
        return [
            'prefilters' => [
                new ActionFilter\HttpMethod([ActionFilter\HttpMethod::METHOD_POST]),
                new ActionFilter\ContentType([ActionFilter\ContentType::JSON]),
            ],
            '-prefilters' => [ActionFilter\Csrf::class],
            'postfilters' => [],
        ];
    }

    protected function getDefaultPreFilters(bool $disabledAuth = false): array
    {
        $prefilters = [
            new ActionFilter\HttpMethod([ActionFilter\HttpMethod::METHOD_GET]),
        ];

        if (!$disabledAuth) {
            $prefilters[] = new ActionFilter\Authentication();
        }

        return $prefilters;
    }

    protected function getActionResponse(Action $action)
    {
        try {
            return parent::getActionResponse($action);
        } catch (ApiException $exception) {
            \CHTTP::SetStatus($exception->getCode() ?: 422);

            return ['error' => $exception->getMessage()];
        }
    }
}
