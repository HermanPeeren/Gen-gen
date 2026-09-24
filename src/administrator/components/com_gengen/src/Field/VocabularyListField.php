<?php

/**
 * @package     Gengen
 * @subpackage  Field
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Component\Gengen\Administrator\Field;

use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\Language\Text;
use Yepr\Component\Gengen\Administrator\Generator\VocabularyContext;
use Yepr\Gen\Core\Rule\Vocabulary;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * A dropdown whose choices come from the target's published vocabulary.
 *
 * This is what makes the generator form a modelling tool rather than a JSON
 * editor: you do not type the name of a selector, you pick one the target
 * actually registered, so a rule naming something that does not exist cannot be
 * saved in the first place. The same closed vocabulary the engine enforces,
 * offered rather than checked.
 *
 * When no target can be determined - none installed, or several and none chosen
 * - the list is empty and says why. An empty list is honest; a list assembled
 * from whichever target happened to be found first would let somebody build a
 * rule set that validates against nothing.
 *
 * @since  0.1.0
 */
abstract class VocabularyListField extends ListField
{
	/**
	 * The names this field offers.
	 *
	 * @return  string[]
	 *
	 * @since   0.1.0
	 */
	abstract protected function choices(): array;

	/**
	 * What to say when there is nothing to choose from.
	 *
	 * @return  string
	 *
	 * @since   0.1.0
	 */
	protected function emptyLabel(): string
	{
		return 'COM_GENGEN_FIELD_NO_TARGET';
	}

	/**
	 * The vocabulary being offered, or null when it cannot be determined.
	 *
	 * @return  ?Vocabulary
	 *
	 * @since   0.1.0
	 */
	protected function vocabulary(): ?Vocabulary
	{
		return VocabularyContext::current();
	}

	/**
	 * The options, as Joomla wants them.
	 *
	 * @return  \stdClass[]
	 *
	 * @since   0.1.0
	 */
	protected function getOptions(): array
	{
		$choices = $this->choices();

		if ($choices === []) {
			return [(object) ['value' => '', 'text' => Text::_($this->emptyLabel())]];
		}

		$options = [];

		// A list with no empty entry cannot say "nothing", and saying
		// nothing is what most of these fields do most of the time.
		//
		// Without it Joomla renders the first option as the selected one
		// whenever the stored value is empty, and `showon` does not help:
		// it hides the field, it does not stop it posting. So opening a
		// generator and pressing Save rewrote every binding that names no
		// derivation to name the first one in the list - twenty-seven rules
		// at a time, silently, on the generator that reproduces Exten-gen.
		//
		// Only where the form has not said the field is required. A rule
		// must name a selector and a template, and offering "nothing" there
		// would be offering to store a rule that cannot run.
		if (!$this->required) {
			$options[] = (object) ['value' => '', 'text' => Text::_('COM_GENGEN_FIELD_NONE')];
		}

		foreach ($choices as $choice) {
			$options[] = (object) ['value' => $choice, 'text' => $choice];
		}

		return array_merge(parent::getOptions(), $options);
	}
}
