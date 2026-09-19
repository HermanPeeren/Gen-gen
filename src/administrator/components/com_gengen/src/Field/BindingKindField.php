<?php

/**
 * @package     Gengen
 * @subpackage  Field
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Component\Gengen\Administrator\Field;

use Yepr\Gen\Core\Rule\Binding;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The five binding kinds, likewise from the library.
 *
 * Which boxes the rest of the binding form shows depends on what is chosen
 * here - see binding.xml, where four of the fields carry a showon.
 *
 * @since  0.1.0
 */
class BindingKindField extends VocabularyListField
{
	/**
	 * The field type, as a form refers to it.
	 *
	 * @var    string
	 * @since  0.1.0
	 */
	protected $type = 'BindingKind';

	/**
	 * The kinds.
	 *
	 * @return  string[]
	 *
	 * @since   0.1.0
	 */
	protected function choices(): array
	{
		return Binding::KINDS;
	}
}
