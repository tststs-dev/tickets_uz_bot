<?php
require_once "database/MonitoringDatabase.php";
require_once "configs/db_config.php";

ignore_user_abort(true);

$db = new MonitoringDatabase(DB_LOGIN, DB_PASSWORD, DB_NAME, DB_SERVER);
$connect = $db->connect();
$monitoring_list = mysqli_query($connect, "SELECT id, date_dep FROM user_data WHERE state LIKE 15")->fetch_all(MYSQLI_ASSOC);

foreach ($monitoring_list as $monitoring) {
    $id = $monitoring['id'];
    if(time() > (strtotime($monitoring['date_dep']) + 86400)) {
        print_r($id . "\n");
        mysqli_query($connect, "DELETE FROM user_data WHERE id=$id");
    }
}


