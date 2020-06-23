<?php
  /*********************************************************
   * Openhab SQl 0.3
   * 
   * Mario Wagner
   * 19.06.2020
   * 
   * Evaluation of stored values from Openhab2 in MySQL
   */
  include("openhabsql.config.php");

  echo "\n  Openhab SQL 0.3\n  ===============\n  (c) 19.06.2020 by Mario Wagner\n\n";
  
  $opts = "s:t:i:f:";
  $debug =false;
  $days=0;
  $id=0;
  $filter="%";
  $csv=false; 
  $sort=1;

  $options = getopt($opts);

   foreach ($options as $opt=>$val) {
    switch ($opt) {
      case 't': $days=$val;
                break;
      case 'i': $id=$val;
                break;
      case 'f': $filter=$val;
                break;
      case 's': $sort=$val;
              break;
  }
  }

  if (!array_key_exists(1,$argv)){
    printHelp();
    exit;
  }

  if (array_search("--debug",$argv) or array_search("-d",$argv)) {
    $debug=true;
  }

  if (array_search("--help",$argv) or array_search("-h",$argv)) {
    printHelp();
    exit;
  }

  if (array_search("--csv",$argv) or array_search("-c",$argv)) {
    $csv=true;
  }

  if (array_search("listTables",$argv)) {
    listTables($database, $filter, $csv, $sort, $debug);
  }
  
  if (array_search("listLastEntries",$argv)) {
    listLastEntries($database, $filter, $sort,  $debug);
  }

  if (array_search("listUnusedEntries",$argv)) {
    if ($days > 0) {
      listUnusedEntries($database, $filter, $days, $sort, $debug);
    } else {
      echo "please define time in days with -t <days>\n\n";
      echo "Example: php openhabsql.php -t 5 listUnusedEntries\n\n";
    }
  }

  if (array_search("removeUnusedEntries",$argv)) {
    if ($days > 0) {
      removeUnusedEntries($database, $filter, $days, $debug);
    } else {
      echo "please define time in days with -t <days>\n\n";
      echo "Example: php openhabsql.php -t 5 removeUnusedEntries\n\n";
    }
  }

  if (array_search("summarizeEntry",$argv)) {
    if ($id > 0) {
      summarizeEntry($database, $id, $csv, $sort, $debug);
    } else {
      echo "please define id with -i <id>\n\n";
      echo "Example: php openhabsql.php -i 123 summarizeEntry\n\n";
    }
  }

  if (array_search("summarizeEntries",$argv)) {
    summarizeEntries($database, $filter, $csv, $sort, $debug);
  }

 
function printHelp(){
  echo "\nHelp\n----\n\n";
  echo "php openhabsql.php [options] [command]\n\n";
  echo "Options:\n";
  echo "-h --help           : help\n";
  echo "-d --debug          : debug, can be combined with any other options\n";
  echo "-c --csv            : displays output in csv format\n\n";
  echo "-t <days>           : time since in days\n";
  echo "-i <id>             : table id\n";
  echo "-f <filter>         : filters item names like 'level%temp'\n";
  echo "-s <column>         : sort table colums, 1=first col, -1=first col descending\n";
  echo "\n";
  echo "Commands:\n";
  echo "listTables          : list of all tables with id and name\n";
  echo "listLastEntries     : list of last entries of all items\n";
  echo "listUnusedEntries   : list of unused entries of all items, needs option -t\n";
  echo "removeUnusedEntries : removes unused tables, needs option -t\n";
  echo "summarizeEntry      : summary all states of one item, needs option -i\n";
  echo "summarizeEntries    : summary all states of all items\n";
  echo "\n";
  echo "Attention: removeUnusedEntries deletes tables without asking. \n";
  echo "           Be careful and make a mysqldump in advance!\n";
  echo "\n";
}

function listTables($database, $filter, $csv, $sort, $debug){
  echo "List Tables $filter\n";
  $db=connDB($database, $debug);
  if(!$tables=getTables($db, $filter, $debug)){
    echo "no tables with '$filter' found\n";
    exit;
  };
  $header['titles']=array("ID", "Name");
  $header['mask']=array("%5.5s ","%-40.40s");
  $content['mask']=array("%5.5s ","%-40.40s");
  foreach ($tables as $id=>$name){
    $content['data'][$id]=array($id, $name);
  }
  if ($csv){
    printCSV($header, $content);
  } else {
    printTable($header, $content, $sort);
  }
}

