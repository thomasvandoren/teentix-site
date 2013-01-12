<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/*
========================================================
Plugin Strip_HTML
--------------------------------------------------------
Copyright: Ingo Wedler
License: Freeware
http://ingowedler.com/
--------------------------------------------------------
This addon may be used free of charge. Should you
employ it in a commercial project of a customer or your
own I'd appreciate a small donation.
========================================================
File: pi.strip_html.php
--------------------------------------------------------
Purpose: Strips all HTML tags and gives you the option to preserve the ones you define. It also takes into account tags like <script> removing all the javascript, too! You can also strip out all the content between any tag that has an opening and closing tag, like <table>, <object>, etc.
========================================================
THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF
ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT
LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS
FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO
EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE
FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN
AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE
OR OTHER DEALINGS IN THE SOFTWARE.
========================================================
*/


$plugin_info = array(  'pi_name' => 'Strip_HTML',
	'pi_version' => '1.1.2',
	'pi_author' => 'Ingo Wedler',
	'pi_author_url' => 'http://ingowedler.com/',
	'pi_description' => 'Strips all HTML tags and gives you the option to preserve the ones you define. It also takes into account tags like < script > removing all the javascript, too! You can also strip out all the content between any tag that has an opening and closing tag, like < table >, < object >, etc.',
	'pi_usage' => strip_html::usage());

class Strip_HTML
{
	var $return_data;

	function Strip_HTML()
	{
		$this->EE =& get_instance();

		$text = $this->EE->TMPL->tagdata;

		$keep = $this->EE->TMPL->fetch_param('keep', '');
		$expand = $this->EE->TMPL->fetch_param('expand', '');


		/**///prep the string
		$text = ' ' . $text;

		/**///remove whitespaces
		$keep = preg_replace('/\s*(\S+)\s*/', '\\1', $keep);
		$expand = preg_replace('/\s*(\S+)\s*/', '\\1', $expand);

		/**///initialize keep tag logic
		if(strlen($keep) > 0){
			$k = explode('|',$keep);
			for($i=0;$i<count($k);$i++){
				$text = str_replace('<' . $k[$i],'[{(' . $k[$i],$text);
				$text = str_replace('</' . $k[$i],'[{(/' . $k[$i],$text);
			}
		}

		//begin removal
		/**///remove comment blocks
		while(stripos($text,'<!--') > 0){
			$pos[1] = stripos($text,'<!--');
			$pos[2] = stripos($text,'-->', $pos[1]);
			$len[1] = $pos[2] - $pos[1] + 3;
			$x = substr($text,$pos[1],$len[1]);
			$text = str_replace($x,'',$text);
		}

		/**///remove tags with content between them
		if(strlen($expand) > 0){
			$e = explode('|',$expand);
			for($i=0;$i<count($e);$i++){
				while(stripos($text,'<' . $e[$i]) > 0){
					$len[1] = strlen('<' . $e[$i]);
					$pos[1] = stripos($text,'<' . $e[$i]);
					$pos[2] = stripos($text,$e[$i] . '>', $pos[1] + $len[1]);
					$len[2] = $pos[2] - $pos[1] + $len[1];
					$x = substr($text,$pos[1],$len[2]);
					$text = str_replace($x,'',$text);
				}
			}
		}

		/**///remove remaining tags
		while(stripos($text,'<') > 0){
			$pos[1] = stripos($text,'<');
			$pos[2] = stripos($text,'>', $pos[1]);
			$len[1] = $pos[2] - $pos[1] + 1;
			$x = substr($text,$pos[1],$len[1]);
			$text = str_replace($x,'',$text);
		}

		/**///finalize keep tag
		if(strlen($keep) > 0){
			$k = explode('|',$keep);
			for($i=0;$i<count($k);$i++){
				$text = str_replace('[{(' . $k[$i],'<' . $k[$i],$text);
				$text = str_replace('[{(/' . $k[$i],'</' . $k[$i],$text);
			}		
		}

		$text = trim($text);

		$this->return_data = $text;
	}


	// ----------------------------------------
	// Plugin Usage
	// ----------------------------------------
	// This function describes how the plugin is used.
	// Make sure and use output buffering
	function usage()
	{
		ob_start();
		?>
Example:
----------------
{exp:strip_html}
{body}
{/exp:strip_html}


{exp:strip_html keep='b|em' expand='script|style|noframes|select|option'}
{body}
{/exp:strip_html}

----------------
CHANGELOG:

1.1.2
* Made compatible with NSM Addon Updater

1.1.1
* Minor tweaks

1.1
* Added the expand function

1.0
* 1st version for EE 2.x
		<?php
		$buffer = ob_get_contents();
		ob_end_clean();
		return $buffer;
	}
	  /* END */

}
/* END Class */
/* End of file pi.strip_html.php */
/* Location: ./system/expressionengine/third_party/strip_html/pi.strip_html.php */