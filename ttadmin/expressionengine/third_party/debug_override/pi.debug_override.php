<?php

$plugin_info = array(
                     'pi_name' => 'debug_override',
                     'pi_version' =>'1.0',
                     'pi_author' =>'GDmac',
                     'pi_author_url' => '',
                     'pi_description' => '',
                     'pi_usage' => '{exp:debug_override override="all|ajax"} default is override on ajax calls',
                     );

class debug_override {
  public function __construct()
  {
    $this->EE =& get_instance();
    $override = $this->EE->TMPL->fetch_param('override', 'ajax');
    
    // no debugging or output-profiler for ajax
    if( ($override=='all') || ($override=='ajax' && AJAX_REQUEST))
      {
        $this->EE->TMPL->debugging = FALSE;
        $this->EE->output->enable_profiler(FALSE);
      }
  }
}
