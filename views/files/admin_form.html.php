<?php

$this->set([
	'page' => [
		'type' => 'single',
		'title' => $item->title,
		'empty' => $t('untitled'),
		'object' => $t('file')
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
			<h1 class="h-gamma"><?= $t('Versions') ?></h1>
			<table>
				<thead>
					<tr>
						<td><?= $t('Version') ?>
						<td><?= $t('URL') ?>
						<td><?= $t('Status') ?>
						<td><?= $t('MIME-Type') ?>
						<td class="actions">
				</thead>
			<?php foreach ($item->versions() as $version): ?>
				<tr>
					<td class="emphasize"><?= $version->version ?>
					<td><?= $version->url ?>
					<td><?= $version->status ?>
					<td><?= $version->mime_type ?>
					<td class="actions">
						<?php if ($version->url): ?>
							<?= $this->html->link($t('open'), $this->media->url($version), ['class' => 'button']) ?>
						<?php endif ?>
			<?php endforeach ?>
			</table>
		</div>

		<div class="bottom-actions">
			<?= $this->form->button($t('save'), ['type' => 'submit', 'class' => 'button large save']) ?>
		</div>
	<?=$this->form->end(); ?>
</article>