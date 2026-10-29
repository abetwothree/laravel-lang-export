<?php

declare(strict_types=1);

namespace AbeTwoThree\LangExport\Data;

/**
 * Why a source re-export wrote nothing; the value ends the command's `Skipped {source}: …` line.
 */
enum SkipReason: string
{
    case Filtered = 'excluded by filters';
    case NotTranslationFile = 'not a translation file';
}
