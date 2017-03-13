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
$elements = array();

get_opts($argv);
//echo("><><><><><><><><><><test1><><><><><><><><><><\n");
generate_unlimited();
//echo("><><><><><><><><><><test2><><><><><><><><><><\n");
fclose($output_stream);
exit(0); //TODO

function generate_unlimited(){
  global $elements, $xml, $columns, $group, $relations, $output_stream, $header_text, $columns_max;


  foreach ($xml->children() as $child) { // preskocim korenovy element
    recursive_load_elements($child, 0, "");
  }

  if($columns_max != -1) check_max($elements, $columns_max);

  print_tables($elements);
}



/*
function recursive_print_children($element, $depth, $parent){
  global $tables;
  $new_table = new table;

  //////////////////////////// pomocny kod
  /*
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
  */
  ///////////////////////////
/*
  if (!get_type($element)){ // nema textovy obsah
    foreach($element->attributes() as $attribute){
      $type = get_type($attribute);
      if ($type === "NTEXT") $type = "NVARCHAR";
      if ($type === "NA") $type = "BIT";
      $new_table->addAttribute($attribute->getName(), $type);
    }
    foreach($element->children() as $child){
      $new_table->addSubelement($child->getName(), "INT");
      recursive_print_children($child, $depth + 1);
    }
  } else {   // ma textovy obsah - nemusime resit podelementy
    $new_table->addAttribute("value", get_type($element));
  }


  if (!isset($tables[$element->getName()])){
    $new_table->setName($element->getName());
    $new_table->setParent($parent);
    $tables[$element->getName()] = $new_table;
  } else {
    // porovnat tabulky, sloucit je
    merge_tables($new_table, $tables[$element->getName()]);
  }

}
*/

function print_tables($elements){
  global $header_text, $output_stream;
  if ($header_text){
      fprintf($output_stream, "--" . $header_text . "\n\n");
  }

  foreach ($elements as $element) {
    $element->print_element();
  }
}

