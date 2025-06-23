<?php

use OliverThiele\OtFormprefill\Middleware\FrontendUserDataMiddleware;

return [
    'frontend' => [
        'oliver-thiele/ot-formprefill/frontend-user-data' => [
            'target' => FrontendUserDataMiddleware::class,
            'before' => [
                'typo3/cms-frontend/page-resolver',
            ],
            'after' => [
                'typo3/cms-frontend/authentication',
            ],
        ],
    ],
];
