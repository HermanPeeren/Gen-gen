<?php

/**
 * @package     Gengen
 * @subpackage  Field
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Component\Gengen\Administrator\Field;

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
	 * Every selector a rule may be written for.
	 *
	 * The target's own, and one per concept of the language this generator is
	 * written for - "every Entity" - which the vocabulary adds because the
	 * language's reference table already knows where each type lives.
	 *
	 * **One list, and it has to be one list.** 3.4 offered concepts instead of
	 * selectors when a language was bound, and nothing added them to the
	 * vocabulary - so a rule written that way was refused when it ran, by a
	 * validator checking the name against the vocabulary's list. What a person
	 * picks here and what Exten-gen accepts are the same question, so they are
	 * now asked of the same object.
	 *
	 * By name rather than by key, which is the opposite of what a reference
	 * stored in a model does and is right for the opposite reason: a rule file
	 * is read by people, and `for: c-entity` is a rule nobody can check by eye.
	 *
	 * @return  string[]
	 *
	 * @since   0.1.0
	 */
	protected function choices(): array
	{
		$vocabulary = $this->vocabulary();

		if ($vocabulary === null) {
			return [];
		}

		$language = MetalanguageContext::current();

		if ($language === null) {
			return $vocabulary->selectors;
		}

		return $vocabulary->withConcepts(array_values($language->conceptChoices()))->selectors;
	}
}
