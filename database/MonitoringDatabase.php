<?php

class MonitoringDatabase {
    private $username;
    private $password;
    private $database;
    private $server;

    public function __construct($username, $password, $database, $server)
    {
        $this->username = $username;
        $this->password = $password;
        $this->database = $database;
        $this->server = $server;
    }

    public function connect() {
        $conn = mysqli_connect($this->server,$this->username,$this->password,$this->database);
        if ($conn === false) {
            return false;
        }
        else return $conn;
    }
}