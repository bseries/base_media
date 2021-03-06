<?php

use base_core\security\Gate;
use lithium\g11n\Message;

$t = function($message, array $options = []) {
	return Message::translate($message, $options + ['scope' => 'base_media', 'default' => $message]);
};

$this->set([
	'page' => [
		'type' => 'multiple',
		'object' => $t('media')
	]
]);

?>
<article
	class="use-rich-index"
	data-endpoint="<?= $this->url([
		'action' => 'index',
		'page' => '__PAGE__',
		'orderField' => '__ORDER_FIELD__',
		'orderDirection' => '__ORDER_DIRECTION__',
		'filter' => '__FILTER__'
	]) ?>"
>

	<div class="top-actions">
		<?php if (Gate::checkRight('clean')): ?>
			<?= $this->html->link(
				$t('delete all unused media files'),
				['action' => 'clean'],
				['class' => 'button delete']
			) ?>
		<?php endif ?>
	</div>

	<?php if ($data->count()): ?>
		<table>
		<thead>
			<tr>
				<td class="media"><?= $t('Preview') ?>
				<td data-sort="title" class="emphasize table-sort"><?= $t('Title') ?>
				<td data-sort="type" class="table-sort"><?= $t('Type') ?>
				<td><?= $t('# dependent') ?>
				<td data-sort="modified" class="date table-sort desc"><?= $t('Modified') ?>
				<?php if ($useOwner): ?>
					<td class="user"><?= $t('Owner') ?>
				<?php endif ?>
				<td class="actions">
					<?= $this->form->field('search', [
						'type' => 'search',
						'label' => false,
						'placeholder' => $t('Filter'),
						'class' => 'table-search',
						'value' => $this->_request->filter
					]) ?>
		</thead>
		<tbody>
		<?php foreach ($data as $item): ?>
		<tr>
			<td class="media">
				<?php
					try {
						if ($version = $item->version('fix3admin')) {
							echo $this->media->image($version, [ 'data-media-id' => $item->id, 'alt' => 'preview' ]);
						}
					} catch (\Exception $e) {
						$version = null;
					}
				?>
			<td class="emphasize">
				<?= $item->title ?>
			<td><?= $item->type ?: '–' ?>
			<td><?= ($depend = $item->depend('count')) ?: '–' ?>
			<td class="date">
				<time datetime="<?= $this->date->format($item->modified, 'w3c') ?>">
					<?= $this->date->format($item->modified, 'date') ?>
				</time>
			<?php if ($useOwner): ?>
				<td class="user">
					<?= $this->user->link($item->owner()) ?>
			<?php endif ?>
			<td class="actions">
				<?php if (!$depend): ?>
					<?=$this->html->link($t('delete'), ['action' => 'delete', 'id' => $item->id, 'library' => 'base_media'], ['class' => 'button delete']) ?>
				<?php endif ?>
				<?=$this->html->link($t('open'), ['action' => 'edit', 'id' => $item->id, 'library' => 'base_media'], ['class' => 'button']) ?>
		<?php endforeach ?>
		</tbody>
		</table>
	<?php else: ?>
		<div class="none-available"><?= $t('There are currently no items available, yet.') ?></div>
	<?php endif ?>

	<?=$this->_render('element', 'paging', compact('paginator'), ['library' => 'base_core']) ?>
</article>