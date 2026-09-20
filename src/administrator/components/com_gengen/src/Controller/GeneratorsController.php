<?php

/**
 * @package     Gengen
 * @subpackage  Controller
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Component\Gengen\Administrator\Controller;

use Joomla\CMS\MVC\Controller\AdminController;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The list: publish, unpublish, delete, reorder.
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
}
