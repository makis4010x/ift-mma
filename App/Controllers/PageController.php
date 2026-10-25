<?php

declare(strict_types=1);

namespace App\Controllers;

final class PageController
{
    public function academy(): void
    {
        loadView('academy');
    }

    public function lessons(): void
    {
        loadView('lessons');
    }

    public function schedule(): void
    {
        loadView('schedule');
    }

    public function personalTraining(): void
    {
        loadView('personal-training');
    }

    public function contact(): void
    {
        loadView('contact');
    }

    public function bookPersonalTraining(): void
    {
        loadView('book-personal-training');
    }
}
