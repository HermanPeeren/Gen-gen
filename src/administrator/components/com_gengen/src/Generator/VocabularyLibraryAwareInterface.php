<?php

/**
 * @package     Gengen
 * @subpackage  Generator
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Yepr\Component\Gengen\Administrator\Generator;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Something that is handed the vocabulary library rather than making one.
 *
 * The component's MVC factory hands it to every model and controller that
 * implements this, the way Joomla's own hands over the database and the form
 * factory. Joomla builds those classes itself, with constructors it decides,
 * so a setter is where injection can happen. The Extension Generator does the
 * same with its generator catalogue.
 *
 * @since  0.6.0
 */
interface VocabularyLibraryAwareInterface
{
    /**
     * @since  0.6.0
     */
    public function setVocabularyLibrary(VocabularyLibrary $library): void;
}
