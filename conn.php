<?php 
    $host       = "localhost";
    $user       = "root";
    $password   = "";
    $dbname     = "db_labkom";

    $conn    = mysqli_connect($host, $user, $password, $dbname);
    if (mysqli_connect_errno()) {
        die("Koneksi Gagal Karena : ". mysqli_connect_error());
    }
?>