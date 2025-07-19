<?php 
    $host = "sql.freedb.tech";
    $user = "freedb_labkom";
    $pass = "sZRM%NjWy\$xDk7Z";  // tanda $ perlu di-escape
    $db   = "freedb_labkompis";
    $port = 3306;

    $conn    = mysqli_connect($host, $user, $password, $dbname);
    if (mysqli_connect_errno()) {
        die("Koneksi Gagal Karena : ". mysqli_connect_error());
    }
?>