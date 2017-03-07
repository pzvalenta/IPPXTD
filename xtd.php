#!/usr/bin/php
<?php


// globalni promenne
$columns;
$group;
$relations;
$input_stream;
$output_stream;
$header_text;
$columns_max;




get_opts($argv);

function get_opts($argv){
    $options = "a";  // negenerovat sloupce z atributu     - $columns
    $options .= "b"; // sloucit podelementy stejneho nazvu - $group
    $options .= "g"; // vygenerovat xml s relacemi         - $relations

    $longopts = array(
      "help",        // vypsat help
      "input:",      // vstupni soubor                     - $input_file
      "output:",     // vystupni soubor                    - $output_file
      "header:",     // text hlavicky                      - $header_text
      "etc:",        // maximalni pocet sloupcu            - $columns_max
    );

    $opts = getopt($options, $longopts);


    global $columns, $group, $relations, $input_stream, $output_stream, $header_text, $columns_max;
    $columns = $group = $relations = 0;
    $columns_max = -1;

    $input_file = $output_file = "";

    foreach($opts as $key => $value){
      switch ($key){
          case "a":
              if($value === FALSE) $columns = 1;
              break;

          case "b":
              if($value === FALSE) $group = 1;
              break;

          case "g":
              if($value === FALSE) $relations = 1;
              break;

          case "help":
              if($value === FALSE) print_help();
              break;

          case "input":
              $input_file = $value;
              break;

          case "output":
              $output_file = $value;
              break;

          case "header":
              $header_text = $value;
              break;

          case "etc":
              $columns_max = intval($value);
              if(!is_int($columns_max)) print_error("--etc value is not int" , 1);
              break;

          default:
              print_error("unknown parameter: " . $key, 1);
              break;
      }
    }

    // overime konflikt argumentu
    if($columns_max && $group) print_error("--etc cannot be set alongside -b", 1);

    // overime vstupni a vystupni soubory
    if($input_file){
      if(!is_readable($input_file)) print_error("input file not readable", 2);
      if(!file_exists($input_file)) print_error("input file nonexistent", 2);
      if(($input_stream = fopen($input_file, "r")) === false) print_error("can't open input file", 2);
    } else {
      $input_stream = STDIN;
    }

    if($output_file){
      if($input_file && ($input_file === $output_file)) print_error("ouput file identical with input file", 2);
      if(($output_stream = fopen($output_file, "w")) === false) print_error("can't open output file", 2);
    } else {
      $output_stream = STDOUT;
    }
}



function print_help(){
  echo("HELP,\nTODO TODO TODO\nTODO TODO TODO \nTODO TODO TODO\n");
  exit(0);
}

function print_error($text, $code){
  echo("ERROR " . $code . " : " . $text);
  exot($code);
}


?>
