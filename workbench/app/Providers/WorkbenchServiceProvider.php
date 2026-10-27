<?php

declare(strict_types=1);

namespace Workbench\App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Translation\Translator;

use function Orchestra\Testbench\workbench_path;

class WorkbenchServiceProvider extends ServiceProvider
{
    /**
     * Points the app lang path at the workbench fixtures before the translator resolves.
     */
    public function register(): void
    {
        $this->app->useLangPath(workbench_path('lang-app'));
    }

    /**
     * Registers a package namespace, plus a module namespace and JSON path the way InterNACHI/modular does.
     */
    public function boot(): void
    {
        $this->loadTranslationsFrom(workbench_path('packages/courier/lang'), 'courier');

        $this->callAfterResolving('translator', function (Translator $translator): void {
            $module = workbench_path('app-modules/blog/resources/lang');

            $translator->addNamespace('blog', $module);
            $translator->addJsonPath($module);
        });
    }
}
