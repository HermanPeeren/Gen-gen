<?php

/**
 * @package     Gengen
 * @subpackage  Table
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Component\Gengen\Administrator\Table;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;
use Yepr\Component\Gengen\Administrator\Generator\GeneratorDefinition;
use Yepr\Gen\Core\Rule\RuleException;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * One stored generator.
 *
 * The row is thin on purpose: a name, a target, and the modelled generator as
 * JSON. The shape of that JSON is the forms, and putting it in columns would
 * mean maintaining the same structure twice - once as a form and once as a
 * schema - with a migration every time a rule gains a field.
 *
 * @since  0.1.0
 */
class GeneratorTable extends Table
{
	/**
	 * Columns here mean NULL when they are null, rather than an empty string.
	 *
	 * @var    boolean
	 * @since  0.1.0
	 */
	protected $_supportNullValue = true;

	/**
	 * Constructor.
	 *
	 * @param   DatabaseDriver  $db  Database connector object.
	 *
	 * @since   0.1.0
	 */
	public function __construct(DatabaseDriver $db)
	{
		$this->typeAlias = 'com_gengen.generator';

		parent::__construct('#__gengen_generators', 'id', $db);
	}

	/**
	 * Refuse a row that is not a generator.
	 *
	 * The stored JSON is read back by `GeneratorDefinition` and then by the
	 * pipeline, neither of which is a good place to discover that somebody
	 * saved something else. Checking it here means a bad row never lands.
	 *
	 * `setError()` rather than throwing: `AdminModel::save()` inspects
	 * `if (!$table->check())` and turns a refused save into a message on the
	 * form the person is looking at. Throwing escapes that and produces an
	 * error page with the edit lost.
	 *
	 * @return  boolean  True when the row can be stored.
	 *
	 * @since   0.1.0
	 */
	public function check()
	{
		try {
			parent::check();
		} catch (\Exception $e) {
			$this->setError($e->getMessage());

			return false;
		}

		if (trim((string) $this->name) === '') {
			$this->setError(Text::_('COM_GENGEN_ERROR_NAME_REQUIRED'));

			return false;
		}

		if ((string) $this->form_data === '') {
			return true;
		}

		try {
			$definition = GeneratorDefinition::fromFormData(
				(array) json_decode((string) $this->form_data, true, 512, \JSON_THROW_ON_ERROR)
			);

			// Reading the rules is what proves they are rules. A rule missing
			// its selector or naming a binding kind that does not exist throws
			// here rather than three screens later, when somebody presses
			// Generate.
			$definition->rules();
		} catch (\JsonException | RuleException | \InvalidArgumentException $e) {
			$this->setError(Text::sprintf('COM_GENGEN_ERROR_NOT_A_GENERATOR', $e->getMessage()));

			return false;
		}

		return true;
	}
}
