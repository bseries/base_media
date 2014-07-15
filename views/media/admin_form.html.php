<?php

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

		<div class="grid-row">
			<div class="grid-column-left">
				<?= $this->media->image($item->version('fix2admin')) ?>
			</div>
			<div class="grid-column-right">
				<?=$this->form->field('title', ['class' => 'use-for-title']) ?>
			</div>
		</div>

		<div class="grid-row grid-row-last">
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
			<?= $this->form->button($t('save'), ['type' => 'submit', 'class' => 'button large save']) ?>
		</div>
	<?=$this->form->end(); ?>
</article>