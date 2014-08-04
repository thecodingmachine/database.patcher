<?php /* @var $this Mouf\Database\Patcher\Controllers\DatabasePatchController */ ?>
<style>
.sqlcode {
	font-family: Courier New, Courier;
}
</style>

<h1>Register/edit a SQL patch</h1>

<p>Use SQL patches to alter the database schema. By registering your patch, you can easily play the patch on other environments. For instance,
you can register the patch on your development environment and play the patch back on the production server.</p>

<?php if ($this->patchInstanceName): ?>
<div class="alert"><strong>Warning!</strong> You are going to edit an SQL patch. Be sure that the patch has not been shared with anyone 
(and that it was not run previously). Indeed, executed patches are not executed again after edition. If your patch has already been shared 
and executed by others, we advise you to add an additional patch instead of editing this one (unless this patch is obviously broken).</div>
<?php endif; ?>

<form action="save" method="post" class="form-horizontal">
<input type="hidden" id="name" name="name" value="<?php echo plainstring_to_htmlprotected($this->instanceName) ?>" />
<input type="hidden" id="patchInstanceName" name="patchInstanceName" value="<?php echo plainstring_to_htmlprotected($this->patchInstanceName) ?>" />
<input type="hidden" id="oldUniqueName" name="oldUniqueName" value="<?php echo plainstring_to_htmlprotected($this->oldUniqueName) ?>" />
<input type="hidden" id="selfedit" name="selfedit" value="<?php echo plainstring_to_htmlprotected($this->selfedit) ?>" />

<div class="control-group">
	<label class="control-label">Patch unique name:</label>
	<div class="controls">
		<input type="text" name="uniqueName" value="<?php echo plainstring_to_htmlprotected($this->uniqueName) ?>" class="input-xxlarge"></input>
		<span class="help-block">Each patch must have a unique name. Try to peek a meaningful one.</span>
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
	<label class="control-label">SQL to be applied:</label>
	<div class="controls">
		<textarea name="upSql" class="input-xxlarge sqlcode" rows="10" wrap="off"><?php echo plainstring_to_htmlprotected($this->upSql) ?></textarea>
		<span class="help-block">Copy and paste the SQL to be applied for your patch.</span>
	</div>
</div>

<div class="control-group">
	<label class="control-label">Status:</label>
	<div class="controls">
		<label>
		<input type="radio" name="status" value="skipped" <?php if ($this->status == "skipped") { echo "checked='checked'"; } ?>></input>
		The patch has already been executed on my database. Mark it as <span class="label label-info">skipped</span>.
		</label>
		<label>
		<input type="radio" name="status" value="saveandexecute" <?php if ($this->status == "saveandexecute") { echo "checked='checked'"; } ?>></input>
		The patch has not yet been executed on my database. <strong>Save and</strong> <span class="label label-success">apply</span> the patch.
		</label>
		<label>
		<input type="radio" name="status" value="awaiting" <?php if ($this->status == "awaiting") { echo "checked='checked'"; } ?>></input>
		The patch has not yet been executed on my database. <strong>Save</strong> but <span class="label">do not apply</span> the patch. Mark the patch "<strong>awaiting execution</strong>".
		</label>
	</div>
</div>


<div class="control-group">
	<div class="controls">
		<button id="moreoptionsbutton" value="generate" type="button" class="btn btn-info">More options</button>
	</div>
</div>

<div id="additionaloptions" style="display:none">
<div class="control-group">
	<label class="control-label">File name:</label>
	<div class="controls">
		<input type="text" name="upSqlFileName" value="<?php echo plainstring_to_htmlprotected($this->upSqlFileName) ?>" class="input-xxlarge"></input>
		<span class="help-block">The file containing the SQL patch (relative to ROOT_PATH).</span>
	</div>
</div>

<div class="control-group">
	<label class="control-label">Revert SQL:</label>
	<div class="controls">
		<textarea name="downSql" class="input-xxlarge sqlcode" rows="6" wrap="off"><?php echo plainstring_to_htmlprotected($this->downSql) ?></textarea>
		<span class="help-block">SQL to revert the patch. Keep this empty if this patch cannot be reverted.</span>
	</div>
</div>

<div class="control-group">
	<label class="control-label">Revert file name:</label>
	<div class="controls">
		<input type="text" name="downSqlFileName" value="<?php echo plainstring_to_htmlprotected($this->downSqlFileName) ?>" class="input-xxlarge"></input>
		<span class="help-block">The file containing the revert SQL patch (relative to ROOT_PATH).</span>
	</div>
</div>
</div>

<div class="form-actions">
	<button type="submit" name="action" value="save" class="btn btn-primary">Save</button>
	<?php if ($this->patchInstanceName): ?>
	<button type="submit" name="action" value="delete" class="btn btn-danger" id="buttondelete">Delete</button>
	<?php endif; ?>
</div>


</form>

<script type="text/javascript">
$(function() {
	$("#moreoptionsbutton").click(function() {
		$("#moreoptionsbutton").hide();
		$("#additionaloptions").show();
	});

	var uniqueName = $('input[name=uniqueName]').val();

    // Removes the first word of the unique name (it is the unique ID and we generally want it in MoufComponents but not in the database/up directory).
    var removeFirstWord = function(str) {
        var baseNameArr = str.split('-')
        baseNameArr.shift();
        return baseNameArr.join('-');
    }

	$('input[name=uniqueName]').keyup(function() {
		var newUniqueName = $('input[name=uniqueName]').val();
		var upSqlFileName = $('input[name=upSqlFileName]').val();
		var downSqlFileName = $('input[name=downSqlFileName]').val();


		if (upSqlFileName == "database/up/"+removeFirstWord(uniqueName)+".sql") {
			$('input[name=upSqlFileName]').val("database/up/"+removeFirstWord(newUniqueName)+".sql");
		}
		
		if (downSqlFileName == "database/down/"+removeFirstWord(uniqueName)+".sql") {
			$('input[name=downSqlFileName]').val("database/down/"+removeFirstWord(newUniqueName)+".sql");
		}
		
		uniqueName = newUniqueName;
	});

	$('#buttondelete').click(function() {
		return confirm("Warning! You are about to delete a patch. Are you sure? (SQL files will not be deleted, you can remove those manually)");
	});
});
</script>