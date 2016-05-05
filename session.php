<?php

    session_start();

    require_once("util.php");
    require_once("database.php");

    $success = false;
    if (!installation_finished())
    {
        $db = null;
    }

    if (!isset($db))
    {
        $db = connect_save();
    }

    if (!is_null($db))
    {
        $username = $_POST["username"];
        $password = $_POST["password"];
        if (isset($username) && isset($password))
        {
            $login_id = $db->valid_login($username, $password);
            if ($login_id >= 0)
            {
                $_SESSION['ID'] = $login_id;
                $success = true;
            }
        }
    }
    $db = null;  // Close database

    if ($success)
    {
        header('Location: ' . $siteroot);
    }
    else
    {
        header('Location: ' . $siteroot . "login.php");
    }
?>
