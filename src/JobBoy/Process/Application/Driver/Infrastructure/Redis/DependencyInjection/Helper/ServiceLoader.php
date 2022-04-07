<?php
declare(strict_types=1);

namespace JobBoy\Process\Application\Driver\Infrastructure\Redis\DependencyInjection\Helper;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\DirectoryLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class ServiceLoader
{
    public function load(ContainerBuilder $container)
    {
        $locator = new FileLocator(__DIR__ . '/../config');
        $loader = new DirectoryLoader($container, $locator);
        $resolver = new LoaderResolver([
            new YamlFileLoader($container, $locator),
            $loader,
        ]);
        $loader->setResolver($resolver);

        $loader->load('services');

        return $container;
    }
}
