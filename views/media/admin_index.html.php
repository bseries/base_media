<?php

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

	<?php if ($data->count()): ?>
		<table>
		<thead>
			<tr>
				<td class="media"><?= $t('Preview') ?>
				<td data-sort="title" class="emphasize table-sort"><?= $t('Title') ?>
				<td data-sort="type" class="table-sort"><?= $t('Type') ?>
				<td data-sort="mime-type" class="table-sort"><?= $t('MIME-Type') ?>
				<td><?= $t('Size') ?>
				<td><?= $t('# dependent') ?>
				<td data-sort="modified" class="date table-sort desc"><?= $t('Modified') ?>
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
			<td><?= $version ? $version->type : '–' ?>
			<td><?= $version ? $version->mime_type : '–' ?>
			<td><?= $this->number->format(round($item->size() / 1024), 'decimal') ?> kb
			<td><?= ($depend = $item->depend('count')) ?: '–' ?>
			<td class="date">
				<time datetime="<?= $this->date->format($item->modified, 'w3c') ?>">
					<?= $this->date->format($item->modified, 'date') ?>
				</time>
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

	<?=$this->view()->render(['element' => 'paging'], compact('paginator'), ['library' => 'base_core']) ?>
</article>