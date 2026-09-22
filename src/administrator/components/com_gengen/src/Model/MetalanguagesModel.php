<?php

/**
 * @package     Gengen
 * @subpackage  Gengen component
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Yepr\Component\Gengen\Administrator\Model;

use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Yepr\Component\Gengen\Administrator\Metalanguage\Metalanguages;
use Yepr\Gen\Joomla\Metalanguage\MetalanguageEntry;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The metalanguages this site can write a project in: step 3.4.
 *
 * A plain model rather than a `ListModel`, and that is a decision rather than
 * a shortcut. `ListModel` brings pagination, filter state and an ordering
 * form, and every one of them assumes the list is a query over one table -
 * which this is not: ER1 is in it and is not a row anywhere. A site holds a
 * handful of languages, not a page of them.
 *
 * Reading also goes through the catalogue rather than through a query here,
 * so that what this screen shows and what the dropdown on a project offers
 * cannot come apart.
 *
 * @since  0.2.0
 */
class MetalanguagesModel extends BaseDatabaseModel
{
    /**
     * Every language this site has imported.
     *
     * @return MetalanguageEntry[]
     *
     * @since  0.2.0
     */
    public function getItems(): array
    {
        return Metalanguages::catalogue($this->getDatabase())->all();
    }

    /**
     * How many generators are written for each language, keyed by binding.
     *
     * The screen needs it to say what removing one would break. Generators
     * that carry no binding are counted under the empty key: those are the
     * ones written before 3.4, against their target's own selectors.
     *
     * @return array<string, int>
     *
     * @since  0.2.0
     */
    public function generatorCounts(): array
    {
        $database = $this->getDatabase();

        $query = $database->getQuery(true)
            ->select([
                $database->quoteName('metalanguage_key'),
                $database->quoteName('metalanguage_version'),
                'COUNT(*) AS ' . $database->quoteName('total'),
            ])
            ->from($database->quoteName('#__extengen_projects'))
            ->group($database->quoteName('metalanguage_key'))
            ->group($database->quoteName('metalanguage_version'));

        $database->setQuery($query);

        $counts = [];

        foreach ($database->loadObjectList() ?: [] as $row) {
            $key = (string) $row->metalanguage_key;

            $counts[$key === '' ? '' : $key . '|' . $row->metalanguage_version] = (int) $row->total;
        }

        return $counts;
    }
}
