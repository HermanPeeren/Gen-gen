<?php

/**
 * @package     Gengen
 * @subpackage  Generator
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Component\Gengen\Administrator\Generator;

use Yepr\Gen\Core\Rule\Vocabulary;

/**
 * Which target's vocabulary the form fields are currently offering.
 *
 * Ambient state, which deserves an explanation rather than an apology. Joomla
 * constructs form fields itself, from XML, with no constructor arguments and no
 * container - a field's only inputs are its own attributes and whatever it can
 * reach. The target, meanwhile, is chosen once at the top of the generator form
 * and needed by fields nested two subforms deep, where `$this->form` is the
 * subform's own Form and knows nothing about it.
 *
 * So the edit form says which target it is for, once, before rendering, and the
 * fields read it here. That is the seam; it is small, it is named, and it is
 * resettable, which is what keeps it testable.
 *
 * With exactly one target installed, nothing has to say anything: one is the
 * answer. With none or several, a field that guessed would offer one target's
 * derivations for another target's rules, so it offers nothing instead.
 *
 * @since  0.1.0
 */
final class VocabularyContext
{
    /**
     * Where vocabularies are found.
     *
     * @var    ?VocabularyLibrary
     * @since  0.1.0
     */
    private static ?VocabularyLibrary $library = null;

    /**
     * The target the form being rendered is for.
     *
     * @var    ?string
     * @since  0.1.0
     */
    private static ?string $target = null;

    /**
     * Use this library rather than looking one up.
     *
     * @param   VocabularyLibrary  $library  The library.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public static function useLibrary(VocabularyLibrary $library): void
    {
        self::$library = $library;
    }

    /**
     * Say which target the form about to be rendered is for.
     *
     * @param   ?string  $target  The target's name, or null to fall back to the only one installed.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public static function useTarget(?string $target): void
    {
        self::$target = $target === '' ? null : $target;
    }

    /**
     * Where vocabularies are found, defaulting to this site's components.
     *
     * A target publishes its descriptor inside its own component, beside the
     * rule file it constrains. Finding them is a glob rather than a registry
     * because a registry would be a third place to keep the same list.
     *
     * @return  VocabularyLibrary
     *
     * @since   0.1.0
     */
    public static function library(): VocabularyLibrary
    {
        if (self::$library !== null) {
            return self::$library;
        }

        $directories = [];

        if (\defined('JPATH_ADMINISTRATOR')) {
            $directories = glob(JPATH_ADMINISTRATOR . '/components/*/src/Generator/Rules', \GLOB_ONLYDIR) ?: [];
        }

        return self::$library = new VocabularyLibrary(...$directories);
    }

    /**
     * The vocabulary the fields should be offering, if it can be known.
     *
     * Impure, and it has to say so: an analyser that assumes a static call
     * returns the same thing twice in one scope concludes that the second call
     * is null because the first one was, which is exactly backwards for a
     * method whose whole job is to read state somebody just changed.
     *
     * @return  ?Vocabulary
     *
     * @phpstan-impure
     *
     * @since   0.1.0
     */
    public static function current(): ?Vocabulary
    {
        $library = self::library();

        if (self::$target !== null) {
            return $library->has(self::$target) ? $library->get(self::$target) : null;
        }

        return $library->only();
    }

    /**
     * Forget everything, so one test cannot decide what the next one sees.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public static function reset(): void
    {
        self::$library = null;
        self::$target  = null;
    }
}