function get_type($str){
  $str = mb_strtolower(trim($str), 'UTF-8');

  if ($str === '') return 0;
  if (is_bool(filter_var($str, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE))) return "BIT";
  if (is_int(filter_var($str, FILTER_VALIDATE_INT))) return "INT";
  if (is_float(filter_var($str, FILTER_VALIDATE_FLOAT))) return "FLOAT";

  return "NTEXT"; // pokud je to atribut, melo by to byt NVARCHAR
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

function recursive_load_elements($element, $depth, $parent){
  global $elements;

  $new_element = new element;

  if (!get_type($element)){ // nema textovy obsah
    foreach($element->attributes() as $attribute){
      $type = get_type($attribute);
      if ($type === "NTEXT") $type = "NVARCHAR";
      if ($type === "NA") $type = "BIT";
      $new_element->add_attribute($attribute->getName(), $type);
    }
    foreach($element->children() as $child){
      $new_element->add_child($child->getName());
      recursive_load_elements($child, $depth + 1, $element->getName());
    }
  } else {   // ma textovy obsah - nemusime resit podelementy
    $new_element->set_type(get_type($element));
  }


  if (!isset($elements[$element->getName()])){
    $new_element->set_name($element->getName());
    $new_element->set_parent($parent);
    $elements[$element->getName()] = $new_element;
  } else {
    // porovnat tabulky, sloucit je
    merge_elements($new_element, $elements[$element->getName()]);
  }
}

class element {
  public $name = "";
  public $parent = "";
  public $children = array();
  public $attributes = array();
  public $type = "NA";

  // vrati name
  public function get_name(){
    return $this->name;
  }

  // nastavi name
  public function set_name($text){

    $this->name = mb_strtolower($text, 'UTF-8');
  }

  // vrati type
  public function get_type(){
    return $this->type;
  }

  // nastavi type
  public function set_type($text){
    if(isset($this->attributes["value"])){
      $this->type = compare_types($this->attributes["value"], $text);
      unset($this->attributes["value"]);
    } else {
      $this->type = $text;
    }
  }

  // vrati parent
  public function get_parent(){
    return $this->parent;
  }

  // nastavi parent
  public function set_parent($text){
    $this->parent = mb_strtolower($text, 'UTF-8');
  }

  // zkontroluje kolize a prida atribut
  public function add_attribute($name, $type){
    $name = mb_strtolower($name, 'UTF-8');
    if ($name === "value" && $this->type !== "NA"){
      $this->type = compare_types($this->type, $type);
    }

    if (isset($this->attributes[$name])){
      $this->attributes[$name] = compare_types($this->attributes[$name], $type);
    }

    //if (isset($this->children[$name])) print_error("attribute and subelement name colision", 90);

    $this->attributes[$name] = $type;
  }

  // zkontroluje kolize a prida child/subelement

  public function add_child($name){
    $name = mb_strtolower($name, 'UTF-8');

    if (!isset($this->children[$name])){
      $this->children[$name] = 1;
      if (isset($this->attributes[$name . "_id"]))
        print_error("attribute and subelement name colision", 90);
    } else {
      $this->children[$name] += 1;
      if (isset($this->attributes[$name . $this->children[$name] . "_id"]) || isset($this->attributes[$name . $this->children[$name] - 1 . "_id"]))
        print_error("attribute and subelement name colision", 90);
    }
  }


  public function print_element(){
    global $output_stream, $columns, $group;
    fprintf($output_stream, "CREATE TABLE ". $this->name . "(\n");
    fprintf($output_stream, "\tprk_" . $this->name . "_id INT PRIMARY KEY");

    if ($this->type !== "NA"){
      fprintf($output_stream, ",\n");
      fprintf($output_stream, "\t" . "value" . " " . $this->type);
    }

    if (!$columns){ // neni nastaven parametr -a, vytvari se sloupce z atributu
      foreach ($this->attributes as $name => $type) {
        fprintf($output_stream, ",\n");
        fprintf($output_stream, "\t" . $name . " " . $type);
      }
    }

    foreach ($this->children as $name => $count) {
      if ($count === 1 || $group){  //$group == zapnuty -b
        fprintf($output_stream, ",\n");
        fprintf($output_stream, "\t" . $name . "_id INT");
      } else {
        for($i = 1; $i <= $count; $i++){
          fprintf($output_stream, ",\n");
          fprintf($output_stream, "\t" . $name . $i . "_id INT");
        }
      }
    }
    fprintf($output_stream, "\n");
    fprintf($output_stream, ");\n\n");
  }


}

// proveri, zda se dodrzuje columns_max a pripadne provede zmeny
function check_max($elements, $columns_max){
  foreach ($elements as $el_name => $element) {
    foreach ($element->children as $child_name => $count) {
      if($count > $columns_max){
        $elements[$child_name]->add_attribute($el_name . "_id", "INT");
        unset($element->children[$child_name]);
      }
    }
  }
}

// mergne do element2
function merge_elements($element1, $element2){
  if($element2->type !== $element1->type)
    $element2->set_type(compare_types($element1->type, $element2->type));

  foreach ($element1->attributes as $name1 => $type1) {
    if (isset($element2->attributes[$name1])){
      if ($element2->attributes[$name1] !== $type1) {
        $element2->attributes[$name1] = compare_types($type1, $element2->attributes[$name1]);
      }
    } else {
      $element2->attributes[$name1] = $type1;
    }
  }

  foreach ($element1->children as $name1 => $count1) {
    if (isset($element2->children[$name1])){
      if ($element2->children[$name1] < $count1){
        $element2->children[$name1] = $count1;
      }
    } else {
      $element2->children[$name1] = $count1;
    }
  }
}

/*
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
    if (isset($this->attributes[$name . "_id"])) print_error("attribute and subelement name colision", 90);

    if(!isset($this->subelements[$name])){
      $this->subelements[$name] = array();
      $this->subelements[$name][$name . "_id"] = $type;
    } else {
      if (isset($this->subelements[$name][$name . "_id"])){
        $this->subelements[$name][$name . "1_id"] = $this->subelements[$name][$name . "_id"];
        unset($this->subelements[$name][$name . "_id"]);
        $this->subelements[$name][$name . "2_id"] = $type;
      } else {
        echo($this->attributes[$name][$name . "_id"] . "\n");
        $i = 2;
        for($i; isset($this->subelements[$name][$name . $i . "_id"]); $i++){}
        $this->subelements[$name][$name . $i . "_id"] = $type;
      }
    }
  }


  public function print_table(){
    global $output_stream, $columns_max;

    fprintf($output_stream, "CREATE TABLE ". $this->name . "(\n");
    fprintf($output_stream, "\t$this->primary_key");

    foreach ($this->attributes as $name => $type) {
      fprintf($output_stream, ",\n");
      fprintf($output_stream, "\t" . $name . " " . $type);
    }

    foreach ($this->subelements as $key => $array) {
      if($columns_max == -1 || $columns_max >= count($array)){
        foreach($array as $name => $type){
          fprintf($output_stream, ",\n");
          fprintf($output_stream, "\t" . $name . " " . $type);
        }
      }
    }

    fprintf($output_stream, "\n");
    fprintf($output_stream, ");\n\n");
  }
}



// sloucit tabulku1 do tabulky2
function merge_tables($table1, $table2){ //TODO nemerguju parent
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
*/


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
    if($columns_max !== -1 && $group) print_error("--etc cannot be set alongside -b", 1);

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
  fprintf(STDERR, "ERROR " . $code . ": " . $text . "\n");
  exit($code);
}


?>
