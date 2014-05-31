<?php

// EDIFACT_FileCONTENT
// INPUT : FileInput : Nom du fichier a analyser
// OUTPUT : Chaine de carcatère contenant les informations
// Cas EDIFACT : EDIFACT;UNA;CHARSET
// Cas XML : XML
// Cas Other : OTHER
function FileCONTENT($FileInput) {

	$Valeur = "";
	
	$IdFile   = fopen($FileInput,"r");
	
	if ($TEMP = fgets($IdFile,50)) {
		
	}
	
	return $Valeur;
}

function array_sort($array, $on, $order='SORT_DESC') 
    { 
      $new_array = array(); 
      $sortable_array = array(); 
  
      if (count($array) > 0) { 
          foreach ($array as $k => $v) { 
              if (is_array($v)) { 
                  foreach ($v as $k2 => $v2) { 
                      if ($k2 == $on) { 
                          $sortable_array[$k] = $v2; 
                      } 
                  } 
              } else { 
                  $sortable_array[$k] = $v; 
              } 
          } 
  
          switch($order) 
          { 
              case 'SORT_ASC':    
                  echo "ASC"; 
                  asort($sortable_array); 
              break; 
              case 'SORT_DESC': 
                  echo "DESC"; 
                  arsort($sortable_array); 
              break; 
          } 
  
          foreach($sortable_array as $k => $v) { 
              $new_array[] = $array[$k]; 
          } 
      } 
      return $new_array; 
    } 
 
function microtime_float()
{
    list($usec, $sec) = explode(" ", microtime());
    return ((float)$usec + (float)$sec);
}

?>
