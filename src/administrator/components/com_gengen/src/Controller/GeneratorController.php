<?php

/**
 * @package     Gengen
 * @subpackage  Controller
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Component\Gengen\Administrator\Controller;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Router\Route;
use Yepr\Component\Gengen\Administrator\Model\GenerateModel;
use Yepr\Component\Gengen\Administrator\Model\GeneratorModel;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Editing one generator, and running it.
 *
 * @since  0.1.0
 */
class GeneratorController extends FormController
{
	/**
	 * Run the generator this row holds, and say what came out.
	 *
	 * A task on the form controller rather than a view of its own: generating
	 * is something you do to a generator, and the answer is a sentence and a
	 * file, not a screen.
	 *
	 * @return  void
	 *
	 * @since   0.1.0
	 */
	public function generate(): void
	{
		$this->checkToken();

		$application = $this->app;
		$id          = (int) $application->getInput()->getInt('id');
		$back        = Route::_('index.php?option=com_gengen&view=generators', false);

		if ($id === 0) {
			$application->enqueueMessage(Text::_('COM_GENGEN_ERROR_NOTHING_TO_GENERATE'), 'warning');
			$application->redirect($back);

			return;
		}

		/** @var GeneratorModel $generators */
		$generators = $this->getModel('Generator');

		/** @var GenerateModel $generate */
		$generate = $this->getModel('Generate');

		try {
			$definition = $generators->definition($id);
			$result     = $generate->generatePackage($definition);
		} catch (\Throwable $e) {
			// Everything that can go wrong here is a fact about the model -
			// a rule naming a template that is gone, a binding naming a
			// derivation the target dropped - and the person who can fix it is
			// the one looking at this screen. So it is said, rather than
			// logged.
			$application->enqueueMessage(
				Text::sprintf('COM_GENGEN_GENERATE_FAILED', $e->getMessage()),
				'error'
			);
			$application->redirect($back);

			return;
		}

		$application->enqueueMessage(
			Text::sprintf(
				'COM_GENGEN_GENERATE_DONE',
				$definition->name,
				\count($result['files']),
				str_replace(JPATH_ROOT, '', $result['path'])
			),
			'message'
		);

		$application->redirect($back);
	}
}
