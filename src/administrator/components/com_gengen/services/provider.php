<?php

/**
 * @package     Gengen
 * @subpackage  Services
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Extension\Service\Provider\ComponentDispatcherFactory;
use Joomla\CMS\HTML\Registry;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Yepr\Component\Gengen\Administrator\Extension\GengenComponent;
use Yepr\Component\Gengen\Administrator\Service\Provider\MVCFactory;
use Yepr\Component\Gengen\Administrator\Service\Provider\Vocabularies;

/**
 * The Gen-gen service provider: the composition root.
 *
 * No category factory, no associations, no router. Exten-gen registers all
 * three and uses none of them; a provider that registers what a component
 * actually has is one you can read to find out what it has.
 *
 * Since 0.6 that includes a service of its own: the vocabulary library
 * (`Vocabularies`), which this component's MVC factory hands to the import the
 * way Joomla's hands over the database (`MVCFactory`, this component's, in
 * place of Joomla's).
 */
return new class () implements ServiceProviderInterface {
	/**
	 * Register the component with the container.
	 *
	 * @param   Container  $container  The DI container.
	 *
	 * @return  void
	 */
	public function register(Container $container)
	{
		$container->registerServiceProvider(new Vocabularies());
		$container->registerServiceProvider(new MVCFactory('\Yepr\Component\Gengen'));
		$container->registerServiceProvider(new ComponentDispatcherFactory('\Yepr\Component\Gengen'));

		$container->set(
			ComponentInterface::class,
			function (Container $container) {
				$component = new GengenComponent($container->get(ComponentDispatcherFactoryInterface::class));

				$component->setRegistry($container->get(Registry::class));
				$component->setMVCFactory($container->get(MVCFactoryInterface::class));

				return $component;
			}
		);
	}
};
