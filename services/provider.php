<?php

/**
 * @package     Bw.Plugin.Task.ArticleHousekeeping
 * @copyright   (C) 2024 Barclay.Works
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

use Bw\Plugin\Task\ArticleHousekeeping\Extension\ArticleHousekeeping;
use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;

return new class () implements ServiceProviderInterface {
    /**
     * Registers the service provider with a DI container.
     *
     * @param   Container  $container  The DI container.
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function register(Container $container): void
    {
        $container->set(
            PluginInterface::class,
            function (Container $container) {
                $plugin = new ArticleHousekeeping(
                    $container->get(DispatcherInterface::class),
                    (array) PluginHelper::getPlugin('task', 'bw_articlehousekeeping')
                );

                $plugin->setApplication(Factory::getApplication());
                $plugin->setDatabase($container->get(DatabaseInterface::class));

                return $plugin;
            }
        );
    }
};
