<?php
// cek_login.php

// Start the session to access session variables
session_start();

// Check if the user is logged in
if (isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0) {
    echo 'logged_in';
} else {
    echo 'not_logged_in'; // Or just leave empty for less verbose output
}

// It's good practice to exit after outputting for AJAX responses
exit();
?>