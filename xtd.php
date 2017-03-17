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

///hlavni cast programu
mb_internal_encoding( 'UTF-8' );
get_opts($argv);
generate();
fclose($output_stream);
exit(0);
// konec hlavni casti programu


// hlavni funkce
function generate(){
  global $elements, $xml, $columns, $group, $relations, $output_stream, $header_text, $columns_max;

  foreach ($xml->children() as $child) { // preskocim korenovy element
    recursive_load_elements($child, 0, ""); //zpracuje xml do $elements
  }

  if ($relations) // pokud -g tiskne xml s relacemi
    print_relations(create_relations($elements), $output_stream);
  else{ // jinak tiskne DDL tabulky
    if($columns_max != -1) check_max($elements, $columns_max); // proveri a opravi --etc
    print_tables($elements);
  }
}

// tisk DDL
function print_tables($elements){
  global $header_text, $output_stream;
  if ($header_text){
      fprintf($output_stream, "--" . $header_text . "\n\n");
  }

  foreach ($elements as $element) {
    $element->print_element();
  }
}

// vrati typ dat reprezentovanych ve stringu $str
function get_type($str){
  $str = mb_strtolower(trim($str), 'UTF-8');

  if ($str === '') return 0;
  if (is_bool(filter_var($str, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE))) return "BIT";
  if (is_int(filter_var($str, FILTER_VALIDATE_INT))) return "INT";
  if (is_float(filter_var($str, FILTER_VALIDATE_FLOAT))) return "FLOAT";
  return "NTEXT"; // pokud je to atribut, melo by to byt NVARCHAR
}

// porovna typy a vrati "silnejsi"
function compare_types($type1, $type2){
  if ($type1 === 0 || $type1 == "NA") return $type2;
  if ($type2 === 0 || $type2 == "NA") return $type1;

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

// nacte xml elementy, jejich atributy, vnitrni text a podelementy
function recursive_load_elements($element, $depth, $parent){
  global $elements, $columns;

  $new_element = new element;
  $new_element->set_name($element->getName());
  $new_element->set_parent($parent);

  if (!get_type($element)){ // nema textovy obsah
    if (!$columns){
      foreach($element->attributes() as $attribute){
        $type = get_type($attribute);
        if ($type === "NTEXT") $type = "NVARCHAR";
        if ($type === "NA") $type = "BIT";
        $new_element->add_attribute($attribute->getName(), $type);
      }
    }
    foreach($element->children() as $child){
      $new_element->add_child($child->getName());
      recursive_load_elements($child, $depth + 1, $element->getName());
    }
  } else {   // ma textovy obsah - nemusime resit podelementy
    $new_element->set_type(get_type($element));
    if (!$columns){
      foreach($element->attributes() as $attribute){
        $type = get_type($attribute);
        if ($type === "NTEXT") $type = "NVARCHAR";
        if ($type === 0) $type = "BIT";
        $new_element->add_attribute($attribute->getName(), $type);
      }
    }
  }

  if (!isset($elements[mb_strtolower($new_element->get_name(), 'UTF-8')])){
    $elements[$new_element->get_name()] = $new_element;
  } else {
    // porovnat tabulky, sloucit je
    merge_elements($new_element, $elements[$new_element->get_name()]);
  }
}

// trida reprezentujici element
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
      $text = compare_types($this->attributes["value"], $text);
      unset($this->attributes["value"]);
    }

    if($this->type != "NA"){
      $this->type = compare_types($this->type, $text);
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
    } else if (isset($this->attributes[$name])){
      $this->attributes[$name] = compare_types($this->attributes[$name], $type);
    } else $this->attributes[$name] = $type;
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

//tiskne DDL pro vytvoreni tabulky
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
// konec tridy element


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

