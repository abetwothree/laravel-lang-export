<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelLang\Facades;

use Illuminate\Support\Facades\Facade;
use AbeTwoThree\LaravelLang\LaravelLang as LaravelLangInstance;

/**
 * @see LaravelLangInstance
 */
class LaravelLang extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return LaravelLangInstance::class;
    }
}
