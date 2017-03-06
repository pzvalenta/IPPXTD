#!/usr/bin/php
<?php

loadSettings($argv);

function loadSettings($argv){
    $options = "abg";
    $longopts = array(
      "help",
      "input::",
      "output::",
      "header::",
      "etc::",
    );

    unset($argv[0]); //zbavime se prvniho argumentu - jmena skriptu
    $opts = getopt($options, $longopts);

    $all = getopt(implode('', array_merge(range('a', 'z'), range('A', 'Z'), range('0', '9'))));
    $wrongOptions = array_diff(array_keys($all), array_keys($opts));
    if (!empty($wrongOptions)) {
      echo("Wrong options: " . implode(', ', $wrongOptions));
      exit(1);
    }

    var_dump($opts);
}


?>
