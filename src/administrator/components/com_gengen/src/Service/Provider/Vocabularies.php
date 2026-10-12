<?php

/**
 * @package     Gengen
 * @subpackage  Service
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Yepr\Component\Gengen\Administrator\Service\Provider;

use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Yepr\Component\Gengen\Administrator\Generator\VocabularyLibrary;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The targets this site can model a generator for, as a service.
 *
 * The one place the library is constructed. Shared - one per request - and
 * lazy, so a screen that never asks about a target never globs for one. The
 * component's MVC factory hands it to the import (see `Service\MVCFactory`),
 * and the component hands the same one to the form fields when it boots,
 * because Joomla makes those with `new` and nothing can inject into them (see
 * `VocabularyContext`).
 *
 * @since  0.6.0
 */
final class Vocabularies implements ServiceProviderInterface
{
    /**
     * @since  0.6.0
     */
    public function register(Container $container): void
    {
        $container->share(
            VocabularyLibrary::class,
            $container->lazy(
                VocabularyLibrary::class,
                static fn (): VocabularyLibrary => new VocabularyLibrary(
                    ...VocabularyLibrary::directoriesUnder(JPATH_ADMINISTRATOR)
                )
            )
        );
    }
}
