<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelLang\Facades;

use AbeTwoThree\LaravelLang\LaravelLang as LaravelLangInstance;
use Illuminate\Support\Facades\Facade;

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