function summarizeEntry($database, $id, $csv, $sort, $debug){
  $sum=array();
  $lastValue="";
  echo "Summaries Entries of $id\n";
  $db=connDB($database, "", $debug);
  $tables=getTables($db, "%", $debug);
  if (!array_key_exists($id, $tables)){
    echo "Entry '$id' does not exist\n";
    exit;
  }
  echo "ID: $id, Name: ".$tables[$id]."\n";
  $values[$id]=getValues($db, "Item".$id, "order by Time", $debug);
  foreach ($values[$id] as $date=>$value){
    //printf($mask, $id, $date, $value);
    if (array_key_exists($lastValue, $sum)) {
      if ($debug) {echo "*summarizeEntry::key exists value=$lastValue\n";};
      $sum[$lastValue]['count']++;
      $sum[$lastValue]['time']=$sum[$lastValue]['time']+strtotime($date)-strtotime($lastDate);
    } 
    else {
      if ($debug) {echo "*summarizeEntry::key not exists value=$lastValue\n";};
      $sum[$value]['count']=1;
      $sum[$value]['time']=0;
    } 
    $lastDate=$date;
    $lastValue=$value;
  }
  
  $header['titles']=array("Status", "Count", "Time [s]");
  $header['mask']=array("%-12.12s ","%-6.6s", "%-20.20s");
  $content['mask']=array("%-12.12s ","%-6.6s", "%-20.20s");

  foreach($sum as $key=>$val){
    $content['data'][$key]=array($key, $val['count'], $val['time']);
  }
  if ($csv){
    printCSV($header, $content);
  } else {
    printTable($header, $content, $sort);
  }

}


function listLastEntries($database, $filter, $sort, $debug){
  echo "List Last Entries $filter\n";
  $db=connDB($database, $debug);
  if(!$tables=getTables($db, $filter, $debug)){
    echo "no tables with '$filter' found\n";
    exit;
  };
  $header['titles']=array("ID", "Name", "Date", "Value");
  $header['mask']=array("%5.5s ","%-20.20s ","%-20.20s ","%-10.10s ");
  $content['mask']=array("%5.5s ","%-20.20s ","%-20.20s ","%10.8s ");

  foreach ($tables as $id=>$name){
    $values[$id]=getValues($db, "Item".$id, "order by Time desc limit 1", $debug);
    foreach ($values[$id] as $date=>$value){
      $content['data'][$id]=array($id, $name, $date, $value);
    }
  }

  if ($csv){
    printCSV($header, $content);
  } else {
    printTable($header, $content, $sort);
  }
}

