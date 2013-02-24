<?php

$edit = true;
?>
<?php if ($edit && ($files = $item->files()) && $files->count()): ?>
<ul class="files">
	<?php foreach ($files as $file): ?>
		<li>
			<?= $file->filename ?>
			<?= $this->html->image($file->versions('fix0')->url()); ?>
		</li>
	<?php endforeach ?>
</ul>
<?php endif ?>
<?=  $this->form->field('transfer', array(
	'type' => 'file',
	'label' => 'File',
	'value' => false
)); ?>
