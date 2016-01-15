<?php 
 $base_url = BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=webservice'.AMP.'method=clear_cache';
?>

<div class="clear_left">&nbsp;</div>



	<p><strong><?=lang('webservice_delete_cache_check')?></strong></p>
	<p class="notice"><?=lang('webservice_delete_check_notice')?></p>

	<a href="<?=$base_url?>&clear=yes" class="submit"><?=lang('delete')?></a>
	</p>
