<?php

use lithium\g11n\Message;

$t = function($message, array $options = []) {
	return Message::translate($message, $options + ['scope' => 'base_media', 'default' => $message]);
};

$this->set([
	'page' => [
		'type' => 'single',
		'title' => $item->title,
		'empty' => $t('untitled'),
		'object' => $t('media')
	]
]);

$edit = $this->request()->params['action'] == 'edit';

?>
<article>
	<?=$this->form->create($item) ?>
		<?=$this->form->field('id', ['type' => 'hidden']) ?>

		<?php if ($useOwner): ?>
			<div class="grid-row">
				<h1><?= $t('Access') ?></h1>

				<div class="grid-column-left"></div>
				<div class="grid-column-right">
					<?= $this->form->field('owner_id', [
						'type' => 'select',
						'label' => $t('Owner'),
						'list' => $users
					]) ?>
				</div>
			</div>
		<?php endif ?>


		<div class="grid-row">
			<h1 class="h-gamma">
				<?= $t('Original') ?>
			</h1>
			<div class="grid-column-left">
			<?php if ($item->hasVersion('fix2admin')): ?>
				<?= $this->media->image($item->version('fix2admin')) ?>
			<?php else: ?>
				<div class="none-available"><?= $t('No preview available.') ?></div>
			<?php endif ?>
			</div>
			<div class="grid-column-right">
				<?=$this->form->field('title', ['class' => 'use-for-title']) ?>

				<?=$this->form->field('type', [
					'label' => $t('Type'),
					'value' => $item->type,
					'disabled' => true
				]) ?>

				<?=$this->form->field('mime_type', [
					'label' => $t('MIME-Type'),
					'value' => $item->mime_type,
					'disabled' => true
				]) ?>

				<?=$this->form->field('size', [
					'label' => $t('Size'),
					'value' => $this->number->format(round($item->size() / 1024), 'decimal') . ' KB',
					'disabled' => true
				]) ?>

			</div>
		</div>

		<div class="grid-row">
			<?php $versions = $item->versions() ?>
			<h1 class="h-gamma">
				<?= $t('Versions') ?>
				<span class="count"><?= $versions->count() ?></span>
			</h1>
			<table>
				<thead>
					<tr>
						<td><?= $t('Version') ?>
						<td><?= $t('URL') ?>
						<td><?= $t('Type') ?>
						<td><?= $t('MIME-Type') ?>
						<td><?= $t('Size') ?>
						<td><?= $t('Status') ?>
						<td class="actions">
				</thead>
			<?php foreach ($versions as $version): ?>
				<tr>
					<td class="emphasize"><?= $version->version ?>
					<td><?= $version->url ?>
					<td><?= $version->type ?>
					<td><?= $version->mime_type ?>
					<td><?= $this->number->format(round($version->size() / 1024), 'decimal') ?> kb
					<td><?= $version->status ?>
					<td class="actions">
						<?= $this->html->link($t('open'), $this->media->url($version), ['class' => 'button']) ?>

			<?php endforeach ?>
			</table>
		</div>

		<div class="bottom-actions">
			<div class="bottom-actions__left">
				<?php if ($item->exists()): ?>
					<?= $this->html->link($t('delete'), [
						'action' => 'delete', 'id' => $item->id
					], ['class' => 'button large delete']) ?>
				<?php endif ?>
			</div>
			<div class="bottom-actions__right">
				<?php if ($item->exists()): ?>
					<?= $this->html->link($t('regenerate versions'), ['action' => 'regenerate_versions', 'id' => $item->id], ['class' => 'button large']) ?>
				<?php endif ?>

				<?= $this->form->button($t('save'), [
					'type' => 'submit',
					'class' => 'button large save'
				]) ?>
			</div>
		</div>

	<?=$this->form->end(); ?>
</article>