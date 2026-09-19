<?php

/**
 * @package     Gengen
 * @subpackage  Field
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Component\Gengen\Administrator\Field;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The named function a binding calls.
 *
 * The one place a modelled generator reaches into code. A derivation is PHP
 * living in the target, and this offers the ones it registered - which is the
 * whole bargain: what is a lookup is modelled here, what is a computation is
 * written there, and neither side can invent a name the other does not know.
 *
 * @since  0.1.0
 */
class DerivationField extends VocabularyListField
{
	/**
	 * The field type, as a form refers to it.
	 *
	 * @var    string
	 * @since  0.1.0
	 */
	protected $type = 'Derivation';

	/**
	 * The target's derivations.
	 *
	 * @return  string[]
	 *
	 * @since   0.1.0
	 */
	protected function choices(): array
	{
		$vocabulary = $this->vocabulary();

		return $vocabulary === null ? [] : $vocabulary->derivations;
	}
}
