<?php

class MySql
{

    private static $server;

    private static $user;

    private static $pwd;

    private static $bdd;

    private static $id_link;

    private static $error;

    private static $ernum;

    public static function getIdLink ()
    {
        if (! isset(self::$id_link) || (self::$id_link == 0))
            self::Connect();
        return self::$id_link;
    }

    public static function Init ($server, $user, $pwd, $bdd)
    {
        self::$id_link = false;
        
        self::$server = $server;
        self::$user = $user;
        self::$pwd = $pwd;
        self::$bdd = $bdd;
    }

    public static function Connect ()
    {
        if (isset(self::$id_link) && (self::$id_link > 0)) {
            return true;
        } else {
            if (isset(self::$server) && isset(self::$user) && isset(self::$pwd) && isset(self::$bdd)) {
                try {
                    self::$id_link = @mysql_connect(self::$server, self::$user, self::$pwd);
                    if (self::$id_link === false) {
                        throw new Exception("SQL_CONNECTION_FAILED");
                    } else {
                        if (@mysql_select_db(self::$bdd, self::$id_link)) {
                            mysql_query("SET NAMES UTF8", self::$id_link);
                            return true;
                        } else {
                            throw new Exception("SQL_DB_SELECTION_FAILED");
                        }
                    }
                } catch (Exception $error) {
                    self::LogError($error);
                }
            }
        }
        return false;
    }

    public static function Disconnect ()
    {
        if (self::$id_link > 0) {
            mysql_close(self::$id_link);
        }
    }

    public static function LogError ($error, $query = "")
    {
        ChromePhp::log($error->getMessage());
        if($query != "") {
            ChromePhp::log($query);
            ChromePhp::log(self::$ernum . ' : ' . self::$error);
        }
    }

    public static function EscapeStr ($string)
    {
        self::Connect();
        $string = strtr( $string, array( "’" => "'" ) );
        return mysql_real_escape_string($string, self::$id_link);
    }

    private $query;

    private $results;

    private $row_obj;

    public function MySql ($query_str)
    {
        self::Connect();
        if ($query_str != '') {
            $this->Query($query_str);
        }
    }

    public function Query ($query_str)
    {
        $this->results = false;
        if (self::Connect()) {
            $this->query = $query_str;
            try {
                $this->results = @mysql_query($this->query, self::$id_link);
                if ($this->results === false) {
                    self::$error = mysql_error(self::$id_link);
                    self::$ernum = mysql_errno(self::$id_link);
                    throw new Exception("SQL_QUERY_FAILED");
                } else {
                    return $this->results;
                }
            } catch (Exception $error) {
                $this->results = false;
                self::LogError($error, $this->query);
            }
        }
        return false;
    }

    public function Success ()
    {
        return $this->results;
    }

    /**
     * Retourne la ligne suivante des résultats de la requete
     *
     * @param string $className            
     * @param array $params            
     * @return object boolean
     */
    public function Fetch ($className = '', $params = null)
    {
        if (self::Connect() && $this->results !== false && $this->results > 1) {
            if ($className == '')
                $className = 'stdClass';
            
            if ($params !== null && (is_array($params) && count($params) > 0)) {
                $this->row_obj = mysql_fetch_object($this->results, $className, $params);
            } else {
                $this->row_obj = mysql_fetch_object($this->results, $className);
            }
            return $this->row_obj;
        }
        return false;
    }

    /**
     * Déplace le pointeur interne pour la lecture des résultats
     */
    public function SetPointer ($int_pos)
    {
        $result = false;
        if ($this->results !== false && $this->results > 1) {
            if (($int_pos < mysql_num_rows($this->results)) && ($int_pos > - 1)) {
                $result = mysql_data_seek($this->results, $int_pos);
            }
        }
        return $result;
    }

    public function GetNbResults ()
    {
        if ($this->results !== false && $this->results > 1) {
            return (int) mysql_num_rows($this->results);
        }
        return 0;
    }

    public function GetAffectedRows ()
    {
        return (int) mysql_affected_rows(self::$id_link);
    }

    public function GetInsertedId ()
    {
        return (int) mysql_insert_id(self::$id_link);
    }

    public function GetQuery ()
    {
        return $this->query;
    }

    public function FreeRez ()
    {
        if ($this->results !== false)
            mysql_free_result($this->results);
    }
}