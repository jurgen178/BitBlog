<?php
// Session is already started by admin.php
session_destroy();
header('Location: admin.php?action=login');
