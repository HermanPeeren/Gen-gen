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

use Joomla\Database\DatabaseInterface;
use Yepr\Gen\Joomla\Metalanguage\MetalanguageCatalogue;
use Yepr\Gen\Joomla\Metalanguage\MetalanguageImporter;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * What this component brings to the shared metalanguage store: step 3.4.
 *
 * The store is the library's, because Exten-gen keeps the same kind of list.
 * What differs here is one thing and the absence of another: the table, and
 * **no built-in language at all**.
 *
 * Exten-gen ships ER1 because it has to open the projects already in its
 * database. Gen-gen ships nothing, and that is the honest answer rather than a
 * gap: a generator written before 3.4 names selectors from its *target's*
 * vocabulary - `Joomla6Selectors::entities()`, PHP hard-keyed to ER1 - and
 * pretending those came from a metalanguage would be inventing a binding
 * nobody made. Such a generator has no language, says so, and keeps working
 * exactly as it did. 3.6 is where the target's selectors become a path through
 * a modelled language and the pretence would have had to be unpicked.
 *
 * @since  0.2.0
 */
final class Metalanguages
{
    /**
     * Where this component keeps the languages it has imported.
     *
     * @since  0.2.0
     */
    public const TABLE = '#__gengen_metalanguages';

    /**
     * Every language a generator may be written for.
     *
     * No built-in is passed, so a site that has imported nothing has an empty
     * list - and the screens say so rather than offering a choice that means
     * nothing.
     *
     * @since  0.2.0
     */
    public static function catalogue(DatabaseInterface $database): MetalanguageCatalogue
    {
        return new MetalanguageCatalogue($database, self::TABLE);
    }

    /**
     * What installs an imported language and records it.
     *
     * @since  0.2.0
     */
    public static function importer(DatabaseInterface $database, string $siteRoot): MetalanguageImporter
    {
        return new MetalanguageImporter($database, self::TABLE, $siteRoot);
    }
}
