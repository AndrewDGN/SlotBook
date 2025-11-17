<?php

$host = 'localhost';         
$user = 'root';              
$pass = '';                  
$db   = 'slotbook_db';       

$mysqli = new mysqli($host, $user, $pass, $db);

if ($mysqli->connect_error) {
    die("Database connection failed: " . $mysqli->connect_error);
}
?>
