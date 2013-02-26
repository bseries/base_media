<article class="files-index">
	<h1>Files</h1>

	<h2>Upload</h2>
	<?= $this->form->create(null, array('type' => 'file')) ?>
		<?=  $this->form->field('transfer', array(
			'type' => 'file',
			'label' => 'File',
			'value' => false
		)) ?>
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
					<?=$this->html->link('edit', array('action' => 'edit', 'id' => $item->_id, 'library' => 'cms_media')) ?>
					<?=$this->html->link('delete', array('action' => 'delete', 'id' => $item->_id, 'library' => 'cms_media')) ?>
				</nav>
		<?php endforeach ?>
		</table>
	<?php else: ?>
		<div class="none-available">There are currently no files available, yet.</div>
	<?php endif ?>
</article>