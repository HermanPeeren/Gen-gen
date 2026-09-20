<?php

/**
 * @package     Gengen
 * @subpackage  Layout
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \Yepr\Component\Gengen\Administrator\View\Generator\HtmlView $this */

$this->getDocument()->getWebAssetManager()
	->useScript('keepalive')
	->useScript('form.validate');
?>
<form action="<?php echo Route::_('index.php?option=com_gengen&layout=edit&id=' . (int) $this->item->id); ?>"
	method="post" name="adminForm" id="generator-form" class="form-validate">

	<?php echo HTMLHelper::_('uitab.startTabSet', 'generatorTab', ['active' => 'general', 'recall' => true]); ?>

	<?php echo HTMLHelper::_('uitab.addTab', 'generatorTab', 'general', Text::_('COM_GENGEN_TAB_GENERAL')); ?>
		<div class="row">
			<div class="col-lg-9">
				<?php echo $this->form->renderFieldset('general'); ?>
			</div>
		</div>
	<?php echo HTMLHelper::_('uitab.endTab'); ?>

	<?php echo HTMLHelper::_('uitab.addTab', 'generatorTab', 'rules', Text::_('COM_GENGEN_TAB_RULES')); ?>
		<div class="row">
			<div class="col-lg-12">
				<div class="alert alert-info">
					<?php echo Text::_('COM_GENGEN_RULES_ORDER_MATTERS'); ?>
				</div>
				<?php echo $this->form->renderFieldset('rules'); ?>
			</div>
		</div>
	<?php echo HTMLHelper::_('uitab.endTab'); ?>

	<?php echo HTMLHelper::_('uitab.endTabSet'); ?>

	<input type="hidden" name="task" value="">
	<?php echo HTMLHelper::_('form.token'); ?>
</form>
