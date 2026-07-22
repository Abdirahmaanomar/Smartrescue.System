<?php
$ch = curl_init('http://127.0.0.1/SmartRescueApp/smartrescue/api/user/user_settings.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, [
    'action' => 'toggle_preference',
    'preference' => 'notifications_enabled',
    'value' => '0',
    'user_id' => '1049'
]);
$res = curl_exec($ch);
echo "Result: " . $res . "\n";
?>
