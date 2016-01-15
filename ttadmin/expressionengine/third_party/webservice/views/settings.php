<div class="clear_left">&nbsp;</div>
<div id="save_settings">
	<?=form_open('C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module='.WEBSERVICE_MAP.AMP.'method=settings')?>

	<?php

	foreach ($settings as $label => $v)
	{
		$this->table->set_template($cp_pad_table_template);
		$this->table->set_heading(
			array('data' => lang(WEBSERVICE_MAP.'_'.$label), 'style' => 'width:25%;'),
			lang(WEBSERVICE_MAP.'_setting')
		);

		foreach ($v as $key => $val)
		{
			//subtext
			$subtext = '';
			$extra_html = '';
			if(is_array($val))
			{
				$subtext = isset($val[1]) ? '<div class="subtext">'.$val[1].'</div>' : '' ;
				$extra_html = isset($val[2]) ? '<div class="extra_html">'.$val[2].'</div>' : '' ;
				$val = $val[0];
			}
			$this->table->add_row(lang($key, $key).$subtext, $val.$extra_html);
		}
		echo $this->table->generate();
		$this->table->clear();
	}

	?>


	<p><?=form_submit('submit', lang('submit'), 'class="submit"')?></p>
	<?php $this->table->clear()?>
	<?=form_close()?>
</div>
