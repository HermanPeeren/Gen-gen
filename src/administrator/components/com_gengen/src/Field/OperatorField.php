<?php

/**
 * @package     Gengen
 * @subpackage  Field
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Component\Gengen\Administrator\Field;

use Yepr\Gen\Core\Rule\Condition;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The four condition operators.
 *
 * From the library rather than from the target: a target may add selectors and
 * derivations, it does not get to add operators. Read off Condition::OPERATORS
 * so the dropdown and the thing that accepts the choice cannot disagree.
 *
 * @since  0.1.0
 */
class OperatorField extends VocabularyListField
{
	/**
	 * The field type, as a form refers to it.
	 *
	 * @var    string
	 * @since  0.1.0
	 */
	protected $type = 'Operator';

	/**
	 * The operators.
	 *
	 * @return  string[]
	 *
	 * @since   0.1.0
	 */
	protected function choices(): array
	{
		return Condition::OPERATORS;
	}
}
