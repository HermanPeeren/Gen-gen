<?php

/**
 * @package     Gengen
 * @subpackage  View
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Component\Gengen\Administrator\View\Generators;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Yepr\Component\Gengen\Administrator\Generator\VocabularyContext;
use Yepr\Component\Gengen\Administrator\Model\GeneratorsModel;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The list of generators.
 *
 * @since  0.1.0
 */
class HtmlView extends BaseHtmlView
{
	/**
	 * The generators.
	 *
	 * @var    object[]
	 * @since  0.1.0
	 */
	protected $items = [];

	/**
	 * Paging.
	 *
	 * @var    \Joomla\CMS\Pagination\Pagination
	 * @since  0.1.0
	 */
	protected $pagination;

	/**
	 * Filters and ordering.
	 *
	 * @var    \Joomla\CMS\Object\CMSObject|\Joomla\Registry\Registry
	 * @since  0.1.0
	 */
	protected $state;

	/**
	 * The targets this site can model a generator for.
	 *
	 * Shown when there are none, because "no generators yet" and "nothing on
	 * this site can be generated for" look identical on an empty list and are
	 * very different problems.
	 *
	 * @var    string[]
	 * @since  0.1.0
	 */
	protected $targets = [];

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
		$model = $this->listModel();

		$this->items      = $model->getItems();
		$this->pagination = $model->getPagination();
		$this->state      = $model->getState();
		$this->targets    = VocabularyContext::library()->targets();

		$this->addToolbar();

		parent::display($tpl);
	}

	/**
	 * The model this view is of.
	 *
	 * `getModel()` promises a BaseDatabaseModel, which has no getItems(). A
	 * check rather than a type hint in a comment: if the wiring is ever wrong
	 * this says which view wanted which model, instead of failing on an
	 * undefined method three lines later.
	 *
	 * @return  GeneratorsModel
	 *
	 * @since   0.1.0
	 */
	private function listModel(): GeneratorsModel
	{
		$model = $this->getModel();

		if (!$model instanceof GeneratorsModel) {
			throw new \RuntimeException('The generators view needs a GeneratorsModel.');
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

		ToolbarHelper::title(Text::_('COM_GENGEN_MANAGER_GENERATORS'), 'cogs');

		$toolbar->addNew('generator.add');

		$dropdown = $toolbar->dropdownButton('status-group')
			->text('JTOOLBAR_CHANGE_STATUS')
			->toggleSplit(false)
			->icon('icon-ellipsis-h')
			->buttonClass('btn btn-action')
			->listCheck(true);

		$childBar = $dropdown->getChildToolbar();
		$childBar->publish('generators.publish')->listCheck(true);
		$childBar->unpublish('generators.unpublish')->listCheck(true);
		$childBar->delete('generators.delete')
			->message('JGLOBAL_CONFIRM_DELETE')
			->listCheck(true);

		$toolbar->preferences('com_gengen');
	}
}
