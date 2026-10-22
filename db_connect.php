<?php
date_default_timezone_set('Asia/Manila');

$server_name = 'localhost';
$user_name = 'root';
$password = '';
$database = 'agribid_db';

$conn = new mysqli($server_name, $user_name, $password, $database);

if ($conn->connect_error) {
	die('Unable to connect: ' . $conn->connect_error);
}


$conn->set_charset('utf8mb4'); 
$conn->query("SET time_zone = '+08:00'");