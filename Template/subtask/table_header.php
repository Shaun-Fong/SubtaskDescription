<th>
	<?php if (isset($paginator)): ?>
	    <?= $paginator->order(t('Description'), \Kanboard\Model\SubtaskModel::TABLE.'.due_description') ?>
	<?php else: ?>
		<?= t('Description') ?>
	<?php endif ?>
</th>

