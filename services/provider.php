<?php

/**
 * @package    com_joomdle
 * @author     Antonio Duran <antonio@joomdle.com>
 * @copyright  2025 Antonio Duran
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Plugin\Joomdleprofile\Joomdlejoomlafieldsprofile\Service;

defined('_JEXEC') or die;

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;
use Joomla\Plugin\Joomdleprofile\Joomdlejoomlafieldsprofile\Extension\Joomdlejoomlafieldsprofile;

return new class () implements ServiceProviderInterface {
    /**
     * Registers the service provider with a DI container.
     *
     * @param   Container  $container  The DI container.
     *
     * @return  void
     *
     * @since   4.4.0
     */
    public function register(Container $container): void
    {
        $container->set(
            PluginInterface::class,
            function (Container $container) {
                $plugin     = new Joomdlejoomlafieldsprofile(
                    $container->get(DispatcherInterface::class),
                    (array) PluginHelper::getPlugin('joomdleprofile', 'joomdlejoomlafieldsprofile')
                );
                $plugin->setApplication(Factory::getApplication());
                $plugin->setDatabase($container->get(DatabaseInterface::class));

                return $plugin;
            }
        );
    }
};
