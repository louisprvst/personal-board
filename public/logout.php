<?php
require_once __DIR__ . '/../src/auth.php';
deconnecter();
header('Location: /login.php');