function summarizeEntries($database, $filter, $csv, $sort, $debug){
  $sum=array();
  echo "Summaries Entries $filter\n";
  $db=connDB($database, $debug);
  if(!$tables=getTables($db, $filter, $debug)){
    echo "no tables with '$filter' found\n";
    exit;
  };
  foreach($tables as $id=>$Name){
    $values[$id]=getValues($db, "Item".$id, "order by Time", $debug);
    $sum[$id]=array();
    $lastValue="";
    foreach ($values[$id] as $date=>$value){
      if (array_key_exists($lastValue, $sum[$id])) {
        if ($debug) {echo "*summarizeEntries::key exists value=$lastValue\n";};
        $sum[$id][$lastValue]['count']++;
        $time=strtotime($date)-strtotime($lastDate);
        $sum[$id][$lastValue]['time']=$sum[$id][$lastValue]['time']+$time;
        if ($time>$sum[$id][$lastValue]['maxtime']) {$sum[$id][$lastValue]['maxtime']=$time;};
        if ($time<$sum[$id][$lastValue]['mintime']) {$sum[$id][$lastValue]['mintime']=$time;};
        if (is_numeric($lastValue)) {
          $tot[$id]['prod']=$tot[$id]['prod']+$time*$lastValue;
        };
        $tot[$id]['time']=$tot[$id]['time']+$time;
      } 
      else {
        if ($debug) {echo "*summarizeEntries::key not exists value=$lastValue\n";};
        $sum[$id][$value]['count']=1;
        $sum[$id][$value]['time']=0;
        $sum[$id][$value]['maxtime']=-1;
        $sum[$id][$value]['mintime']=1000000000;
        $tot[$id]['prod']=0;
        $tot[$id]['time']=0;
      } 
      $lastDate=$date;
      $lastValue=$value;
   }
   $tot[$id]['max']=max($values[$id]);
   $tot[$id]['min']=min($values[$id]);
   if ($debug)  {echo "*summarizeEntries:: max[$id]=".$tot[$id]['max']."\n";};
}

  $header['titles']=array("ID", "Name", "Status", "Count", "Time [s]", "Max Time [s]", "Min Time [s]");
  $header['mask']=array("%6.6s ","%-20.20s ","%6.6s ","%6.6s ","%12.12s ","%12.12s ","%12.12s");
  $content['mask']=array("%6.6s ","%-20.20s ","%6.6s ","%6.6s ","%12.12s ","%12.12s ","%12.12s");

    foreach($sum as $id=>$entry) {
      foreach($sum[$id] as $key=>$value) {
        $content['data'][$id.$key]=array($id, $tables[$id], $key, $value['count'], $value['time'], number_format($value['maxtime'],0,".","'"), $value['mintime']);
      }
    }  

    if ($csv){
      printCSV($header, $content);
    } else {
      printTable($header, $content);
    }

    $header2['titles']=array("ID", "Name", "Total", "Total Time [s]", "Max Value", "Min Value", "Average");
    $header2['mask']=array("%6.6s ","%-20.20s ","%12.12s ","%15.15s ","%10.10s ","%10.10s ","%10.10s ");
    $content2['mask']=array("%6.6s ","%-20.20s ","%12.12s ","%15.15s ","%10.10s ","%10.10s ","%10.10s ");

    foreach($tot as $id=>$name) {
      $avg=$tot[$id]['prod']/$tot[$id]['time'];
      $content2['data'][$id]=array($id, $tables[$id], $tot[$id]['prod'], $tot[$id]['time'], $tot[$id]['max'], $tot[$id]['min'],$avg);
    }

    if ($csv){
      printCSV($header2, $content2);
    } else {
      printTable($header2, $content2, $sort);
    }

  }

function listUnusedEntries($database, $filter, $days, $sort, $debug){
  $time=new DateTime('-'.$days.' day');
  echo "List Unused Entries $filter since ".$time->format('Y-m-d H:i:s')."\n";
  $db=connDB($database, $debug);
  if(!$tables=getTables($db, $filter, $debug)){
    echo "no tables with '$filter' found\n";
    exit;
  };
  $mask = "|%5.5s |%-40.40s |%-40.40s |%-40.40s |\n";
  printf($mask, "ID", "Name", "Date", "Value");
  foreach ($tables as $id=>$name){
    $values[$id]=getValues($db, "Item".$id, "order by Time desc limit 1", $debug);
    foreach ($values[$id] as $date=>$value){
      if ($date<$time->format('Y-m-d H:i:s')){
        printf($mask, $id, $name, $date,$value);
      }
    }
  }
}

function removeUnusedEntries($database, $filter, $days, $debug){
  $time=new DateTime('-'.$days.' day');
  echo "Remove Unused Entries $filter since ".$time->format('Y-m-d H:i:s')."\n";
  $db=connDB($database, $debug);
  if(!$tables=getTables($db, $filter, $debug)){
    echo "no tables with '$filter' found\n";
    exit;
  };
  $mask = "|%5.5s |%-40.40s |%-40.40s |%-40.40s |\n";
  printf($mask, "ID", "Name", "Date", "Value");
  foreach ($tables as $id=>$name){
    $values[$id]=getValues($db, "Item".$id, "order by Time desc limit 1", $debug);
    foreach ($values[$id] as $date=>$value){
      if ($date<$time->format('Y-m-d H:i:s')){
        printf($mask, $id, $name, $date,$value);
        removeTable($db, $id, $debug);
      }
    }
  }
}


