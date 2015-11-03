<?php

    define("R_OK", 0);
    define("R_TRIP_UNSPECIFIED", 6);
    define("R_MISSING_TRIP", 7);
    define("R_LOCKED_TRIP", 8);

    require_once("database");
	
  $requireddb = urldecode($_GET["db"]);     
  if ( $requireddb == "" || $requireddb < 8 )
  {
    	echo "Result:5";
    	die;
  }	
	
	
    $db = connect_save();
    if (is_null($db))
	{
		echo "Result:4";
		die();
	}
	
	// Check username and password
	$username = $_GET["u"];
	$password = $_GET["p"];
	
	// User not specified
	if ( $username == "" || $password == "" )
	{
		echo "Result:3";
		die();
	}
	
    $userid = $db->valid_login($username, $password);
    if ($userid === NO_USER)
    {
        $userid = $db->create_login($username, $password);
        if ($userid < 0)
            result(2);
    }
    elseif ($userid === INVALID_CREDENTIALS)
    {
        result(1);  // user exists, password incorrect.
    }
    elseif ($userid === LOCKED_USER)
    {
			echo "User disabled. Please contact system administrator";
			die();
    }
	
	
	$tripname = urldecode($_GET["tn"]);	
	$action = $_GET["a"];
	
	
	
	
	if ($action=="noop")
	{
		echo "Result:0";
		die();		
	}
			
	
	if ($action == "sendemail" )
	{
		$to = $_GET["to"];
		$body = $_GET["body"];
		$subject = $_GET["subject"];
		
		if ( $subject == "" )
			$subject = "Notification alert";
		
		mail($to,$subject, $body, "From: TrackMe Alert System\nX-Mailer: PHP/");		
		
		echo "Result:0";		
		die();		
	}

	
	
	if( $action=="geticonlist")
	{

        $iconlist = "Result:0";
        $result = $db->exec_sql("SELECT name FROM icons ORDER BY name");
        while( $row=$result->fetch() )
		{
            $iconlist.= "|$row[name]";
		}

        echo $iconlist;
		die();
	}
		
	


	if($action=="upload")
	{				
		
		if ( $tripname != "" )
		{			
            $tripid = get_trip();
            // Trip doesn't exist. Let's create it.
            if (is_null($tripid))
            {
                $db->exec_sql("INSERT INTO trips (FK_Users_ID,Name) VALUES (?, ?)",
                              $userid, $tripname);
            }
            $tripid = get_trip();
            if (is_null($tripid))
            {
                result(R_TRIP_UNSPECIFIED);
            }
		}
        else
        {
            $tripid = null;
        }
		
		
        if ($tripid === false)
		{
            result(R_LOCKED_TRIP);
		}
	
		$lat = $_GET["lat"];
		$long = $_GET["long"];
		$dateoccurred = urldecode($_GET["do"]);		
		$comments = urldecode($_GET["comments"]);		
		$cellid = urldecode($_GET["cid"]);		
		$signalstrength = urldecode($_GET["ss"]);		
		$signalstrengthmax = urldecode($_GET["ssmax"]);		
		$signalstrengthmin = urldecode($_GET["ssmin"]);		
	  $uploadss = urldecode($_GET["upss"]);	
	
		
        $iconid = null;
		if ($iconname != "" ) 
		{
            $icon_row = $db->exec_sql("SELECT ID FROM icons WHERE name = ?", $iconname)->fetch();
            if ($icon_row)
                $iconid = $icon_row['ID'];
		}
		

        $params = array($userid, $tripid, $lat, $long, $dateoccurred, $iconid);
        foreach array("sp", "alt", "comments", "imageurl", "ang") as $param
        {
            $params[] = get_nulled($param);
        }
        foreach array("ss", "ssmax", "ssmin") as $param
        {
            if ($uploadss == 1)
                $params[] = get_nulled($param);
            else
                $params[] = null;
        }
        $params[] = get_nulled("bs");

        $result = $db->exec_sql("INSERT INTO positions (FK_Users_ID, FK_Trips_ID, latitude, longitude, " .
                                "dateoccurred, FK_Icons_ID, speed, altitude, comments, imageurl, " .
                                "angle, signalstrength, signalstrengthmax, signalstrengthmin, " .
                                "batterystatus) values(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                                $params);
		

		if (!$result) 
		{
            result(R_MISSING_TRIP, $db->errorInfo()[2]);
		}
		
		$upcellext = urldecode($_GET["upcellext"]);				
		if ($upcellext == 1 && $cellid != "" )
		{
            $params = array($cellid, $lat, $long);
            foreach array("ss", "ssmax", "ssmin") as $param
            {
                $params[] = get_nulled($param);
            }
            $db->exec_sql("INSERT INTO cellids(cellid, latitude, longitude, signalstrength, " .
                          "signalstrengthmax, signalstrengthmin) values (?, ?, ?, ?, ?, ?)",
                          $params);
		}

		
        result();
	}
	
	
	
	
	
	
	
	
	
	
		
	if($action=="updatepositiondata" || $action=="updateimageurl")
			
		$id = urldecode($_GET["id"]);		
		$ignorelocking = urldecode($_GET["ignorelocking"]);
		
		if ( $id == "" )
		{
			echo "Result:6"; // id not specified
			die();
		}
		
		if ($ignorelocking == "" )
			$ignorelocking = 0;
		
		$locked = 0;
		$result=mysql_query("Select Locked FROM trips A1 INNER JOIN positions A2 ON A2.FK_TRIPS_ID=A1.ID WHERE A2.FK_Users_ID = '$userid' and A2.ID='$id'");
		if ( $row=mysql_fetch_array($result) )
		{
			 $locked = $row['Locked'];			 
			 if ( $locked == 1 && $ignorelocking == 0 )
			 {
			 		echo "Result:8";	
			 		die();
			 }
		}
		else
		{
			 echo "Result:7"; // trip not found.
			 die();					
		}
		
		
		$sql = "Update positions set ";
		
		if ( isset($_GET["imageurl"]))
		{		
			$imageurl = urldecode($_GET["imageurl"]);
			
			if ($imageurl != "" )
			{		
				$iconid='null';		
				$result=mysql_query("Select ID FROM icons WHERE name = 'Camera'");
				if ( $row=mysql_fetch_array($result) )
							$iconid=$row['ID'];							
			
				$sql.=" fk_icons_id=$iconid, imageurl='$imageurl',";			
			}
			else
				$sql.=" imageurl=null,";			
			
	  }
	  
	  if ( isset($_GET["comments"]))
		{				
			$comments = urldecode($_GET["comments"]);				
			
			if ( $comments == "" )
				$sql.=" comments=null,";							
			else
				$sql.=" comments='$comments',";			
				
	  }	 
		 		 	 		 		 
		$sql.="ID=ID where id=$id AND fk_users_id='$userid'";
		
		 		 
		mysql_query($sql);		 	 
        result();
	}
	
	
	if($action=="delete")
	{	
        $where = array("FK_Users_ID = ?");
        $params = array($userid);
        if ($tripname === "<None>")
        {
            $where[] = "FK_Trips_ID is NULL";
        }
        elseif ($tripname)
        {
            $tripid = test_trip();
            $where[] = "FK_Trips_ID = ?";
            $params[] = $tripid;
        }
		
		if ( $tripname == "<None>" )			
		else if ( $tripname != "" )
			$sql = "DELETE FROM positions WHERE FK_Trips_ID='$tripid' ";
		else
			$sql = "DELETE FROM positions WHERE 1=1 ";
					
		$sql.= " and FK_Users_ID = '$userid' ";

		$datefrom = urldecode($_GET["df"]);
		$dateto = urldecode($_GET["dt"]);
		
		if ( $datefrom != "" )
        {
            $where[] = "DateOccurred >= ?";
            $params[] = $datefrom;
        }
		if ( $dateto != "" )
        {
            $where[] = "DateOccurred <= ?";
            $params[] = $dateto;
        }

			
						
        $where = implode(" AND ", $where);
        $db->exec_sql("DELETE FROM positions WHERE $where", $params);
		
        result();
	} 	
	
	if($action=="deletepositionbyid")
	{		
		$positionid = urldecode($_GET["positionid"]);
		if ( $positionid == "" )
		{
			echo "Result:6";
			die();		
            result(R_UNSPECIFIED_TRIP);
		}
		
		$locked = 0;
        $result = $db->exec_sql("SELECT Locked FROM trips A1 " .
                                "INNER JOIN positions A2 ON A2.FK_TRIPS_ID=A1.ID " .
                                "WHERE A2.FK_Users_ID = ? and A2.ID = ?",
                                $userid, $positionid);
        if ( $row=$result->fetch() )
		{
			 $locked = $row['Locked'];			 
			 if ( $locked == 1 )
			 {
                result(R_LOCKED_TRIP);
			 }
		}
		else
		{
            result(R_MISSING_TRIP);
		}	 	
		
			
        $db->exec_sql("DELETE FROM positions WHERE ID='?' AND FK_USERS_ID='?'",
                      $positionid, $userid);
						
		
        result();
	}
	

	
	
	
	if($action=="findclosestpositionbytime")
	{	
		$date = urldecode($_GET["date"]);
		
		if ( $date == "" )
		 {
			echo "Result:6"; // date not specified
		 	die();
		 }
		 
		$sql = "SELECT ID,dateoccurred FROM positions ";
		$sql.= "WHERE dateoccurred = (SELECT MIN(dateoccurred) ";
		$sql.= "FROM positions WHERE ABS(TIMESTAMPDIFF(SECOND,'$date',dateoccurred))= ";
		$sql.= "(SELECT MIN(ABS(TIMESTAMPDIFF(SECOND,'$date',dateoccurred))) ";
		$sql.= "FROM positions WHERE FK_USERS_ID='$userid') AND FK_USERS_ID='$userid') ";
		$sql.= "AND FK_USERS_ID='$userid'";
	
		$result=mysql_query($sql);	
		
		if ( $row=mysql_fetch_array($result) )
		{
			echo "Result:0|".$row['ID']."|".$row['dateoccurred'];
		}						
		else
			echo "Result:7"; // No positions from user found

		
		die();		
	} 	
	
	
	
	if($action=="findclosestpositionbyposition")
	{	
		
		$lat = $_GET["lat"];
		$long = $_GET["long"];
		
		if ( $lat == "" || $long== "" )
		 {
			echo "Result:6"; // position not specified
		 	die();
		 }
		 
		
		$sql = "SELECT(  DEGREES(     ACOS(        SIN(RADIANS( latitude )) * SIN(RADIANS(".$lat.")) +";
		$sql.= "COS(RADIANS( latitude )) * COS(RADIANS(".$lat.")) * COS(RADIANS( longitude - ".$long.")) ) * 60 * 1.1515 ";
		$sql.= ")  ) AS distance,ID, dateoccurred FROM positions WHERE FK_Users_ID = '$userid' order by distance asc limit 0,1";
					
		$result=mysql_query($sql);	
		
		if ( $row=mysql_fetch_array($result) )
		{
			echo "Result:0|".$row['ID']."|".$row['dateoccurred']."|".$row['distance'];
		}						
		else
			echo "Result:7"; // No positions from user found
			
		

		die();		
	} 
	
	
	
	if($action=="findnearbypushpins")
	{	
		
		$lat = $_GET["lat"];
		$long = $_GET["long"];
		$radius = $_GET["radius"];
					
		if ( $lat == "" || $long== "" )
		{
			echo "Result:6"; // position not specified
		 	die();
		}
		
		
		if ( $radius == "" )
		   $radius = 50.0;		  
		 
		$sql = "SELECT latitude, longitude, distance,  positioncomments, positionimageurl, tripname  FROM ( SELECT z.latitude, z.longitude, p.radius, p.distance_unit ";
    $sql.= "* DEGREES(ACOS(COS(RADIANS(p.latpoint)) * COS(RADIANS(z.latitude)) * COS(RADIANS(p.longpoint - z.longitude)) + SIN(RADIANS(p.latpoint)) ";
		$sql.= "* SIN(RADIANS(z.latitude)))) AS distance,  z.comments AS positioncomments, z.imageurl as positionimageurl, TT.name as tripname FROM positions AS z   LEFT JOIN trips TT on TT.ID = z.fk_trips_id JOIN (   /* these are the query parameters */ ";
		$sql.= "SELECT  ".$lat."  AS latpoint,  ".$long." AS longpoint, ".$radius." AS radius,      111.045 AS distance_unit ) AS p ON 1=1 WHERE ";
		$sql.= "z.fk_users_id='$userid' and ( z.comments <>'' or z.imageurl<>'') ";
		
		if ( $tripname != "" )
		{
			$tripid = "";
			
			$result=mysql_query("Select ID FROM trips WHERE FK_Users_ID = '$userid' and name='$tripname'");
			
			if ( $row=mysql_fetch_array($result) )
			 		$tripid=$row['ID'];					 		
			
			if ( $tripid <> "" )
		 		$sql.= "and ( z.fk_trips_id<>".$tripid." or z.fk_trips_id is null ) "; 
		}
		 
		$sql.= "and z.latitude BETWEEN p.latpoint  - (p.radius / p.distance_unit) AND p.latpoint  + (p.radius / p.distance_unit) ";
    $sql.= "AND z.longitude BETWEEN p.longpoint - (p.radius / (p.distance_unit * COS(RADIANS(p.latpoint)))) AND p.longpoint + (p.radius / (p.distance_unit * COS(RADIANS(p.latpoint)))) ";
 		$sql.= ") AS d WHERE distance <= radius ORDER BY distance LIMIT 15";
 		 								
		$result=mysql_query($sql);
		
		$output = ""; 		
				
		while( $row=mysql_fetch_array($result) )
		{
			$output.=$row['latitude']."|".$row['longitude']."|".$row['distance']."|".$row['positioncomments']."|".$row['positionimageurl']."|".$row['tripname']."\n";
		}						
		
		echo "Result:0|$output";			
			
		
		die();		
	}
	
	if($action=="findclosestbuddy")
	{	
		$result=mysql_query("Select latitude,longitude FROM positions WHERE fk_users_id='$userid' order by dateoccurred desc limit 0,1");
		
		if ( $row=mysql_fetch_array($result) )
		{	
			/*
			$sql = "SELECT(  DEGREES(     ACOS(        SIN(RADIANS( latitude )) * SIN(RADIANS(".$row['latitude'].")) +";
			$sql.= "COS(RADIANS( latitude )) * COS(RADIANS(".$row['latitude'].")) * COS(RADIANS( longitude - ".$row['longitude'].")) ) * 60 * 1.1515 ";
			$sql.= ")  ) AS distance,dateoccurred,fk_users_id FROM positions WHERE FK_Users_ID <> '$userid' order by distance asc limit 0,1";
						
			$result=mysql_query($sql);	
			
			if ( $row=mysql_fetch_array($result) )
			{
				echo "Result:0|".$row['distance']."|".$row['dateoccurred']."|".$row['fk_users_id'];
			}						
			else
				echo "Result:7"; // No positions from other users found
			*/
			
			echo "Result:7";			

		}
		else
			echo "Result:6"; // No positions for selected user

		die();		
	} 
	
	
	
	
	
	// Trips
	if ($action=="gettripinfo")
	{
		if ( $tripname == "" )
		{
			echo "Result:6"; // trip not specified
			die();
		}
		
        $result = $db->exec_sql("SELECT ID, Locked, Comments FROM trips " .
                                "WHERE FK_Users_ID = '?' and name='?'",
                                $userid, $tripname);
		if ( $row=$result->fetch() )
		{
			 $output.=$row['ID']."|".$row['Locked']."|".$row['Comments']."\n";    		  
		}
		else
		{
		 	  echo "Result:7"; // trip not found.
				die();					
		}					
    		
		echo "Result:0|$output";		
		die();
	}
	
	if ($action=="gettripfull" || $action=="gettriphighlights")
	{
		if ( $tripname == "" )
		{
			echo "Result:6"; // trip not specified
			die();
		}
		
		$tripid = "";
		$result=mysql_query("Select ID FROM trips WHERE FK_Users_ID = '$userid' and name='$tripname'");
		if ( $row=mysql_fetch_array($result) )
		{
			 $tripid=$row['ID'];					 		
		}
		else
		{
		 	  echo "Result:7"; // trip not found.
				die();					
		}		
				
    $output = ""; 		
    $result = mysql_query("select latitude,longitude,ImageURL,Comments,A2.URL IconURL, dateoccurred, A1.ID, A1.Altitude, A1.Speed, A1.Angle  from positions A1 left join icons A2 on A1.FK_Icons_ID=A2.ID where fk_trips_id='$tripid' order by dateoccurred");
    while( $row=mysql_fetch_array($result) )
    {
    	$output.=$row['latitude']."|".$row['longitude']."|".$row['ImageURL']."|".$row['Comments']."|".$row['IconURL']."|".$row['dateoccurred']."|".$row['ID']."|".$row['Altitude']."|".$row['Speed']."|".$row['Angle']."\n";    		  
    }
    		
		echo "Result:0|$output";		
		die();
	}
	
		
	if( $action=="gettriplist")
	{
		$order = $_GET["order"];

		
		$triplist = "";
		$sql = "SELECT A1.locked, A1.comments, A1.name, 
		(select min( A2.dateoccurred ) from positions A2 where A2.FK_TRIPS_ID=A1.ID) AS startdate, 
		(select max( A2.dateoccurred ) from positions A2 where A2.FK_TRIPS_ID=A1.ID) AS enddate, 
		(SELECT TIMEDIFF(max( A2.dateoccurred ),min( A2.dateoccurred )) from positions A2 where A2.FK_TRIPS_ID=A1.ID) AS totaltime,
		(select count(*) from positions A2 where A2.FK_TRIPS_ID=A1.ID AND A2.Comments is not null) as totalcomments,
		(select count(*) from positions A2 where A2.FK_TRIPS_ID=A1.ID AND A2.ImageURL is not null) as totalimages,
		(select IFNULL(max(speed), 0) from positions A2 where A2.FK_TRIPS_ID=A1.ID) as maxspeed,
		(select IFNULL(min(altitude), 0) from positions A2 where A2.FK_TRIPS_ID=A1.ID) as minaltitude,		
		(select IFNULL(max(altitude), 0) from positions A2 where A2.FK_TRIPS_ID=A1.ID) as maxaltitude
		from trips A1 where A1.FK_USERS_ID='$userid' ";
		
		$datefrom = urldecode($_GET["df"]);
		$dateto = urldecode($_GET["dt"]);
		
		if ( $datefrom != "" )
			$sql.=" and (select min( A2.dateoccurred ) from positions A2 where A2.FK_TRIPS_ID=A1.ID)>='$datefrom' ";
		if ( $dateto != "" )
			$sql.=" and (select min( A2.dateoccurred ) from positions A2 where A2.FK_TRIPS_ID=A1.ID)<='$dateto' ";			
		
		
		if ( $order == "" || $order == "0" )
			$sql.= " order by name";
		else
			$sql.= " order by startdate desc";
		
		

		$result = mysql_query($sql);					
		
		while( $row=mysql_fetch_array($result) )
		{
			$triplist.=$row['name']."|"
			.$row['startdate']."|"
			.$row['enddate']."|"
			.$row['comments']."|"
			.$row['locked']."|"
			.$row['totaltime']."|"
			.$row['totalcomments']."|"
			.$row['totalimages']."|"
			.$row['maxspeed']."|"
			.$row['minaltitude']."|"
			.$row['maxaltitude']
			."\n";			
		}

		$triplist = substr($triplist, 0, -1);		  
		echo "Result:0|$triplist";
		die();
	}
	
	if ( $action=="updatetripdata" )
	{				
		 
		 $tripid = "";
		 $locked = 0;
        $tripid = test_trip();
		 
		 if ( isset($_GET["comments"]))
		 {
				$comments = urldecode($_GET["comments"]);
		 			 
		 		if (!$comments)
                $comments = null;
            $db->exec_sql("UPDATE trips SET comments = ? " .
                          "WHERE id = ? AND FK_Users_ID = ?",
                          $comments, $tripid, $userid);
		 }
		 	 		 		 
        result();
	}	
	
	if ( $action=="updatelocking" )
	{				
        $tripid = test_trip(null, true);
		 $locked = urldecode($_GET["locked"]);
		 
        $db->exec_sql("UPDATE trips SET locked=? WHERE id=?",
                      $tripid);
        result();
	}
	
	if ( $action=="deletetrip" )
	{		
        $tripid = test_trip();
        $db->exec_sql("DELETE FROM positions WHERE FK_Trips_ID=? AND FK_Users_ID = ?",
                      $tripid, $userid);
        $db->exec_sql("DELETE FROM trips WHERE ID=? AND FK_Users_ID = ?",
                      $tripid, $userid);
        result();
	}
	
	if ( $action=="addtrip" )
	{				
		 if ( $tripname == "" )
		 {
            result(R_TRIP_UNSPECIFIED);
		 }
		 	 		 
        $tripid = get_trip($tripname);
        if (!is_null($tripid))
        {
            result(10); // new name already exists
        }
        $db->exec_sql("INSERT INTO trips (Name, FK_Users_ID) VALUES (?, ?)",
                      $tripname, $userid);
        result();
	}	
	
	if ( $action=="renametrip" )
	{				
		 
		 $newname = $_GET["newname"];		 
		 if ( $newname == "" )
		 {
			echo "Result:9"; // new name not specified
		 	die();
		 }
		 
        $tripid = test_trip();
        $new_id = get_trip($newname);
        if (!is_null($new_id))
		 {
		 		echo "Result:10"; // new name already exists
		 		die();
		 }		
		 		 
        $db->exec_sql("UPDATE trips SET Name = ? WHERE ID = ?",
                      $newname, $tripid);
        result();
	}	

    function get_trip($name=null, $allow_locked=false)
    {
        global $db, $userid;
        $result = $db->exec_sql("Select ID, Locked FROM trips WHERE FK_Users_ID = '?' and name='?'",
                                $userid, $name);
        if ($row=$result->fetch())
        {
            if (!&allow_locked && $row['Locked'] == 1)
                return false;
            else
                return $row['ID'];
        }
        else
        {
            return null;
        }
    }


    function test_trip($name=null, $allow_locked=false)
    {
        global $tripname;
        if (is_null($name))
            $name = $tripname;
        if (!$name)
        {
            result(R_TRIP_UNSPECIFIED); // trip not specified
        }
        $tripid = get_trip($name)
        if ($tripid === false)
        {
            result(R_LOCKED_TRIP);
        }
        elseif (is_null($tripid))
        {
            result(R_MISSING_TRIP); // trip not found.
        }
        else
        {
            return $tripid;
        }
    }

    function get_nulled($name)
    {
        $value = urldecode($_GET[$name]);
        if ($value !== "")
            return $value;
        else
            return null;
    }

    function result($id=R_OK, $message="")
    {
        if ($message)
            $message = "|$message"
        echo "Result:$id$message";
        exit();
    }

?>

