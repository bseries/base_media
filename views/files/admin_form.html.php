 <?php

 $edit = $this->request()->params['action'] == 'edit';

?>
<article>
	<h1 class="alpha"><?=$this->title($t('File')) ?></h1>

	<?=$this->form->create($item) ?>
		<img src="<?= $item->version('fix0')->url() ?>" class="media image"/>

		<?php if ($edit): ?>
			<?=$this->form->field('id', ['type' => 'hidden']) ?>
		<?php endif ?>
		<?=$this->form->field('title') ?>
		<?= $this->form->button($t('save'), ['type' => 'submit']) ?>
	<?=$this->form->end(); ?>
</article>