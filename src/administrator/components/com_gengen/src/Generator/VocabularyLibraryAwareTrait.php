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
 * The half of `VocabularyLibraryAwareInterface` every implementer would write the same.
 *
 * @since  0.6.0
 */
trait VocabularyLibraryAwareTrait
{
    /**
     * @var    ?VocabularyLibrary
     * @since  0.6.0
     */
    private ?VocabularyLibrary $vocabularyLibrary = null;

    /**
     * @since  0.6.0
     */
    public function setVocabularyLibrary(VocabularyLibrary $library): void
    {
        $this->vocabularyLibrary = $library;
    }

    /**
     * The library, which must have been handed over.
     *
     * @throws \UnexpectedValueException  When nothing did: the object was made outside the component's factory.
     *
     * @since  0.6.0
     */
    protected function getVocabularyLibrary(): VocabularyLibrary
    {
        return $this->vocabularyLibrary ?? throw new \UnexpectedValueException(
            static::class . ' was not given the vocabulary library. Ask the component\'s MVC factory for it.'
        );
    }
}
