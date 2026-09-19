<?php

/**
 * @package     Gengen
 * @subpackage  Field
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Component\Gengen\Administrator\Field;

use Yepr\Component\Gengen\Administrator\Generator\VocabularyContext;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Which target this generator produces.
 *
 * The targets are whatever is installed: each publishes a vocabulary descriptor
 * inside its own component, and Gen-gen finds them rather than being told. So
 * this list is empty on a site with nothing to generate for, which is the
 * truthful answer.
 *
 * @since  0.1.0
 */
class GeneratorTargetField extends VocabularyListField
{
	/**
	 * The field type, as a form refers to it.
	 *
	 * @var    string
	 * @since  0.1.0
	 */
	protected $type = 'GeneratorTarget';

	/**
	 * The installed targets.
	 *
	 * @return  string[]
	 *
	 * @since   0.1.0
	 */
	protected function choices(): array
	{
		return VocabularyContext::library()->targets();
	}

	/**
	 * What to say when nothing on the site publishes a vocabulary.
	 *
	 * @return  string
	 *
	 * @since   0.1.0
	 */
	protected function emptyLabel(): string
	{
		return 'COM_GENGEN_FIELD_NO_TARGETS_INSTALLED';
	}
}
