<?php

/**
 * @package     Gengen
 * @subpackage  Controller
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Component\Gengen\Administrator\Controller;

use Joomla\CMS\Access\Exception\NotAllowed;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\CMS\Router\Route;
use Yepr\Component\Gengen\Administrator\Model\ImportModel;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The list: publish, unpublish, delete, reorder, and import.
 *
 * @since  0.1.0
 */
class GeneratorsController extends AdminController
{
	/**
	 * The model one row of this list is.
	 *
	 * @param   string                $name    The model name.
	 * @param   string                $prefix  The class prefix.
	 * @param   array<string, mixed>  $config  Configuration array.
	 *
	 * @return  \Joomla\CMS\MVC\Model\BaseDatabaseModel
	 *
	 * @since   0.1.0
	 */
	public function getModel($name = 'Generator', $prefix = 'Administrator', $config = ['ignore_request' => true])
	{
		return parent::getModel($name, $prefix, $config);
	}

	/**
	 * Import an uploaded generator package as a model to edit.
	 *
	 * The same gesture as importing a metalanguage, and the same shape: the
	 * token, the permission, the upload read where PHP put it, and nothing
	 * written until the package has been read and checked. Creating a
	 * generator is what this does, so it asks for `core.create`, the
	 * permission the New button asks for.
	 *
	 * @return  void
	 *
	 * @since   0.6.0
	 */
	public function import(): void
	{
		$this->checkToken();

		$app = $this->app;

		if (!$app->getIdentity()->authorise('core.create', 'com_gengen')) {
			throw new NotAllowed(Text::_('JERROR_ALERTNOAUTHOR'), 403);
		}

		$this->setRedirect(Route::_('index.php?option=com_gengen&view=generators', false));

		$file = $app->getInput()->files->get('package', null, 'raw');

		if (!\is_array($file) || ($file['error'] ?? \UPLOAD_ERR_NO_FILE) !== \UPLOAD_ERR_OK) {
			$app->enqueueMessage(Text::_('COM_GENGEN_GENERATOR_IMPORT_NO_FILE'), 'error');

			return;
		}

		/** @var ImportModel $model */
		$model = $this->getModel('Import', 'Administrator', []);

		try {
			$result = $model->import((string) $file['tmp_name']);
		} catch (\RuntimeException $e) {
			// The reader and the check say what is wrong in a sentence a person
			// can act on, the way the metalanguage import does.
			$app->enqueueMessage($e->getMessage(), 'error');

			return;
		}

		$app->enqueueMessage(
			Text::sprintf(
				$result['replaced'] ? 'COM_GENGEN_GENERATOR_IMPORT_REPLACED' : 'COM_GENGEN_GENERATOR_IMPORTED',
				$result['name'],
				$result['rules'],
				$result['id']
			),
			'message'
		);

		if ($result['bound'] !== '') {
			$app->enqueueMessage(Text::sprintf('COM_GENGEN_GENERATOR_IMPORT_BOUND', $result['bound']), 'message');
		}

		if ($result['unboundLanguage'] !== '') {
			$app->enqueueMessage(
				Text::sprintf('COM_GENGEN_GENERATOR_IMPORT_LANGUAGE_MISSING', $result['unboundLanguage']),
				'warning'
			);
		}

		if ($result['extends'] !== '') {
			$app->enqueueMessage(Text::sprintf('COM_GENGEN_GENERATOR_IMPORT_EXTENDS', $result['extends']), 'notice');
		}

		foreach ($result['notes'] as $note) {
			$app->enqueueMessage($note, 'warning');
		}
	}
}
