<?php

declare(strict_types=1);

namespace App\Controllers;

use Framework\View;

final class ErrorController
{
    public static function notFound(): void
    {
        http_response_code(404);

        View::render('error');
    }
}
