<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Support\AdminGuard;

class AdminController
{
    public function index(): void
    {
        $admin = AdminGuard::requireAdmin();

        // Keep the existing dashboard frontend.
        loadView('admin-header', [
            'adminEmail' => $admin['email']
        ]);
    }
}
