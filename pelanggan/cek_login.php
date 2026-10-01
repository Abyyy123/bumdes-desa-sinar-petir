<?php
session_start();

if (isset($_SESSION['pengguna_id']) && !empty($_SESSION['pengguna_id'])) {
    echo 'logged_in';
} else {
    echo 'logged_out';
}
?>