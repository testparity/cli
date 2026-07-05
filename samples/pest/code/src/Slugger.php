<?php

declare(strict_types=1);

namespace App;

final class Slugger
{
    public function slug(string $title): string
    {
        return strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $title), '-'));
    }
}