// mergne element 1 do element2
function merge_elements($element1, $element2){
  foreach ($element1->attributes as $name1 => $type1) {
    if (isset($element2->attributes[$name1])){
      if ($element2->attributes[$name1] !== $type1) {
        $element2->attributes[$name1] = compare_types($type1, $element2->attributes[$name1]);
      }
    } else {
      $element2->attributes[$name1] = $type1;
    }
  }

  if($element2->type !== $element1->type)
    $element2->set_type(compare_types($element1->type, $element2->type));

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


// vytvori xml relations
function create_relations($elements){
  global $columns_max, $group;
  $relations = array();

  // vytvori se vztah mezi kazdym rodicem a ditem v $elements. pokud je nastavene --etc a rodic ma vice deti, nastavuje se vztah opacny
  foreach ($elements as $parent) {
    foreach ($elements[$parent->get_name()]->children as $child => $count) {
      if (($columns_max === -1 && $group === 0) || $columns_max !== -1 && $columns_max >= $count){
        $relations[$parent->get_name()][$child] = "X";
      }
      else if ($group){
        $relations[$parent->get_name()][$child] = "X";
      }
    }

    foreach ($elements as $element) {
      foreach ($elements[$element->get_name()]->children as $ckey => $count) {
        if($columns_max !== -1 && $columns_max < $count && $ckey == $parent->get_name()){
          $relations[$parent->get_name()][$element->get_name()] = "X";
        }
      }
    }
  }

  // kazda tabulka ma sama se sebou relaci 1:1
  // (a) Pokud a = b, pak R(a, b) = 1:1
  foreach ($elements as $element) {
    $relations[$element->get_name()][$element->get_name()] = "1:1";
  }

  // nastavi se hodnoty vztahu
  foreach ($relations as $parent => $pvalue) {
    foreach ($relations[$parent] as $child => $cvalue) {
      $relations[$parent][$child] = get_relationship($relations, $parent, $child);
    }
  }

  // vztahy plati pro oba smery, pokud existuje R(a,b), pak R(b,a) bude mit opacnou hodnotu
  foreach ($relations as $parent => $pvalue) {
    foreach ($relations[$parent] as $child => $cvalue) {
        if($relations[$parent][$child] === "N:M") {
          $relations[$child][$parent] = "N:M";
        }
        else if($relations[$parent][$child] === "1:N") {
            $relations[$child][$parent] = "N:1";
        } else if($relations[$parent][$child] === "N:1") {
          $relations[$child][$parent] = "1:N";
        }
    }
  }


  // tranzitivita vztahu 1:N a N:1
  $change = TRUE;
  while($change) {
    $change = FALSE;
    foreach ($relations as $a => $avalue){
        foreach ($relations[$a] as $c => $cvalue){
            if($relations[$a][$c] == "1:N" || $relations[$a][$c] == "N:1"){
              // POKUD ∃c ∈ T : R(a, c) = 1:N,  /  ∃c ∈ T : R(a, c) = N:1,

                foreach ($relations[$c] as $b => $bvalue){

                    if($relations[$c][$b] == $relations[$a][$c]){
                      //  R(c, b) = 1:N   /           R(c, b) = N:1

                        if(!isset($relations[$a][$b])){
                          // ∀a, b ∈ T,  R(a, b) = ε

                            $relations[$a][$b] = $relations[$c][$b];
                            // PAK R(a, b) = 1:N  / R(a, b) = N:1
                            $change = TRUE;
                        }
                    }
                }
            }
        }
      }
    }

    // tranzitivita vztahu N:M
    $change = TRUE;
    while($change) {
      $change = FALSE;
      foreach ($relations as $a => $avalue) {
        foreach ($relations[$a] as $c => $cvalue) {
          //∃c ∈ T : R(a, c) != ε

            foreach ($relations[$c] as $b => $bvalue) {
              //R(c, b) != ε

                if(!isset($relations[$a][$b])) {
                  // ∀a, b ∈ T,  R(a, b) = ε

                    $relations[$a][$b] = "N:M";
                    $relations[$b][$a] = "N:M";
                    //R(a, b) = R(b, a) = N:M
                    $change = TRUE;
                }
            }
        }
      }
    }

    return $relations;
}

// vraci hodnotu vztahu mezi parent a child podle toho, zda jsou inicializovane [$parent][$child] a [$child][$parent]
function get_relationship($relations, $parent, $child){
  if ($parent === $child) return "1:1";  // (a) Pokud a = b, pak R(a, b) = 1:1.

  if (isset($relations[$parent][$child])){
    if (isset($relations[$child][$parent])){
      return "N:M";  //(b) Pokud a 6 = b, a → b a b → a, pak R(a, b) = N:M
    } else return "N:1"; // (c) Pokud a 6 = b, a → b a neplatí b → a, pak R(a, b) = N:1
  } else if (isset($relations[$child][$parent])){
    return "1:N"; //(d) Pokud a 6 = b, b → a a neplatí a → b, pak R(a, b) = 1:N
  } else {
    return "1:1"; // (e) Jinak R(a, b) = ε.
  }
}

/*
// pomocna funkce
function helpprint($relations){
  foreach ($relations as $parent => $pvalue) {
    echo($parent . " =>\n");
    foreach ($relations[$parent] as $child => $cvalue) {
      echo("\t" . $child . " = " . $cvalue . "\n");
    }
  }
}
*/

// tiskne relations na output_stream v predepsanem formatu
function print_relations($relations, $output_stream){
  fprintf($output_stream, "<tables>\n");
  foreach ($relations as $parent_name => $child) {
    fprintf($output_stream, "\t<table name=\"" . $parent_name . "\">\n");
    foreach($child as $child_name => $rel){
      fprintf($output_stream, "\t\t<relation to=\"" . $child_name . "\" relation_type=\"" . $rel . "\"/>\n");
    }
    fprintf($output_stream, "\t</table>\n");
  }
  fprintf($output_stream, "</tables>\n");
}

// nacte predane parametry programu do globalnich promennych + resi konflikty
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

    unset($argv[0]);

    foreach($argv as $argument){ // overeni zda nebyly predany neplatne argumenty
      if ( (strpos($argument, "--help") === FALSE) && (strpos($argument, "--input=") === FALSE) && (strpos($argument, "--output=") === FALSE) &&
           (strpos($argument, "--header=") === FALSE) && (strpos($argument, "--etc=") === FALSE) && !preg_match('/(^-[abg]+)/', $argument) )
           print_error("wrong parameter" . $argument . ", try calling --help", 1);
    }

    $opts = getopt($options, $longopts);

    global $xml, $columns, $group, $relations, $output_stream, $header_text, $columns_max;
    $columns = $group = $relations = 0;
    $columns_max = -1;
    $help = 0;

    $input_file = $output_file = $header_text = "";

    foreach($opts as $key => $value){
      switch ($key){
          case "a":
              if($value === FALSE) $columns = 1;
              break;

          case "b":
              if($value === FALSE) $group = 1;
              break;

          case "g":
              if($value === FALSE) $relations = 1; // vim ze je toto pojmenovani matouci, vzhledem k tomu, ze se jmenuje relations uz i pole, ale neni cas
              break;

          case "help":
              if($value === FALSE) $help = 1;
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

    if ($help && ($group || $columns_max != -1 || $relations || $columns || $input_file != ""
                    || $output_file != "" || $header != "" )) print_error("--help set alnogside other parameter", 1);
    else if ($help) print_help();


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
      if($input_file && ($input_file === $output_file)) print_error("ouput file identical with input file", 3);
      if(($output_stream = fopen($output_file, "w")) === false) print_error("can't open output file", 3);
    } else {
      $output_stream = STDOUT;
    }
}


// vypise help a ukonci program
function print_help(){
  echo("IPP XTD HELP:\n");
  echo("--help            - prints this\n");
  echo("--input=filenime  - sets input file, otherwise STDIN\n");
  echo("--output=filenime - sets output file, otherwise STDOUT\n");
  echo("--header=text     - prepends 'text' as a header to the output\n");
  echo("--etc=n           - (n >= 0) max. no. of columns made from namesake elements\n");
  echo("-a                - prevents generation of columns from attributes\n");
  echo("-b                - groups namesake elements into one column\n");
  echo("-g                - changes output to xml with relations\n");
  exit(0);
}

// vypise error a ukonci program
function print_error($text, $code){
  fprintf(STDERR, "ERROR " . $code . ": " . $text . "\n");
  exit($code);
}

?>
