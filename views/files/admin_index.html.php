<article class="files-index">
	<h1 class="alpha"><?= $t('Files') ?></h1>

	<nav class="actions">
		<?= $this->html->link($t('regenerate versions'), ['action' => 'regenerate_versions', 'library' => 'cms_media']) ?>
	</nav>

	<h2 class="beta"><?= $t('Upload') ?></h2>
	<?= $this->form->create(null, ['type' => 'file']) ?>
		<?=  $this->form->field('transfer.form', [
			'type' => 'file',
			'label' => $t('(a) File (from computer)'),
			'value' => false
		]) ?>
		<?= $this->form->field('transfer.url', [
			'type' => 'text',
			'label' => $t('(b) File (URL)')
		]) ?>
	<?= $this->form->submit($t('Upload')) ?>
	<?= $this->form->end() ?>

	<h2 class="beta"><?= $t('Available Files') ?></h2>
	<?php if ($data->count()): ?>
		<table>
		<?php foreach ($data as $item): ?>
		<tr>
			<td>
				<?php if ($version = $item->version('fix3')): ?>
					<img src="<?= $version->url() ?>" />
				<?php endif ?>
			<td>
				<?= $item->id ?>
			<td>
				<?= $item->title ?>
			<td>
				<nav class="actions">
					<?=$this->html->link($t('edit'), ['action' => 'edit', 'id' => $item->id, 'library' => 'cms_media']) ?>
					<?=$this->html->link($t('delete'), ['action' => 'delete', 'id' => $item->id, 'library' => 'cms_media']) ?>
				</nav>
		<?php endforeach ?>
		</table>
	<?php else: ?>
		<div class="none-available"><?= $t('There are currently no files available, yet.') ?></div>
	<?php endif ?>
</article>