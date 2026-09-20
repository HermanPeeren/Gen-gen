<?php

/**
 * @package     Gengen
 * @subpackage  Model
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Component\Gengen\Administrator\Model;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\AdminModel;
use Yepr\Component\Gengen\Administrator\Generator\GeneratorDefinition;
use Yepr\Component\Gengen\Administrator\Generator\VocabularyContext;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Editing one generator.
 *
 * The whole modelled generator is stored as JSON in `form_data`, so this is
 * mostly two lines: decode it on the way in, encode it on the way out. What is
 * not two lines is telling the form fields which target they are offering - see
 * `loadFormData()`.
 *
 * @since  0.1.0
 */
class GeneratorModel extends AdminModel
{
	/**
	 * The form this model edits.
	 *
	 * @param   array<string, mixed>  $data      Data for the form.
	 * @param   boolean               $loadData  Whether to load the stored data.
	 *
	 * @return  Form|boolean
	 *
	 * @since   0.1.0
	 */
	public function getForm($data = [], $loadData = true)
	{
		$form = $this->loadForm('com_gengen.generator', 'generator', ['control' => 'jform', 'load_data' => $loadData]);

		return empty($form) ? false : $form;
	}

	/**
	 * The stored generator, and the target its fields should offer.
	 *
	 * The second half is why this is not the usual three lines. Every dropdown
	 * in the rule subforms is filled from one target's published vocabulary,
	 * and a field two subforms deep cannot reach the `target` value at the top
	 * of the form - `$this->form` there is the subform's own Form. So the
	 * target is put where the fields look for it, once, before anything
	 * renders. See VocabularyContext.
	 *
	 * @return  object|array<string, mixed>
	 *
	 * @since   0.1.0
	 */
	protected function loadFormData()
	{
		$application = Factory::getApplication();

		// What a failed save left behind, so the form comes back with the
		// person's edits in it rather than with what was last stored.
		$data = $application->getUserState('com_gengen.edit.generator.data', []);

		if (empty($data)) {
			$item = $this->getItem();

			$data = $item;

			if (!empty($item->form_data)) {
				$stored = json_decode((string) $item->form_data);

				// A row whose JSON will not decode must not take down the page
				// somebody needs in order to fix it. Enqueued rather than
				// setError(), which nothing reads on this path: the form would
				// come back empty with no word of why.
				if ($stored === null) {
					$application->enqueueMessage(
						Text::_('COM_GENGEN_ERROR_STORED_GENERATOR_UNREADABLE'),
						'warning'
					);
				} else {
					$data = $stored;
				}
			}
		}

		VocabularyContext::useTarget(
			\is_object($data) ? ($data->target ?? null) : ($data['target'] ?? null)
		);

		$this->preprocessData('com_gengen.generator', $data);

		return $data;
	}

	/**
	 * Store the whole modelled generator, and the two things a list needs.
	 *
	 * `name` and `target` are columns as well as form fields, because a list
	 * view that had to decode every row's JSON to show a name would be a list
	 * view nobody can sort.
	 *
	 * @param   array<string, mixed>  $data  The posted form data.
	 *
	 * @return  boolean
	 *
	 * @since   0.1.0
	 */
	public function save($data)
	{
		$definition = GeneratorDefinition::fromFormData($data);

		$data['name']      = $definition->name;
		$data['target']    = $definition->target;
		$data['form_data'] = json_encode($data, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);

		return parent::save($data);
	}

	/**
	 * The stored generator, as the thing that can be generated from.
	 *
	 * @param   integer  $id  The generator's id.
	 *
	 * @return  GeneratorDefinition
	 *
	 * @since   0.1.0
	 */
	public function definition(int $id): GeneratorDefinition
	{
		$item = $this->getItem($id);

		if (empty($item->id)) {
			throw new \RuntimeException('There is no generator ' . $id . '.');
		}

		/** @var array<string, mixed> $data */
		$data = json_decode((string) $item->form_data, true, 512, \JSON_THROW_ON_ERROR);

		return GeneratorDefinition::fromFormData($data);
	}
}
