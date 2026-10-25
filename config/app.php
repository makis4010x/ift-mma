<?php

declare(strict_types=1);

return [
    'name' => 'IFT Academy',

    'environment' => getenv('APP_ENV') ?: 'local',

    'debug' => filter_var(
        getenv('APP_DEBUG') ?: 'false',
        FILTER_VALIDATE_BOOLEAN
    )
];
