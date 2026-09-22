<?php

/**
 * @package     Gengen
 * @subpackage  Metalanguage
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Yepr\Component\Gengen\Administrator\Metalanguage;

use Yepr\Gen\Joomla\Metalanguage\MetalanguageEntry;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Which metalanguage the form fields are currently offering concepts from.
 *
 * The same seam as `VocabularyContext`, for the same reason and with the same
 * apology: Joomla constructs form fields itself, from XML, with no constructor
 * arguments and no container. The language is chosen once at the top of the
 * generator form and needed by a field nested two subforms deep, where
 * `$this->form` is the subform's own Form and knows nothing about it.
 *
 * So the edit form says which language it is for, once, before rendering, and
 * the fields read it here. Small, named, and resettable - which is what keeps
 * it testable.
 *
 * **Null means no language, and that is a real answer.** A generator written
 * before 3.4 names selectors from its target's own vocabulary; the fields fall
 * back to exactly that, so nothing that worked stops working.
 *
 * @since  0.2.0
 */
final class MetalanguageContext
{
    /**
     * The language the form being rendered is for.
     *
     * @var    ?MetalanguageEntry
     * @since  0.2.0
     */
    private static ?MetalanguageEntry $language = null;

    /**
     * Say which language the form about to be rendered is for.
     *
     * @since  0.2.0
     */
    public static function useLanguage(?MetalanguageEntry $language): void
    {
        self::$language = $language;
    }

    /**
     * The language the fields should be offering concepts from, if any.
     *
     * Impure, and it has to say so: an analyser that assumes a static call
     * returns the same thing twice in one scope concludes the second call is
     * null because the first was, which is backwards for a method whose whole
     * job is to read state somebody just set.
     *
     * @phpstan-impure
     *
     * @since  0.2.0
     */
    public static function current(): ?MetalanguageEntry
    {
        return self::$language;
    }

    /**
     * Forget it, so one test cannot decide what the next one sees.
     *
     * @since  0.2.0
     */
    public static function reset(): void
    {
        self::$language = null;
    }
}
