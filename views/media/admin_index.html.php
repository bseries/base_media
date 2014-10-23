<?php

use base_core\extensions\cms\Features;

$this->set([
	'page' => [
		'type' => 'multiple',
		'object' => $t('media')
	]
]);

?>
<article class="media-index">
	<?php if ($data->count()): ?>
		<table>
		<thead>
			<tr>
				<td class="media"><?= $t('Preview') ?>
				<td class="emphasize"><?= $t('Title') ?>
				<td><?= $t('Type') ?>
				<td><?= $t('MIME-Type') ?>
				<td><?= $t('Size') ?>
				<td><?= $t('# dependent') ?>
				<td class="actions">
		</thead>
		<tbody>
		<?php foreach ($data as $item): ?>
		<tr>
			<td class="media">
				<?php if ($version = $item->version('fix3admin')): ?>
					<?= $this->media->image($version, [
						'data-media-id' => $item->id, 'alt' => 'preview'
					]) ?>
				<?php endif ?>
			<td class="emphasize">
				<?= $item->title ?>
			<td><?= $version ? $version->type : '–' ?>
			<td><?= $version ? $version->mime_type : '–' ?>
			<td><?= $this->number->format(round($item->size() / 1024), 'decimal') ?> kb
			<td><?= ($depend = $item->depend('count')) ?: '–' ?>
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
</article>