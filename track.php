<?php
    /**
     * Script to provide (new) positions easily as JSON data.
     *
     * To support changing the track without reloading it is necessary to get
     * the data separately which this script allows.
     *
     * Has a trip or tripid parameter to define the trip name or trip ID and
     * optionally a start parameter to allow querying data after a certain
     * point.
     */

    header('Content-Type: application/json');

    session_start();

    require_once("database.php");

    if (isset($_REQUEST['userid']))
    {
        $user_id = $_REQUEST['userid'];
        if (!is_numeric($user_id))
            error('malformed-user', 'user id must be a numeric id');
    }

    if ($public_page != "yes")
    {
        if (!isset($_SESSION["ID"]))
            error('logged-out', 'You are not logged in');
        elseif ($_SESSION["ID"] !== $user_id)
            error('not-owned', 'You do not own the trip');
    }

    try {
        $db = connect();
    } catch (PDOException $e) {
        error('database-error', $e->getMessage());
    }

    $params = array();

    if (isset($_REQUEST['trip']))
    {
        if (isset($_REQUEST['tripid']))
        {
            error('trip-ambiguous', 'Name and ID provided');
        }
        $trip_stmt = $db->exec_sql("SELECT `ID` FROM `trips` " .
                                   "WHERE `trips`.`Name` = ?",
                                   $_REQUEST['trip']);
        $trip_ids = array();
        while ($trip = $trip_stmt->fetch())
        {
            $trip_ids[] = $trip['ID'];
        }
    }
    elseif (isset($_REQUEST['tripid']))
    {
        $trip_ids = array($_REQUEST['tripid']);
        if ($trip_ids[0] === '*')
            $trip_ids = true;
        elseif ($trip_ids[0] === '-1')
        {
            $trip_ids = false;
            if (!isset($_REQUEST['userid']))
            {
                error('trip-none-user-missing',
                      'User required if the trip id is empty');
            }
        }
    }
    else
    {
        error('trip-missing', 'Neither name or ID is set');
    }

    $onetrip = isset($_REQUEST['onetrip']) || $trip_ids === false;
    if ($onetrip)
    {
        if (!isset($user_id))
            error('user-missing', 'If onetrip is set, userid must be too');
        elseif ($trip_ids !== true && $trip_ids !== false)
            error('trip-specific', 'If onetrip is set, trip cannot be specific');
    }
    elseif ($trip_ids === true)
    {
        if (!isset($user_id))
            error('user-missing', 'If all positions are requested, userid must set');
        $trip_ids = $db->exec_sql("SELECT `ID` FROM `trips` WHERE `FK_Users_ID` = ?",
                                  $user_id)->fetchAll(PDO::FETCH_COLUMN, 0);
        $trip_ids[] = "-1";
    }

    if (isset($_REQUEST['start']))
    {
        // TODO: > or >= ?
        $when = "`positions`.`DateOccurred` > ?";
        $start = $_REQUEST['start'];
    }
    else
    {
        $when = "";
    }

    // TODO: The URL parameters at the moment do not allow multiple trips as
    //       it is not possible to associate usernames/trips/startdates.
    //       But the loop below should work fine if we support that.

    $user_ids = array();
    $trips_data = array();
    if ($onetrip)
    {
        $pos_entries = query_positions($trip_ids);
        $trips_data["-1"] = array("name" => "",
                                  "uid" => $user_id,
                                  "pos" => $pos_entries);
        $user_ids = array($user_id);
    }
    else
    {
        $temp_trip_ids = array();
        foreach ($trip_ids as $trip_id)
        {
            if (!in_array($trip_id, $trips_data))
            {
                $pos_entries = query_positions($trip_id);
                $trips_data[$trip_id] = array('pos' => $pos_entries);
                if ($trip_id !== "-1")
                {
                    $temp_trip_ids[] = $trip_id;
                }
                else
                {
                    $trips_data[$trip_id]["uid"] = $user_id;
                    $trips_data[$trip_id]["name"] = "";
                }
            }
        }

        $temp_trips_data = query_multiple("SELECT `ID`, `FK_Users_ID`, `Name` " .
                                          "FROM `trips`",
                                          "`ID` = ?",
                                          $temp_trip_ids);
        $temp_user_ids = array();
        foreach ($temp_trips_data as $temp_trip_data)
        {
            $trip_data =& $trips_data[$temp_trip_data['ID']];
            $trip_data['name'] = $temp_trip_data['Name'];
            $trip_data['userid'] = $temp_trip_data['FK_Users_ID'];
            $temp_user_ids[$temp_trip_data['FK_Users_ID']] = true;
        }
        $user_ids = array_keys($temp_user_ids);
    }

    $temp_users_data = query_multiple("SELECT `ID`, `username` FROM `users`",
                                      "`ID` = ?",
                                      $user_ids);
    $users_data = array();
    foreach ($temp_users_data as $temp_user_data)
    {
        $users_data[$temp_user_data['ID']] = array(
            'name' => $temp_user_data['username']
        );
    }

    echo json_encode(array('status' => 'success', 'users' => $users_data,
                           'trips' => $trips_data));

    $db = null;

    function query_positions($trip_id)
    {
        global $db, $when, $start, $user_id;
        if ($trip_id === false || $trip_id === "-1")
        {
            $where = "`FK_Trips_ID` IS NULL AND FK_Users_ID = ?";
            $params = array($user_id);
        }
        elseif ($trip_id === true)
        {
            $where = 'FK_Users_ID = ?';
            $params = array($user_id);
        }
        else
        {
            $where = "`FK_Trips_ID` = ?";
            $params = array($trip_id);
        }
        if ($where && $when)
            $where .= " AND ";
        if ($where || $when)
            $where = "WHERE $where";
        if (isset($start))
            $params[] = $start;

        $pos_stmt = $db->exec_sql("SELECT `positions`.*, `icons`.`URL` ".
                             "FROM `positions` " .
                             "LEFT JOIN `icons` ON `positions`.`FK_Icons_ID` = `icons`.`ID`" .
                             "$where $when " .
                             "ORDER BY `positions`.`DateOccurred` LIMIT 5",
                             $params);
        $pos_entries = array();
        while ($pos = $pos_stmt->fetch())
        {
            $pos_entry = array('latitude' => (float) $pos['Latitude'],
                               'longitude' => (float) $pos['Longitude'],
                               'speed' => (float) $pos['Speed'],
                               'altitude' => (float) $pos['Altitude'],
                               // Maybe format so that JS doesn't need to?
                               'timestamp' => $pos['DateOccurred'],
                               );
            if ($pos['Angle'] != "")
                $pos_entry['bearing'] = (float) $pos['Angle'];
            if ($pos['Comments'] != "")
                $pos_entry['comment'] = $pos['Comments'];
            if (!is_null($pos['URL']))
                $pos_entry['iconurl'] = $pos['URL'];
            if (!is_null($pos['ImageURL']))
                $pos_entry['photo'] = $pos['ImageURL'];

            $pos_entries[] = $pos_entry;
        }
        return $pos_entries;
    }

    function query_multiple($statement, $condition, $values)
    {
        global $db;
        $where = array();
        for ($i = 0; $i < count($values); $i++)
        {
            $where[] = $condition;
        }
        $where = implode(" OR ", $where);
        if ($where)
        {
            return $db->exec_sql("$statement WHERE $where",
                                 $values)->fetchAll();
        }
        else
        {
            return array();
        }
    }

    function error($type, $info)
    {
        echo json_encode(array('status' => 'error', 'type' => $type,
                               'info' => $info));
        exit(1);
    }

?>
