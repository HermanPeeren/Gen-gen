<?php

/**
 * @package     Gengen
 * @subpackage  Extension
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Component\Gengen\Administrator\Extension;

use Joomla\CMS\Extension\BootableExtensionInterface;
use Joomla\CMS\Extension\MVCComponent;
use Joomla\CMS\HTML\HTMLRegistryAwareTrait;
use Psr\Container\ContainerInterface;
use Yepr\Component\Gengen\Administrator\Generator\VocabularyContext;
use Yepr\Component\Gengen\Administrator\Generator\VocabularyLibrary;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The component.
 *
 * The guard above is `_JEXEC`. Exten-gen's was `JPATH_PLATFORM`, a constant
 * Joomla 6 removed, and the result was every view returning HTTP 200 with a
 * zero-byte body and nothing in any log - because `die` is not an error. Once
 * is enough to learn from.
 *
 * @since  0.1.0
 */
class GengenComponent extends MVCComponent implements BootableExtensionInterface
{
	/**
	 * Where setRegistry() comes from.
	 *
	 * The service provider calls it, and MVCComponent does not have it - so
	 * without this the component builds fine right up until something asks
	 * the container for it, and then dies with "undefined method". Nothing
	 * static sees it, because the call is in a closure in a file Joomla
	 * includes at run time.
	 */
	use HTMLRegistryAwareTrait;

	/**
	 * Booting the extension.
	 *
	 * One thing, since 0.6: the form fields get the vocabulary library the
	 * container holds.
	 *
	 * @param   ContainerInterface  $container  The container.
	 *
	 * @return  void
	 *
	 * @since   0.1.0
	 */
	public function boot(ContainerInterface $container)
	{
		// The form fields read the vocabulary library through VocabularyContext,
		// because Joomla makes them with `new` and nothing can inject into them.
		// They get the one the container holds, so the fields and the import
		// cannot disagree about which targets this site has.
		if ($container->has(VocabularyLibrary::class)) {
			VocabularyContext::useLibrary($container->get(VocabularyLibrary::class));
		}
	}
}
