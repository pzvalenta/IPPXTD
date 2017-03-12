#!/usr/bin/php
<?php


// globalni promenne
$xml;
$columns;
$group;
$relations;
$output_stream;
$header_text;
$columns_max;

$types = array(
  "NA"  => 0,
  "BIT" => "BIT",
  "INT" => "INT",
  "FLOAT" => "FLOAT",
  "NVARCHAR" => "NVARCHAR",
  "NTEXT" => "NTEXT",
);





get_opts($argv);

echo("><><><><><><><><><><test1><><><><><><><><><><\n");
generate_unlimited();
echo("><><><><><><><><><><test2><><><><><><><><><><\n");

fclose($output_stream);
exit(0); //TODO

function generate_unlimited(){
  global $tables, $xml, $columns, $group, $relations, $output_stream, $header_text, $columns_max;

  $tables = array();
  recursive_print_children($xml, 0);

  print_tables($tables);
}


function recursive_print_children($parent, $depth){
  global $types, $tables;
  $new_table = new table;

  //////////////////////////// pomocny kod
  for($i = 0; $i < $depth; $i++){
    if($i === $depth - 1) echo("|___");
    else echo("\t");
  }
  echo($parent->getName());

  //if (get_type($parent) !== $types["NA"]){
  //  echo(" contents: " . trim($parent));
  //}

  echo(" ( ");
  foreach ($parent->attributes() as $attribute) {
    echo($attribute->getName()." ");
  }
  echo(")\n");
  ///////////////////////////

  if (get_type($parent) === $types["NA"]){ // nema textovy obsah
    foreach($parent->attributes() as $attribute){
      $type = get_type($attribute);
      if ($type === "NTEXT") $type = "NVARCHAR";
      if ($type === "NA") $type = "BIT";
      $new_table->addAttribute($attribute->getName(), $type);
    }

    foreach($parent->children() as $child){
      $new_table->addSubelement($child->getName(), "INT");
      recursive_print_children($child, $depth + 1);
    }
  } else {   // ma textovy obsah - nemusime resit podelementy
    $new_table->addAttribute("value", get_type($parent));
  }


  if (!isset($tables[$parent->getName()])){
    $new_table->setName($parent->getName());
    $tables[$parent->getName()] = $new_table;
  } else {
    // porovnat tabulky, sloucit je
    merge_tables($new_table, $tables[$parent->getName()]);
  }

}

function print_tables($tables){
  foreach ($tables as $table) {
    $table->print_table();
  }
}

function get_type($str){
  global $types;
  $str = mb_strtolower(trim($str), 'UTF-8');

  if ($str === '') return $types["NA"];
  if (is_bool(filter_var($str, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE))) return $types["BIT"];
  if (is_int(filter_var($str, FILTER_VALIDATE_INT))) return $types["INT"];
  if (is_float(filter_var($str, FILTER_VALIDATE_FLOAT))) return $types["FLOAT"];

  return $types["NTEXT"]; // pokud je to atribut, melo by to byt NVARCHAR
}

function compare_types($type1, $type2){
  if ($type1 === 0) return $type2;
  if ($type2 === 0) return $type1;

  if ($type1 === "BIT") return $type2;
  if ($type2 === "BIT") return $type1;

  if ($type1 === "INT") return $type2;
  if ($type2 === "INT") return $type1;

  if ($type1 === "FLOAT") return $type2;
  if ($type2 === "FLOAT") return $type1;

  if ($type1 === "NVARCHAR") return $type2;
  if ($type2 === "NVARCHAR") return $type1;

  if ($type1 === "NTEXT") return "NTEXT";
  if ($type2 === "NTEXT") return "NTEXT";

  print_error("fatal type comparison error", 100);
}


class table {
  public $name = "";
  public $subelements = array();
  public $attributes = array();
  public $primary_key;

  public function getName(){
    return $this->name;
  }

  public function setName($text){
    $this->name = $text;
    $this->primary_key = "prk_" . $text . "_id INT PRIMARY KEY";
  }

  public function addAttribute($name, $type){
    if (isset($this->attributes[$name . "_id"])) print_error("attribute name colision", 90);
    // TODO je toto chyba?
    $this->attributes[$name] = $type;
  }

  public function addSubelement($name, $type){
    if (isset($this->attributes[$name . "_id"])) print_error("attribute and subelemnt name colision", 90);

    if (isset($this->subelements[$name . "_id"])){
      $this->subelements[$name . "1_id"] = $this->subelements[$name . "_id"];
      unset($this->subelements[$name . "_id"]);
      $this->subelements[$name . "2_id"] = $type;
    } elseif (isset($this->subelements[$name . "1_id"])) {
      $i = 2;
      for($i; isset($this->subelements[$name . $i . "_id"]); $i++){}
      $this->subelements[$name . $i . "_id"] = $type;
    } else {
      $this->subelements[$name . "_id"] = $type;
    }

  }

  public function print_table(){
    echo("CREATE TABLE " . $this->name . "(\n");
    echo("\t$this->primary_key");
    foreach ($this->attributes as $name => $type) {
      echo(",\n");
      echo("\t" . $name . " " . $type);
    }

    foreach ($this->subelements as $name => $type) {
      echo(",\n");
      echo("\t" . $name . " " . $type);
    }

    echo("\n");
    echo(");\n\n");
  }
}

// sloucit tabulku1 do tabulky2
function merge_tables($table1, $table2){
  foreach ($table1->attributes as $name1 => $type1) {
    if (isset($table2->attributes[$name1])){
      if ($table2->attributes[$name1] !== $type1) {
        $table2->attributes[$name1] = compare_types($type1, $table2->attributes[$name1]);
      }
    } else {
      $table2->attributes[$name1] = $type1;
    }
  }

  foreach ($table1->subelements as $name1 => $type1) {
    if (isset($table2->subelements[$name1])){
      if ($table2->subelements[$name1] !== $type1) {
        $table2->subelements[$name1] = compare_types($type1, $table2->subelements[$name1]);
      }
    } else {
      $table2->subelements[$name1] = $type1;
    }
  }
}

function stronger_type($type1, $type2){
  return "PLACEHOLDER TYPE";
}


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


    global $xml, $columns, $group, $relations, $output_stream, $header_text, $columns_max;
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

    // overim konflikt argumentu
    if($columns_max && $group) print_error("--etc cannot be set alongside -b", 1);

    // overim vstupni soubor
    if($input_file){
      if(!is_readable($input_file)) print_error("input file not readable", 2);
      if(!file_exists($input_file)) print_error("input file nonexistent", 2);
      if(($input_stream = fopen($input_file, "r")) === false) print_error("can't open input file", 2);
      $xml = simplexml_load_string(stream_get_contents($input_stream));
    } else {
      $xml = simplexml_load_string(stream_get_contents(STDIN));
    }

    // overim vystupni soubor
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
  fwrite(STDERR, "ERROR " . $code . ": " . $text . "\n");
  exit($code);
}


?>
