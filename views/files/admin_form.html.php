<?php

$title = [
	'action' => ucfirst($this->_request->action === 'add' ? $t('creating') : $t('editing')),
	'title' => $item->title ?: $t('untitled'),
	'object' => [ucfirst($t('file')), ucfirst($t('files'))]
];
$this->title("{$title['title']} - {$title['object'][1]}");

$edit = $this->request()->params['action'] == 'edit';

?>
<article>
	<h1 class="alpha">
		<span class="action"><?= $title['action'] ?></span>
		<span class="title"><?= $title['title'] ?></span>
	</h1>

	<?=$this->form->create($item) ?>
		<img src="<?= $item->version('fix2')->url() ?>" class="media image"/>

		<?php if ($edit): ?>
			<?=$this->form->field('id', ['type' => 'hidden']) ?>
		<?php endif ?>
		<?=$this->form->field('title') ?>
		<?= $this->form->button($t('save'), ['type' => 'submit', 'class' => 'butto large']) ?>
	<?=$this->form->end(); ?>
</article>