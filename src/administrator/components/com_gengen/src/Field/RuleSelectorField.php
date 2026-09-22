<?php

/**
 * @package     Gengen
 * @subpackage  Field
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Component\Gengen\Administrator\Field;

use Joomla\CMS\Language\Text;
use Yepr\Component\Gengen\Administrator\Metalanguage\MetalanguageContext;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Which source elements a rule applies to: the iteration, chosen not typed.
 *
 * **When the generator is written for a metalanguage, the choices are that
 * language's concepts** - step 3.4. A rule iterates over things a model holds,
 * and which things exist is a property of the language the model is written
 * in, not of the target. Nothing else can answer it: Gen-gen does not read
 * concept models, and does not need to, because the package's manifest names
 * every concept by key and by name.
 *
 * The value stored is the **key**, and the label is the name. A rule that
 * stored the name would come unpicked the moment somebody renamed a concept in
 * Meta-gen - silently, because the rule would still be a string and still look
 * like one.
 *
 * **With no language bound, the target's own selectors are offered**, exactly
 * as before. A generator written before 3.4 names selectors from
 * `Joomla6Selectors`, PHP hard-keyed to ER1, and none of them stop working.
 * 3.6 is where a selector becomes a path *through* a modelled language rather
 * than a name from a fixed list, and where these two lists become one.
 *
 * @since  0.1.0
 */
class RuleSelectorField extends VocabularyListField
{
	/**
	 * The field type, as a form refers to it.
	 *
	 * @var    string
	 * @since  0.1.0
	 */
	protected $type = 'RuleSelector';

	/**
	 * The target's selectors.
	 *
	 * @return  string[]
	 *
	 * @since   0.1.0
	 */
	protected function choices(): array
	{
		$vocabulary = $this->vocabulary();

		return $vocabulary === null ? [] : $vocabulary->selectors;
	}

	/**
	 * The options, which are a language's concepts when there is one.
	 *
	 * Overridden rather than folded into `choices()` because these have a
	 * value that differs from their label - the key is stored and the name is
	 * shown - and `choices()` is a list of names that are both.
	 *
	 * @return  \stdClass[]
	 *
	 * @since   0.2.0
	 */
	protected function getOptions(): array
	{
		$language = MetalanguageContext::current();

		if ($language === null) {
			return parent::getOptions();
		}

		$concepts = $language->conceptChoices();

		if ($concepts === []) {
			// A language imported from a package built before its manifest
			// named concepts. Falling back to the target's selectors would
			// offer ER1's names for a language that is not ER1, so it says
			// there is nothing instead.
			return [(object) ['value' => '', 'text' => Text::_('COM_GENGEN_FIELD_NO_CONCEPTS')]];
		}

		$options = [];

		foreach ($concepts as $key => $name) {
			$options[] = (object) ['value' => $key, 'text' => $name];
		}

		return $options;
	}
}
