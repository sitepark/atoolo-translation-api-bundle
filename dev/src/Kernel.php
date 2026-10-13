<?php

declare(strict_types=1);

namespace Atoolo\TranslationApi\Dev;

use Atoolo\TranslationApi\AtooloTranslationApiBundle;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * Minimal application to try out the bundle (e.g. with the i18next demo) and
 * to run the functional tests against.
 */
final class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new AtooloTranslationApiBundle();
    }

    public function getProjectDir(): string
    {
        return \dirname(__DIR__);
    }

    public function getCacheDir(): string
    {
        return \dirname(__DIR__, 2) . '/var/cache/dev-kernel/' . $this->environment;
    }

    public function getLogDir(): string
    {
        return \dirname(__DIR__, 2) . '/var/log';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'dev',
            'test' => $this->environment === 'test',
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'default_locale' => 'en',
            'translator' => [
                'default_path' => '%kernel.project_dir%/translations',
                'fallbacks' => ['en'],
            ],
        ]);
        $container->extension('atoolo_translation_api', [
            'domains' => ['frontend'],
            'locales' => ['de', 'en', 'en_GB'],
            'cors_allow_origins' => ['*'],
        ]);
        if ($this->environment === 'test') {
            $container->services()->set('logger', NullLogger::class);
        }
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('@AtooloTranslationApiBundle/config/routes.yaml');
    }
}
