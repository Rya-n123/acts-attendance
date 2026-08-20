<?php
session_start();
session_unset();
session_destroy();
// Ipasa ang status=logged_out sa URL para ma-detect ng login page
header("Location: login.php?status=logged_out");
exit();