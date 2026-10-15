<?php

const LIFETIME_SECONDS = 60;

const BODY = '{"reached":true}';

$server = \stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);

if ($server === false) {
    \fwrite(STDERR, $errorMessage);
    exit(1);
}

$address = (string) \stream_socket_get_name($server, false);
\fwrite(STDOUT, \substr($address, \strrpos($address, ':') + 1).PHP_EOL);
\fflush(STDOUT);

while ($connection = @\stream_socket_accept($server, LIFETIME_SECONDS)) {
    $request = '';
    while (!\str_contains($request, "\r\n\r\n") && !\feof($connection)) {
        $request .= (string) \fread($connection, 8192);
    }

    \fwrite($connection, "HTTP/1.1 200 OK\r\nContent-Type: application/json\r\nContent-Length: ".\strlen(BODY)."\r\nConnection: close\r\n\r\n".BODY);
    \fclose($connection);
}
