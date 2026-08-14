function coai_google_oauth_access_token() {

    $refresh_token = get_option('coai_google_refresh_token');

    if (!$refresh_token) {
        return new WP_Error('no_token', 'Missing refresh token. Re-auth required.');
    }

    $client_id     = defined('COAI_GOOGLE_CLIENT_ID') ? COAI_GOOGLE_CLIENT_ID : '';
    $client_secret = defined('COAI_GOOGLE_CLIENT_SECRET') ? COAI_GOOGLE_CLIENT_SECRET : '';

    $response = wp_remote_post('https://oauth2.googleapis.com/token', [
        'body' => [
            'client_id' => $client_id,
            'client_secret' => $client_secret,
            'refresh_token' => $refresh_token,
            'grant_type' => 'refresh_token',
        ]
    ]);

    if (is_wp_error($response)) return $response;

    $body = json_decode(wp_remote_retrieve_body($response), true);

    if (empty($body['access_token'])) {
        return new WP_Error('token_failed', 'Google token refresh failed.');
    }

    return $body['access_token'];
}

error_log('COAI REFRESH TOKEN: ' . print_r($refresh_token, true));
error_log('GOOGLE RESPONSE: ' . print_r($body, true));
