<?php

/**
 * @package     Gengen
 * @subpackage  Field
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Yepr\Component\Gengen\Administrator\Field;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Yepr\Component\Gengen\Administrator\Metalanguage\Metalanguages;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The metalanguage a generator is written for: step 3.4.
 *
 * A generator transforms a model into a target, and a rule in it names
 * concepts - which only mean anything inside a language. So a generator says
 * which language it is for, beside the target it is for, and the rules below
 * offer that language's concepts.
 *
 * **Empty is an answer, not a blank.** A generator written before 3.4 names
 * selectors from its target's own vocabulary, and those keep working; saying
 * "no language" is exactly true of one of those. That is also why this list
 * has no built-in at the top the way Exten-gen's has: Exten-gen ships ER1
 * because it has to open the projects already in its database, and this
 * component ships nothing.
 *
 * **The class name is the file name, exactly.** Joomla builds it with
 * `ucwords`, which touches letters after whitespace and nothing else - the
 * defect that produced `RuleselectorField` against `RuleSelectorField` here at
 * 2.2, found by CI and not by this machine.
 *
 * @since  0.2.0
 */
class MetalanguageField extends ListField
{
    /**
     * The field class must know its own type.
     *
     * @var    string
     * @since  0.2.0
     */
    protected $type = 'Metalanguage';

    /**
     * Every language this site has imported.
     *
     * @return  \stdClass[]
     *
     * @since   0.2.0
     */
    protected function getOptions(): array
    {
        $database = Factory::getContainer()->get(DatabaseInterface::class);
        $options  = [(object) ['value' => '', 'text' => Text::_('COM_GENGEN_FIELD_NO_METALANGUAGE')]];

        foreach (Metalanguages::catalogue($database)->all() as $entry) {
            $options[] = (object) ['value' => $entry->binding(), 'text' => $entry->label()];
        }

        return array_merge($options, parent::getOptions());
    }
}
