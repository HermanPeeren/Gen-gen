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
use Joomla\CMS\Session\Session;

/** @var \Yepr\Component\Gengen\Administrator\View\Generators\HtmlView $this */

$this->getDocument()->getWebAssetManager()->useScript('table.columns')->useScript('multiselect');
?>
<form action="<?php echo Route::_('index.php?option=com_gengen&view=generators'); ?>" method="post" name="adminForm" id="adminForm">
	<div class="row">
		<div class="col-md-12">
			<div id="j-main-container" class="j-main-container">
				<?php if ($this->targets === []) : ?>
					<div class="alert alert-warning">
						<h4><?php echo Text::_('COM_GENGEN_NO_TARGETS_HEADING'); ?></h4>
						<p><?php echo Text::_('COM_GENGEN_NO_TARGETS_BODY'); ?></p>
					</div>
				<?php endif; ?>

				<?php if (empty($this->items)) : ?>
					<div class="alert alert-info">
						<span class="icon-info-circle" aria-hidden="true"></span>
						<span class="visually-hidden"><?php echo Text::_('INFO'); ?></span>
						<?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?>
					</div>
				<?php else : ?>
					<table class="table" id="generatorList">
						<caption class="visually-hidden"><?php echo Text::_('COM_GENGEN_MANAGER_GENERATORS'); ?></caption>
						<thead>
							<tr>
								<td style="width:1%" class="text-center">
									<?php echo HTMLHelper::_('grid.checkall'); ?>
								</td>
								<th scope="col" style="width:1%" class="text-center">
									<?php echo Text::_('JSTATUS'); ?>
								</th>
								<th scope="col">
									<?php echo Text::_('COM_GENGEN_HEADING_NAME'); ?>
								</th>
								<th scope="col" style="width:15%" class="d-none d-md-table-cell">
									<?php echo Text::_('COM_GENGEN_HEADING_TARGET'); ?>
								</th>
								<th scope="col" style="width:10%" class="d-none d-md-table-cell">
									<?php echo Text::_('JGRID_HEADING_ID'); ?>
								</th>
							</tr>
						</thead>
						<tbody>
						<?php foreach ($this->items as $i => $item) : ?>
							<tr class="row<?php echo $i % 2; ?>">
								<td class="text-center">
									<?php echo HTMLHelper::_('grid.id', $i, $item->id, false, 'cid', 'cb', $item->name); ?>
								</td>
								<td class="text-center">
									<?php echo HTMLHelper::_('jgrid.published', $item->published, $i, 'generators.', true); ?>
								</td>
								<th scope="row">
									<a href="<?php echo Route::_('index.php?option=com_gengen&task=generator.edit&id=' . (int) $item->id); ?>">
										<?php echo $this->escape($item->name); ?>
									</a>
								</th>
								<td class="d-none d-md-table-cell">
									<code><?php echo $this->escape($item->target); ?></code>
								</td>
								<td class="d-none d-md-table-cell">
									<?php echo (int) $item->id; ?>
								</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>

					<?php echo $this->pagination->getListFooter(); ?>
				<?php endif; ?>

				<input type="hidden" name="task" value="">
				<input type="hidden" name="boxchecked" value="0">
				<?php echo HTMLHelper::_('form.token'); ?>
			</div>
		</div>
	</div>
</form>
