<?php

    define("R_OK", 0);
    define("R_UNSPECIFIED_PARAMETER", 6);
    define("R_TRIP_MISSING", 7);
    define("R_TRIP_LOCKED", 8);

    require_once("database.php");
	

    function run($connection)
    {
  $requireddb = urldecode($_GET["db"]);     
  if ( $requireddb == "" || $requireddb < 8 )
  {
            return "Result:5";
  }	
	
	
        $db = connect_save($connection);
        if (is_null($db))
	{
            return "Result:4";
	}
	
	// Check username and password
        $username = $_GET["u"];
        $password = $_GET["p"];
	
	// User not specified
	if ( $username == "" || $password == "" )
	{
            return "Result:3";
	}
	
        $userid = $db->valid_login($username, $password);
        switch ($userid) {
        case NO_USER:
            $userid = $db->create_login($username, $password);
            if ($userid < 0)
                return result(2);
            break;
        case INVALID_CREDENTIALS:
            return result(1);  // user exists, password incorrect.
        case LOCKED_USER:
            return "User disabled. Please contact system administrator";
        }
	
	
	$tripname = urldecode($_GET["tn"]);	
	$action = $_GET["a"];
	
	
	
	
	if ($action=="noop")
	{
            return "Result:0";
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
            $result = $db->exec_sql("SELECT Name FROM icons ORDER BY Name")->fetchAll(PDO::FETCH_COLUMN, 0);
            return success($result);
	}
		
	

        // TODO: As long as this is both using PDO and mysql, start connection here in parallel
        //       mysql is not used before this line so start as late as possible
        if(!@mysql_connect("$connection[host]","$connection[user]","$connection[pass]"))
	{
            return "Result:4";
	}

        mysql_select_db("$connection[name]");


	if($action=="upload")
	{				
		$tripid = 'null';
		$locked = 0;
		
		if ( $tripname != "" )
		{			
			$result=mysql_query("Select ID, Locked FROM trips WHERE FK_Users_ID = '$userid' and name='$tripname'");
			if ( $row=mysql_fetch_array($result) )
			{
				$tripid=$row['ID'];		
				$locked=$row['Locked'];		
			}
			else // Trip doesn't exist. Let's create it.
			{
				mysql_query("Insert into trips (FK_Users_ID,Name) values('$userid','$tripname')");				
				
				$result=mysql_query("Select ID, Locked FROM trips WHERE FK_Users_ID = '$userid' and name='$tripname'");
				if ( $row=mysql_fetch_array($result) )
				{
					$tripid=$row['ID'];							
					$locked=$row['Locked'];		
				}
				
				if ( $tripid == 'null' )
				{
					echo "Result:6"; // Unable to create trip.
					die();					
				}				
			}
		}
		
		
		if ( $locked==1 )
		{
			echo "Result:8"; // Trip is locked
			die();								
		}
	
		$lat = $_GET["lat"];
		$long = $_GET["long"];
		$dateoccurred = urldecode($_GET["do"]);		
		$altitude = urldecode($_GET["alt"]);
		$angle = urldecode($_GET["ang"]);
		$speed = urldecode($_GET["sp"]);		
		$iconname = urldecode($_GET["iconname"]);
		$comments = urldecode($_GET["comments"]);		
		$imageurl = urldecode($_GET["imageurl"]);		
		$cellid = urldecode($_GET["cid"]);		
		$signalstrength = urldecode($_GET["ss"]);		
		$signalstrengthmax = urldecode($_GET["ssmax"]);		
		$signalstrengthmin = urldecode($_GET["ssmin"]);		
	  $batterystatus = urldecode($_GET["bs"]);	
	  $uploadss = urldecode($_GET["upss"]);	
	
		
		$iconid='null';		
		if ($iconname != "" ) 
		{
				$result=mysql_query("Select ID FROM icons WHERE name = '$iconname'");
				if ( $row=mysql_fetch_array($result) )
					$iconid=$row['ID'];							
		}
		

		$sql = "Insert into positions (FK_Users_ID,FK_Trips_ID,latitude,longitude,dateoccurred,fk_icons_id,speed,altitude,comments,imageurl,angle,signalstrength,signalstrengthmax,signalstrengthmin,batterystatus) values('$userid',$tripid,'$lat','$long','$dateoccurred',$iconid,";
			
		if ($speed == "" ) 
			$sql.="null,";
		else
			$sql.="'".$speed."',";					
			
	  if ($altitude == "" ) 
			$sql.="null,";
		else
			$sql.="'".$altitude."',";										
			
		if ($comments == "" ) 
			$sql.="null,";
		else
			$sql.="'".$comments."',";		

		if ($imageurl == "" ) 
			$sql.="null,";
		else
			$sql.="'".$imageurl."',";					
			
		if ($angle == "" ) 
			$sql.="null,";
		else
			$sql.="'".$angle."',";		
			
		if ($uploadss == 1 )
		{
			if ($signalstrength == "" ) 
				$sql.="null,";
			else
				$sql.=$signalstrength.",";								
				
			if ($signalstrengthmax == "" ) 
				$sql.="null,";
			else
				$sql.=$signalstrengthmax.",";								
				
			if ($signalstrengthmin == "" ) 
				$sql.="null,";
			else
				$sql.=$signalstrengthmin.",";														
		}
		else
			$sql.="null,null,null,";
			
		if ($batterystatus == "" ) 
			$sql.="null";
		else
			$sql.=$batterystatus;																	

			
			
		$sql.=")";
		

		$result = mysql_query($sql);	
		if (!$result) 
		{
			echo "Result:7|".mysql_error();		
			die();		
		}
		
		$upcellext = urldecode($_GET["upcellext"]);				
		if ($upcellext == 1 && $cellid != "" )
		{
			$sql = "Insert into cellids(cellid,latitude,longitude,signalstrength,signalstrengthmax,signalstrengthmin) values ('$cellid','$lat','$long',";
			
			if ($signalstrength == "" ) 
				$sql.="null,";
			else
				$sql.=$signalstrength.",";								
			
			if ($signalstrengthmax == "" ) 
				$sql.="null,";
			else
				$sql.=$signalstrengthmax.",";								
			
			if ($signalstrengthmin == "" ) 
				$sql.="null";
			else
				$sql.=$signalstrengthmin;							
				
			$sql.=")";			
					
			mysql_query($sql);
		}

		
		echo "Result:0";		
		die();		
	}
	
	
	
	
	
	
	
	
	
	
		
	if($action=="updatepositiondata" || $action=="updateimageurl")
	{		
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
            $row = $db->exec_sql("SELECT Locked FROM trips INNER JOIN positions ON positions.FK_Trips_ID=trips.ID WHERE positions.FK_Users_ID = ? AND positions.ID=?", $userid, $id)->fetch();
            if (!$row) {
                return result(R_TRIP_MISSING);  // trip not found.
            } else if ($ignorelocking == 0 && $row['Locked'] == 1) {
                return result(R_TRIP_LOCKED);
            }
            $values = array();
		
		if ( isset($_GET["imageurl"]))
		{		
                $values["ImageURL"] = get_nulled("imageurl");
	  }
	  
	  if ( isset($_GET["comments"]))
		{				
                $values["Comments"] = get_nulled("comments");
	  }	 
		 		 	 		 		 
            $names = "";
            $parameters = array();
            foreach ($values as $name => $value) {
                $names .= "$name = ?";
                $parameters[] = $value;
            }
            $parameters[] = $id;
            $parameters[] = $userid;
            $db->exec_sql("UPDATE positions SET $names WHERE ID=? AND FK_Users_ID=?", $parameters);
            return success();
	}
	
	
	if($action=="delete")
	{	
            $where = array("FK_Users_ID = ?");
            $params = array($userid);
            if ($tripname === "<None>") {
                $where[] = "FK_Trips_ID is NULL";
            } else {
                $tripid = test_trip($db, $userid, $tripname);
                if (!is_numeric($tripid))
                    return $tripid;
                $params[] = $tripid;
                $where[] = "FK_Trips_ID = ?";
            }
		$datefrom = urldecode($_GET["df"]);
		$dateto = urldecode($_GET["dt"]);
		
            if ($datefrom != "") {
                $where[] = "DateOccurred >= ?";
                $params[] = $datefrom;
            }
            if ($dateto != "") {
                $where[] = "DateOccurred <= ?";
                $params[] = $dateto;
            }

			
						
            $where = implode(" AND ", $where);
            $db->exec_sql("DELETE FROM positions WHERE $where", $params);
		
            return success();
	} 	
	
	if($action=="deletepositionbyid")
	{		
		$positionid = urldecode($_GET["positionid"]);
		if ( $positionid == "" )
		{
			echo "Result:6";
			die();		
		}
		
		$locked = 0;
            $result = $db->exec_sql("SELECT Locked FROM trips " .
                                    "INNER JOIN positions ON positions.FK_Trips_ID=trips.ID " .
                                    "WHERE positions.FK_Users_ID=? and positions.ID=?",
                                    $userid, $positionid);
            if ($row=$result->fetch())
		{
			 $locked = $row['Locked'];			 
			 if ( $locked == 1 )
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
		
			
            $db->exec_sql("DELETE FROM positions WHERE ID=? AND FK_USERS_ID=?",
                          $positionid, $userid);
						
		
            return success();
	}
	

	
	
	
	if($action=="findclosestpositionbytime")
	{	
		$date = urldecode($_GET["date"]);
		
		if ( $date == "" )
		 {
			echo "Result:6"; // date not specified
		 	die();
		 }
		 
            $row = $db->exec_sql("SELECT ID, DateOccurred FROM positions WHERE " .
                                 "ABS(TIMESTAMPDIFF(SECOND,:date,DateOccurred))=(" .
                                     "SELECT MIN(ABS(TIMESTAMPDIFF(SECOND,:date,DateOccurred))) FROM positions WHERE FK_Users_ID=:user)" .
                                 " AND FK_Users_ID=:user", array("date" => $date, "user" => $userid))->fetch();
            if ($row)
                return success(array($row["ID"], $row["DateOccurred"]));
            else
                return result(R_TRIP_MISSING); // No positions from user found
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
		 
		
            $sql = "SELECT (DEGREES(ACOS(SIN(RADIANS(latitude)) * SIN(RADIANS(:lat)) +";
            $sql.= "COS(RADIANS(latitude)) * COS(RADIANS(:lat)) * COS(RADIANS(longitude - :long))) * 60 * 1.1515 ";
            $sql.= ")) AS distance, ID, DateOccurred FROM positions WHERE FK_Users_ID = :user ORDER BY distance ASC LIMIT 0,1";
					
            $row = $db->exec_sql($sql, array("lat" => $lat, "long" => $long, "user" => $userid))->fetch();
            if ($row) {
                return success(array($row["ID"], $row["DateOccurred"], $row["distance"]));
            } else {
                return result(R_TRIP_MISSING); // No positions from user found
            }
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
                                    "WHERE FK_Users_ID=? and name=?",
                                    $userid, $tripname);
            if ($row=$result->fetch())
		{
                return success(array($row['ID'], $row['Locked'], "$row[Comments]\n"));
		}
		else
		{
		 	  echo "Result:7"; // trip not found.
				die();					
		}					
    		
	}
	
	if ($action=="gettripfull" || $action=="gettriphighlights")
	{
            $tripid = test_trip($db, $userid, $tripname, true);
            if (!is_numeric($tripid))
                return $tripid;
				
    $output = ""; 		
            $result = $db->exec_sql("SELECT Latitude, Longitude, ImageURL, Comments, icons.URL IconURL, DateOccurred, positions.ID, Altitude, Speed, Angle " .
                                    "FROM positions LEFT JOIN icons on positions.FK_Icons_ID=icons.ID WHERE FK_Trips_ID=? ORDER BY DateOccurred", $tripid);
            while ($row = $result->fetch()) {
                $output .= "$row[Latitude]|$row[Longitude]|$row[ImageURL]|$row[Comments]|$row[IconURL]|$row[DateOccurred]|$row[ID]|$row[Altitude]|$row[Speed]|$row[Angle]\n";
            }
            return success($output);
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
		 if ( $tripname == "" )
		 {
			echo "Result:6"; // trip not specified
		 	die();
		 }
		 
		 $tripid = "";
		 $locked = 0;
		 $result=mysql_query("Select ID, Locked FROM trips WHERE FK_Users_ID = '$userid' and name='$tripname'");
		 if ( $row=mysql_fetch_array($result) )
		 {
			 $tripid=$row['ID'];		
			 $locked = $row['Locked'];
			 
			 if ( $locked == 1 )
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
		 
	 
		 $sql = "Update trips set ";
		 
		 if ( isset($_GET["comments"]))
		 {
				$comments = urldecode($_GET["comments"]);
		 			 
		 		if ( $comments != "" )
					$sql.=" comments='$comments',";
				else
					$sql.=" comments=null,";				
		 }
		 	 		 		 
		 $sql.="id=id where id='$tripid' AND FK_Users_ID = '$userid'";
		 		 
		 mysql_query($sql);		 	 
		 echo "Result:0";
  	 die();			 	
	}	
	
	if ( $action=="updatelocking" )
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
		 
		 $locked = urldecode($_GET["locked"]);
		 
		 $sql = "Update trips set locked='$locked' where id='$tripid' AND FK_Users_ID = '$userid'";
		 		 		 		 
		 mysql_query($sql);		 	 
		 echo "Result:0";
  	 die();			 	
	}
	
	if ( $action=="deletetrip" )
	{		
            $tripid = test_trip($db, $userid, $tripname);
            if (!is_numeric($tripid))
                return $tripid;
            try {
                $db->beginTransaction();
                $db->exec_sql("DELETE FROM positions WHERE FK_Trips_ID=? AND FK_Users_ID = ?",
                              $tripid, $userid);
                $db->exec_sql("DELETE FROM trips WHERE ID=? AND FK_Users_ID = ?",
                              $tripid, $userid);
            } catch (Exception $e) {
                $db->rollback();
            }
            return success();
	}
	
	if ( $action=="addtrip" )
	{				
		 if ( $tripname == "" )
		 {
			echo "Result:6"; // trip not specified
		 	die();
		 }
		 	 		 
		 mysql_query("Insert into trips (name,fk_users_id) values ('$tripname','$userid')");		 	 
		 echo "Result:0";
  	 die();			 	
	}	
	
	if ( $action=="renametrip" )
	{				
		 if ( $tripname == "" )
		 {
			echo "Result:6"; // trip not specified
		 	die();
		 }
		 
		 $newname = $_GET["newname"];		 
		 if ( $newname == "" )
		 {
			echo "Result:9"; // new name not specified
		 	die();
		 }
		 
		 
		 $locked = 0;
		 $result=mysql_query("Select Locked FROM trips WHERE FK_Users_ID = '$userid' and name='$tripname'");
		 if ( $row=mysql_fetch_array($result) )
		 {
			 $locked = $row['Locked'];
			 
			 if ( $locked == 1 )
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
		 
		 
		 $result=mysql_query("Select ID FROM trips WHERE FK_Users_ID = '$userid' and name='$newname'");			
		 if ( $row=mysql_fetch_array($result) )
		 {
		 		echo "Result:10"; // new name already exists
		 		die();
		 }		
		 		 
		 mysql_query("Update trips set name='$newname' where name='$tripname' AND FK_Users_ID = '$userid'");		 	 
		 echo "Result:0";
  	 die();			 	
	}	
    }

    // Run by default when included/required, unless __norun is set to true
    if (!isset($__norun) || !$__norun) {
        echo run(toConnectionArray($DBIP, $DBNAME, $DBUSER, $DBPASS));
    }

    function get_trip($db, $userid, $name, $allow_locked=false)
    {
        $result = $db->exec_sql("SELECT ID, Locked FROM trips WHERE FK_Users_ID = ? and Name = ?",
                                $userid, $name);
        if ($row=$result->fetch()) {
            if (!$allow_locked && $row['Locked'] == 1)
                return false;
            else
                return $row['ID'];
        } else {
            return null;
        }
    }


    function test_trip($db, $userid, $name, $allow_locked=false)
    {
        if (!$name) {
            return result(R_UNSPECIFIED_PARAMETER); // trip not specified
        }
        $tripid = get_trip($db, $userid, $name, $allow_locked);
        if ($tripid === false) {
            return result(R_TRIP_LOCKED);
        } elseif (is_null($tripid)) {
            return result(R_TRIP_MISSING); // trip not found.
        } else {
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

    function success($message="")
    {
        return result(R_OK, $message);
    }

    function result($id=R_OK, $message="")
    {
        if (is_array($message))
            $message = implode("|", $message);
        if ($message)
            $message = "|$message";
        return "Result:$id$message";
    }

?>

