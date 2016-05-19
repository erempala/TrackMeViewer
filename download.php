<?php

    session_start();
  
    require_once("database.php");
    require_once("exporter/base.php");
    
// DMR didn't see where this was used so removed.    
//  $requireddb = urldecode($_GET["db"]);     
//  if ( $requireddb == "" || $requireddb < 7 )
//  {
//      echo "<Result>5</Result>";
//      die;
//  }   
  
    $db = connect_save();
    if (is_null($db))
  {
    echo "<Result>4</Result>";
    die();
  }
  
  $showbearings = 0;
  

  
  
  $action = $_GET["a"];
//  $username = urldecode($_GET["u"]);
//  $password = urldecode($_GET["p"]);
  $datefrom = urldecode($_GET["df"]);
  $dateto = urldecode($_GET["dt"]);
  $showbearings = urldecode($_GET["sb"]);
    
  
    if ($public_page == "yes" && array_key_exists("u", $_GET)) {
        $userid = $_GET["u"];
    } elseif (isset($_SESSION["ID"])) {
        $userid = $_SESSION["ID"];
    } else {
    echo "<Result>Not Logged in or this is not a private system</Result>";
    die();
    }

    $tripid = Exporter::normalize($db, $userid, $_GET);

    if($action=="kml")
    {
        require_once("exporter/kml.php");
        $exporter = new KMLExporter($db, $userid, $tripid, $datefrom, $dateto);
    }
    else if ($action = "gpx")
    {
        require_once("exporter/gpx.php");
        $exporter = new GPXExporter($db, $userid, $tripid, $datefrom, $dateto);
    }
    else
    {
        echo "<Result>Invalid action selected</Result>";
        die();
    }
    $output = $exporter->export($showbearings);

    // Create file
    $FileName = str_replace(" ", "_", $exporter->username . "_" . $exporter->tripname . "_" . $datefrom . "_" . $dateto . ".$action");

    // Set the name of the downloaded file
    header("Content-type: text/$action");
    header("Content-Disposition:attachment;filename=" . $FileName);
    echo "$output";

    $db = null;

?>
