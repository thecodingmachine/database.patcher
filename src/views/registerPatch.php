<?php /* @var $this Mouf\Utils\Patcher\Controllers\PatchController */ ?>
<h1>Patches list</h1>

<?php if (empty($this->patchesArray)): ?>
<div class="alert alert-info">No patches have been registered yet.</div>
<?php endif; ?>
