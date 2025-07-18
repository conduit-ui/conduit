<?php

// Simple callback handler for Spotify OAuth
// This file handles the OAuth callback when PHP's built-in server is running

header('Content-Type: text/html; charset=utf-8');

// Extract authorization code from URL
$authCode = $_GET['code'] ?? null;
$state = $_GET['state'] ?? null;
$error = $_GET['error'] ?? null;

if ($error) {
    // Handle OAuth error
    http_response_code(400);
    echo getErrorPage($error);
    // Store error in temp file for the CLI to read
    file_put_contents('/tmp/spotify_auth_error', $error);
    exit;
}

if ($authCode) {
    // Store the auth code in a temp file for the CLI to read
    $authData = [
        'code' => $authCode,
        'state' => $state,
        'timestamp' => time()
    ];
    
    file_put_contents('/tmp/spotify_auth_code', json_encode($authData));
    
    echo getSuccessPage();
} else {
    // No code received
    http_response_code(400);
    echo getErrorPage('No authorization code received');
    file_put_contents('/tmp/spotify_auth_error', 'missing_code');
}

function getSuccessPage(): string
{
    return '<!DOCTYPE html>
<html>
<head>
    <title>🚀 Conduit × Spotify Connected</title>
    <meta charset="utf-8">
    <style>
        body { 
            font-family: system-ui, -apple-system, sans-serif;
            text-align: center; 
            padding: 50px; 
            background: #1db954;
            color: white;
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container { 
            max-width: 500px; 
            background: rgba(0,0,0,0.2);
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
        }
        h1 { font-size: 2.5em; margin-bottom: 20px; }
        .emoji { font-size: 4em; margin: 20px 0; }
        p { font-size: 1.2em; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="emoji">🎵</div>
        <h1>Spotify Connected!</h1>
        <p>Your music empire is ready to rock</p>
        <p><strong>You can close this window now</strong></p>
    </div>
    <script>
        setTimeout(() => window.close(), 3000);
        if (window.opener) {
            window.opener.postMessage("spotify_auth_complete", "*");
        }
    </script>
</body>
</html>';
}

function getErrorPage(string $error): string
{
    return '<!DOCTYPE html>
<html>
<head>
    <title>❌ Spotify Authorization Error</title>
    <meta charset="utf-8">
    <style>
        body { 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            text-align: center; 
            padding: 50px; 
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container { 
            max-width: 500px; 
            background: rgba(0,0,0,0.1);
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
        }
        h1 { font-size: 2.5em; margin-bottom: 20px; font-weight: 300; }
        p { font-size: 1.2em; margin-bottom: 20px; opacity: 0.9; }
        .emoji { font-size: 4em; margin: 20px 0; }
        .error-code { 
            font-family: "SF Mono", Monaco, monospace;
            background: rgba(0,0,0,0.3);
            padding: 15px;
            border-radius: 10px;
            margin: 20px 0;
            font-size: 1.1em;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="emoji">❌</div>
        <h1>Authorization Failed</h1>
        <p>There was an error connecting to Spotify.</p>
        <div class="error-code">Error: ' . htmlspecialchars($error) . '</div>
        <p>Please close this window and try again in your terminal.</p>
    </div>
</body>
</html>';
}