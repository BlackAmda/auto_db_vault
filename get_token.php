<?php
require 'vendor/autoload.php';

$client = new Google_Client();
$client->setAuthConfig('credentials.json');
$client->addScope(Google_Service_Drive::DRIVE);
$client->setAccessType('offline');
$client->setPrompt('select_account consent');

$authUrl = $client->createAuthUrl();
echo "🌐 Open the following link in your browser:\n$authUrl\n\n";
echo "📥 Paste the authorization code here: ";
$authCode = trim(fgets(STDIN));

$accessToken = $client->fetchAccessTokenWithAuthCode($authCode);
file_put_contents('token.json', json_encode($accessToken));
echo "✅ token.json has been created successfully.\n";
