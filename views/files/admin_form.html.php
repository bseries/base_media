 <?php

 $edit = $this->request()->params['action'] == 'edit';

?>
<article>
	<h1><?=$this->title('File') ?></h1>

	<?=$this->form->create($item) ?>
		<img src="<?= $item->versions('fix0')->url() ?>" />

		<?php if ($edit): ?>
			<?=$this->form->field('_id', ['type' => 'hidden']) ?>
		<?php endif ?>
		<?=$this->form->field('filename') ?>
		<?=$this->form->submit('Save')  ?>
	<?=$this->form->end(); ?>
</article>