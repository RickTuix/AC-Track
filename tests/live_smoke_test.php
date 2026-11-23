<?php

function testLiveSession(string $username, string $password, string $expectedRedirect) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIEFILE, ""); // in-memory cookie engine
    curl_setopt($ch, CURLOPT_HEADER, true);

    // 1. Fetch login page
    curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1:8000/login');
    $response = curl_exec($ch);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $body = substr($response, $headerSize);

    if (!preg_match('/name="_csrf_token"\s+value="([^"]+)"/', $body, $matches)) {
        throw new Exception("CSRF token not found in /login");
    }
    $token = $matches[1];

    // 2. Submit credentials
    curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1:8000/login');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Origin: http://127.0.0.1:8000',
        'Referer: http://127.0.0.1:8000/login',
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        '_username' => $username,
        '_password' => $password,
        '_csrf_token' => $token,
    ]));
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);

    $loginResp = curl_exec($ch);
    $redirectUrl = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    echo "[$username] POST /login -> Status $statusCode, Redirect: $redirectUrl\n";

    if ($statusCode !== 302 || !str_contains($redirectUrl, $expectedRedirect)) {
        // Fetch error page
        curl_setopt($ch, CURLOPT_HTTPGET, true);
        curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1:8000/login');
        $errorPage = curl_exec($ch);
        echo "Error Page excerpt: " . substr($errorPage, strpos($errorPage, 'alert') ?: 0, 500) . "\n";
        throw new Exception("Failed authentication for $username. Expected redirect to $expectedRedirect, got $redirectUrl");
    }

    // 3. Follow redirect
    curl_setopt($ch, CURLOPT_HTTPGET, true);
    curl_setopt($ch, CURLOPT_URL, $redirectUrl);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

    $dashResp = curl_exec($ch);
    $dashStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);

    echo "[$username] GET $redirectUrl -> Final Status: $dashStatus, Final URL: $finalUrl\n";

    if ($dashStatus !== 200) {
        throw new Exception("Failed to load dashboard: Status $dashStatus");
    }
}

echo "=== AC.TRACK Live Development Server Smoke Verification ===\n";
testLiveSession('student01', 'password123', '/student/dashboard');
testLiveSession('teacher.santos', 'password123', '/teacher/dashboard');
testLiveSession('admin', 'password123', '/admin/dashboard');
echo "SUCCESS: All 3 roles successfully authenticated and reached their respective dashboards!\n";
