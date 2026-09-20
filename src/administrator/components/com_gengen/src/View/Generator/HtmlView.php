<?php

/**
 * @package     Gengen
 * @subpackage  View
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Component\Gengen\Administrator\View\Generator;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Yepr\Component\Gengen\Administrator\Model\GeneratorModel;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Editing one generator.
 *
 * @since  0.1.0
 */
class HtmlView extends BaseHtmlView
{
	/**
	 * The form.
	 *
	 * @var    \Joomla\CMS\Form\Form
	 * @since  0.1.0
	 */
	protected $form;

	/**
	 * The stored generator.
	 *
	 * @var    object
	 * @since  0.1.0
	 */
	protected $item;

	/**
	 * Render.
	 *
	 * @param   ?string  $tpl  The layout.
	 *
	 * @return  void
	 *
	 * @since   0.1.0
	 */
	public function display($tpl = null): void
	{
		$model = $this->editModel();

		$this->form = $model->getForm();
		$this->item = $model->getItem();

		$this->addToolbar();

		parent::display($tpl);
	}

	/**
	 * The model this view is of.
	 *
	 * @return  GeneratorModel
	 *
	 * @since   0.1.0
	 */
	private function editModel(): GeneratorModel
	{
		$model = $this->getModel();

		if (!$model instanceof GeneratorModel) {
			throw new \RuntimeException('The generator view needs a GeneratorModel.');
		}

		return $model;
	}

	/**
	 * The buttons.
	 *
	 * @return  void
	 *
	 * @since   0.1.0
	 */
	private function addToolbar(): void
	{
		$toolbar = $this->getDocument()->getToolbar();
		$isNew   = empty($this->item->id);

		ToolbarHelper::title(
			Text::_($isNew ? 'COM_GENGEN_GENERATOR_NEW' : 'COM_GENGEN_GENERATOR_EDIT'),
			'cogs'
		);

		$toolbar->apply('generator.apply');
		$toolbar->save('generator.save');
		$toolbar->save2new('generator.save2new');

		// Generating needs something stored to generate from, so it is not
		// offered on a form that has never been saved. Offering it and then
		// explaining would be a worse way to say the same thing.
		if (!$isNew) {
			$toolbar->standardButton('cog', 'COM_GENGEN_TOOLBAR_GENERATE', 'generator.generate')
				->icon('icon-cog');
		}

		$toolbar->cancel('generator.cancel', $isNew ? 'JTOOLBAR_CANCEL' : 'JTOOLBAR_CLOSE');
	}
}
