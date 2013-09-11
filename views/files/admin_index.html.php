<article class="files-index">
	<h1>Files</h1>

	<h2>Upload</h2>
	<?= $this->form->create(null, ['type' => 'file']) ?>
		<?=  $this->form->field('transfer.form', [
			'type' => 'file',
			'label' => '(a) File (from computer)',
			'value' => false
		]) ?>
		<?= $this->form->field('transfer.url', [
			'type' => 'text',
			'label' => '(b) File (URL)'
		]) ?>
	<?= $this->form->submit('Upload') ?>
	<?= $this->form->end() ?>

	<h2>Available Files</h2>
	<?php if ($data->count()): ?>
		<table>
		<?php foreach ($data as $item): ?>
		<tr>
			<td>
				<?php if ($version = $item->versions('fix0')): ?>
					<img src="<?= $version->url() ?>" />
				<?php endif ?>
			<td>
				<?= $item->_id ?>
			<td>
				<?= $item->filename ?>
			<td>
				<nav class="actions">
					<?=$this->html->link('edit', ['action' => 'edit', 'id' => $item->_id, 'library' => 'cms_media']) ?>
					<?=$this->html->link('delete', ['action' => 'delete', 'id' => $item->_id, 'library' => 'cms_media']) ?>
				</nav>
		<?php endforeach ?>
		</table>
	<?php else: ?>
		<div class="none-available">There are currently no files available, yet.</div>
	<?php endif ?>
</article>