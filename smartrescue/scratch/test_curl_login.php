<?php
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "http://localhost/SmartRescueApp/smartrescue/auth/login.php");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'phone_or_email' => 'maxamed@gmail.com',
    'password' => '123456',
    'login_btn' => '1',
    'flutter' => '1'
]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);

$response = curl_exec($ch);
echo "--- Raw Response ---\n";
echo $response;
curl_close($ch);
?>
