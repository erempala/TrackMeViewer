<?php

    if (!isset($no_config) || !$no_config)
        require_once("config.php");

    define('NO_USER', -1);
    define('LOCKED_USER', -2);
    define('INVALID_CREDENTIALS', -3);


    // Database related functions
    function toConnectionArray($DBIP, $DBNAME, $DBUSER, $DBPASS)
    {
        return array('host' => $DBIP, 'name' => $DBNAME,
                     'user' => $DBUSER, 'pass' => $DBPASS);
    }

    function connect($connection=false)
    {
        if ($connection === false)
        {
            global $DBIP, $DBNAME, $DBUSER, $DBPASS;
            $connection = toConnectionArray($DBIP, $DBNAME, $DBUSER, $DBPASS);
        }
        return new TrackMePDO("mysql:host=$connection[host];dbname=$connection[name]",
                              $connection["user"], $connection["pass"]);
    }

    function connect_save($connection=false)
    {
        try {
            return connect($connection);
        } catch (PDOException $e) {
            return null;
        }
    }

    class TrackMePDO extends PDO {

        function get_count($statement)
        {
            $stmt = $this->prepare("SELECT COUNT(*) FROM $statement");
            $stmt->execute();
            $result = $stmt->fetchAll();
            return $result[0][0];
        }

        function _log($obj=null)
        {
            if (is_a($obj, "PDOStatement"))
            {
                $query = $obj->queryString;
            }
            else
            {
                if (is_null($obj))
                    $query = null;
                else
                    $query = $obj;
                $obj = $this;
            }
            if (!is_null($query))
                error_log("Error in query: $query");
            $e = $obj->errorInfo();
            error_log("SQL Error info: $e[0]:$e[1]:$e[2]");
        }

        function exec_sql()
        {
            $args = func_get_args();
            $statement = $args[0];
            if (count($args) == 1)
                $args = array();
            elseif (is_array($args[1]))
                $args = $args[1];
            else
                $args = array_slice($args, 1);
            $stmt = $this->prepare($statement);
            if (!$stmt) {
                $this->_log($statement);
                return false;
            }
            foreach ($args as $name => $value) {
                if (is_numeric($name)) {
                    $name++;
                } else {
                    $name = ":$name";
                }
                $stmt->bindValue($name, $value);
            }
            if ($stmt->execute())
                return $stmt;
            else
            {
                $this->_log($stmt);
                return false;
            }
        }

        private function static_hash($password)
        {
            $salt = "trackmeuser";
            return MD5($salt.$password);
        }

        private function verify_password($password, $hash)
        {
            return $hash === $this->static_hash($password);
        }

        function create_login($username, $password)
        {
            $hash = $this->static_hash($password);
            $this->exec_sql("INSERT INTO users (username, password) ".
                            "VALUES (?, ?)",
                            $username, $hash);
            return $this->valid_login($username, $password);
        }

        function valid_login($username, $password, $allow_disabled=false)
        {
            $user = $this->exec_sql("Select ID, password, Enabled " .
                                    "FROM users WHERE username=?",
                                    $username)->fetch();
            if ($user === false)
            {
                return NO_USER;
            }
            elseif ($this->verify_password($password, $user['password']))
            {
                if (!$allow_disabled && $user['Enabled'] == 0)
                    return LOCKED_USER;
                else
                    return $user['ID'];
            }
            else
            {
                return INVALID_CREDENTIALS;
            }
        }
    }
?>
