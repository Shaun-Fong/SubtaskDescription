<?=
$this->form->label(t('Description'),'due_description');
?>
<?= 

$this->form->textEditor('due_description', $values, $errors);
?>