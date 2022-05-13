<td>
    <?php if (! empty($subtask['due_description'])): ?>
        <?= $this->text->markdown($subtask['due_description']) ?>
    <?php endif ?>
</td>
