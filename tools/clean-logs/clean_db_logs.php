<?php

$db_user = $argv[1];
$db_name = $argv[2];

$db_pass = readline('DB password: ');

$db = @mysql_connect('localhost', $db_user, $db_pass);

try {
  $db_selected = @mysql_select_db($db_name, $db);
  if (!$db_selected) {
    $msg = 'Failed to select MySQL db ' . $db_name;
    throw new Exception($msg);
  }

  $min_max_res = mysql_query('SELECT MIN(log_id) AS min_log_id, MAX(log_id) AS max_log_id FROM exp_webservice_logs;', $db);
  if (!$min_max_res) {
    $msg =  'MySQL error: ' . mysql_error($db);
    throw new Exception($msg);
  }
  $min_max_arr = mysql_fetch_array($min_max_res);

  $min_log_id = $min_max_arr['min_log_id'];
  $max_log_id = $min_max_arr['max_log_id'];

  $batch_size = 100;
  $batches = floor(($max_log_id+1 - $min_log_id) / $batch_size);

  for ($i = $min_log_id ; $i <= $max_log_id ; $i += $batch_size) {
    $del_res = mysql_query('DELETE FROM  exp_webservice_logs WHERE log_id >= '.$i.' AND log_id < '.($i+$batch_size).';', $db);
    if (!$del_res) {
      throw new Exception('MySQL failure: '.mysql_error($db));
    }
    $batch_no = floor(($i - $min_log_id) / $batch_size);
    if (floor($batch_no) % 10 == 0){
      echo $batch_no.'/'.$batches." batches complete\n";
    }
  }
} finally {
  @mysql_close($db);
}
