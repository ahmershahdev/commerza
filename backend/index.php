<?php

$skills = [
['HTML', 'CSS', 'JS'],
['JQUERY','MONGODB','SQL'],
['REACT','NODE','PHP']
];

for($i=0; $i<=2; $i++){
    for($j=0; $j<=2; $j++){

  echo 
  "<table border='2px'>" .
   '<tr>'. 
   '<td>'.
    $skills[$i][$j].
   '</td>'.
    
   '</tr>'.
   '</table>';
   }
}

?>