function connDB($database, $debug){
  if ($debug) { echo "*connDB:: Connect to Database .. "; };
  $db=new mysqli($database['host'], $database['username'], $database['password'], $database['database']);
  if (!$db) { 
    die(' connection failed: ' . mysqli_connect_error()); 
  }
  else { 
    if ($debug) {
      echo "ok\n";
    };
    return $db;
  };
}

function getTables($db, $filter, $debug){
  $query = "SELECT * from Items where ItemName like '$filter'";
  $tables=array();
  if ($debug) { echo "*getTables:: get tables list .. "; }
  if ($result = mysqli_query($db, $query)) {
    while($row = mysqli_fetch_array($result)){
      $tables[$row['ItemId']]=$row['ItemName'];
    }
    if ($debug) { echo "ok\n"; };
    return $tables;
  }
  else {
    return false;
  }
}

function getValues($db, $table, $dbopts, $debug){
  $query = "SELECT * from $table $dbopts";
  if ($debug) { echo "*getValues:: get values $table $dbopts .. "; }
  if ($result = mysqli_query($db, $query)) {
    while($row = mysqli_fetch_array($result)){
      $values[$row['Time']]=$row['Value'];
    }
    if ($debug) { echo "ok\n"; };
    return $values;
  }
  else {
    return null;
  }
}

function removeTable($db, $id, $debug){
  $query = "DROP TABLE Item".$id;
  if ($debug) { echo "*removeTable:: drop Item".$id." .. "; }
  if ($result = mysqli_query($db, $query)) {
    if ($debug) { echo "ok\n"; };
    if ($debug) { echo "*removeTable:: delete Item".$id." .. "; }
      $query = " delete from Items where ItemId=$id;";
      if ($result = mysqli_query($db, $query)) {
        if ($debug) { echo "ok\n"; };
      }
  }
}

function printCSV($header, $content){
  echo "\n";
  echo implode(",", $header['titles'])."\n";
  foreach($content['data'] as $id => $valArray){
    echo implode(",", $valArray)."\n";
  }
}


function printTable($header, $content, $sort=1){
  echo "\n";
  foreach($header['titles'] as $key=>$title){
    printf("  ".$header['mask'][$key],"-------------------------------------------------------");
  }
  echo "\n";
  $i=0;
  foreach($header['titles'] as $key=>$title){
    $max[$i]=mmax($content['data'], $i);
    printf("| ".$header['mask'][$key],$title);
    $i++;
  }
  echo "|\n";
  foreach($header['titles'] as $key=>$title){
    printf("| ".$header['mask'][$key],"-------------------------------------------------------");
  }
  echo "|\n";
  if ($sort>0){
    $cols=array_column($content['data'], $sort-1);
    array_multisort($cols, SORT_ASC, $content['data']);
  } else {
    $cols=array_column($content['data'], -$sort-1);
    array_multisort($cols, SORT_DESC, $content['data']);   
  }

  //print_r($content['data']);
  foreach($content['data'] as $id => $valArray){
    $i=0;
    foreach($valArray as $value){
      if (is_numeric($value)){
        if ($max[$i] > 1000){
          $value=number_format($value,0,".","'");
        } elseif (is_integer($max[$i])) {
          $value=number_format($value,0,".","'");
        } else {
          $value=number_format($value,6,".","'");
        }
      }
      printf("| ".$content['mask'][$i], $value);
      $i++;
    }
    echo "|\n";
  }
  foreach($header['titles'] as $key=>$title){
    printf("| ".$header['mask'][$key],"-------------------------------------------------------");
  }
  echo "|\n";
  $i=0;
  foreach($max as $key=>$value){
    if (is_numeric($value)){
      if ($max[$i] > 1000){
        $value=number_format($value,0,".","'");
      } elseif (is_integer($value)) {
        $value=number_format($value,0,".","'");
      } else {
        $value=number_format($value,6,".","'");
      }
    }
    printf("| ".$content['mask'][$i], $value);
    $i++;
  }
  echo "|\n";
  foreach($header['titles'] as $key=>$title){
    printf("  ".$header['mask'][$key],"-------------------------------------------------------");
  }
  echo "\n";
}

function mmax($array, $index){
  foreach($array as $key=>$entry){
    $tmp[$key]=$entry[$index];
  }
  return max($tmp);
}
?>
