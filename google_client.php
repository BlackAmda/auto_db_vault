<?php
require __DIR__ . '/vendor/autoload.php';

function getGoogleClient(): Google_Client
{
    $client = new Google_Client();
    $client->setApplicationName('Auto DB Vault');
    $client->setAuthConfig(__DIR__ . '/credentials.json');
    $client->setScopes([Google_Service_Drive::DRIVE]);
    $client->setAccessType('offline');
    $client->setPrompt('select_account consent');

    $tokenPath = __DIR__ . '/token.json';
    $saved = [];
    if (file_exists($tokenPath)) {
        $saved = json_decode(file_get_contents($tokenPath), true) ?: [];
        $client->setAccessToken($saved);
    }

    if ($client->isAccessTokenExpired()) {
        $refresh = $client->getRefreshToken();
        if ($refresh) {
            $new = $client->fetchAccessTokenWithRefreshToken($refresh);
            if (isset($new['error'])) {
                throw new \RuntimeException('Token refresh failed: ' . json_encode($new));
            }

            $merged = array_merge($saved, $client->getAccessToken(), $new);
            file_put_contents($tokenPath, json_encode($merged, JSON_PRETTY_PRINT));
            $client->setAccessToken($merged);
        } else {
            throw new \LogicException(
                'No refresh_token in token.json. Run the one-time OAuth bootstrap to create a token with a refresh_token.'
            );
        }
    }

    return $client;
}

return getGoogleClient();
