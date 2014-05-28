<?php

$edit = true;
?>
<?php if ($edit && ($files = $item->files()) && $files->count()): ?>
<ul class="files">
	<?php foreach ($files as $file): ?>
		<li>
			<?= $file->filename ?>
			<?= $this->html->image($file->versions('fix2admin')->url()); ?>
		</li>
	<?php endforeach ?>
</ul>
<?php endif ?>
<?=  $this->form->field('transfer', [
	'type' => 'file',
	'label' => 'File',
	'value' => false
]); ?>