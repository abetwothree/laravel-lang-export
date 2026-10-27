<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelLang\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \AbeTwoThree\LaravelLang\LaravelLang
 */
class LaravelLang extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \AbeTwoThree\LaravelLang\LaravelLang::class;
    }
}
