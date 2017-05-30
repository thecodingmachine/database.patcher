<?php /* @var $this Mouf\Database\Patcher\Controllers\MigrationPatchController */ ?>

<h1>Create a migration class</h1>

<p>Use migration class to alter the database schema using PHP code stored in a PHP class.</p>

<form action="save" method="post" class="form-horizontal">
<input type="hidden" id="name" name="name" value="<?php echo plainstring_to_htmlprotected($this->instanceName) ?>" />
<input type="hidden" id="selfedit" name="selfedit" value="<?php echo plainstring_to_htmlprotected($this->selfedit) ?>" />

<div class="control-group">
    <label class="control-label">Class name:</label>
    <div class="controls">
        <input type="text" name="className" value="<?php echo plainstring_to_htmlprotected($this->patchClassName) ?>" class="input-xxlarge"></input>
        <span class="help-block">The fully qualified name of the class that will be generated. It must be autoloadable by Composer.</span>
    </div>
</div>

<div class="control-group">
    <label class="control-label">This patch will:</label>
    <div class="controls">
        <label class="radio">
            <input type="radio" name="purpose" id="purpose_model" value="model" checked>
            Modify the model/structure of the database
        </label>
        <label class="radio">
            <input type="radio" name="purpose" id="purpose_data" value="data">
            Add/remove data in the database
        </label>
    </div>
</div>

<div class="control-group">
	<label class="control-label">Description:</label>
	<div class="controls">
		<textarea name="description" class="input-xxlarge"><?php echo plainstring_to_htmlprotected($this->description); ?></textarea>
		<span class="help-block">A short description of your patch.</span>
	</div>
</div>

<div class="control-group">
    <label class="control-label">Patch type:</label>
    <div class="controls">
        <select name="type" class="input-xxlarge">
<?php foreach ($this->types as $type): ?>
            <option value="<?php echo $type['instanceName'] ?>" <?php if ($this->selectedType === $type['instanceName']) { echo "selected"; } ?>><?php echo $type['name'] ?: '(default)' ?></option>
<?php endforeach; ?>
        </select>
        <span class="help-block help-patch-type"></span>
    </div>
</div>


<div class="form-actions">
	<button type="submit" name="action" value="generate" class="btn btn-primary">Generate</button>
</div>


</form>
