<?php

use cms_core\extensions\cms\Features;

$this->set([
	'page' => [
		'type' => 'multiple',
		'object' => $t('media')
	]
]);

?>
<article class="media-index">
	<section class="grid-row">
		<h1 class="h-gamma"><?= $t('Upload') ?></h2>
		<?= $this->form->create(null, ['type' => 'file']) ?>
			<?=  $this->form->field('form', [
				'type' => 'file',
				'label' => $t('(a) File (from computer)'),
				'value' => false
			]) ?>
			<?= $this->form->field('url', [
				'type' => 'text',
				'label' => $t('(b) File (URL)')
			]) ?>
			<?= $this->form->field('vimeo_id', [
				'type' => 'text',
				'label' => $t('(c) Vimeo ID')
			]) ?>
		<?= $this->form->button($t('upload'), ['type' => 'submit', 'class' => 'button large save']) ?>
		<?= $this->form->end() ?>
	</section>
	<section class="grid-row">
		<h1 class="h-gamma"><?= $t('Available Media') ?></h2>
		<?php if ($data->count()): ?>
			<table>
			<thead>
				<tr>
					<td><?= $t('Preview') ?>
					<td class="emphasize"><?= $t('Title') ?>
					<td><?= $t('# dependent') ?>
					<td class="actions">
			</thead>
			<tbody>
			<?php foreach ($data as $item): ?>
			<tr>
				<td>
					<?php if ($version = $item->version('fix3admin')): ?>
						<img src="<?= $version->url() ?>" />
					<?php endif ?>
				<td class="emphasize">
					<?= $item->title ?>
				<td>
					<?= ($depend = $item->depend('count')) ?: '–' ?>
				<td class="actions">
					<?php if (!$depend): ?>
						<?=$this->html->link($t('delete'), ['action' => 'delete', 'id' => $item->id, 'library' => 'cms_media'], ['class' => 'button delete']) ?>
					<?php endif ?>
					<?=$this->html->link($t('open'), ['action' => 'edit', 'id' => $item->id, 'library' => 'cms_media'], ['class' => 'button']) ?>
			<?php endforeach ?>
			</tbody>
			</table>
		<?php else: ?>
			<div class="none-available"><?= $t('There are currently no items available, yet.') ?></div>
		<?php endif ?>
	</section>
</article>