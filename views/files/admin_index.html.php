<?php

use cms_core\extensions\cms\Features;

?>
<article class="files-index">
	<h1 class="alpha"><?= $this->title($t('Files')) ?></h1>

	<section>
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
		<?= $this->form->button($t('upload'), ['type' => 'submit']) ?>
		<?= $this->form->end() ?>
	</section>
	<section>
		<h2 class="beta"><?= $t('Available Files') ?></h2>
		<?php if ($data->count()): ?>
			<table>
			<thead>
				<tr>
					<td><?= $t('Preview') ?>
					<td class="emphasize"><?= $t('Title') ?>
					<td><?= $t('# dependent') ?>
					<td class="actions">
			<tbody>
			<?php foreach ($data as $item): ?>
			<tr>
				<td>
					<?php if ($version = $item->version('fix3')): ?>
						<img src="<?= $version->url() ?>" />
					<?php endif ?>
				<td class="emphasize">
					<?= $item->title ?>
				<td>
					<?= count($item->depend()) ?: '–' ?>
				<td class="actions">
					<?=$this->html->link($t('delete'), ['action' => 'delete', 'id' => $item->id, 'library' => 'cms_media'], ['class' => 'button']) ?>
					<?=$this->html->link($t('edit'), ['action' => 'edit', 'id' => $item->id, 'library' => 'cms_media'], ['class' => 'button']) ?>
			<?php endforeach ?>
			</tbody>
			</table>
		<?php else: ?>
			<div class="none-available"><?= $t('There are currently no files available, yet.') ?></div>
		<?php endif ?>
	</section>
</article>