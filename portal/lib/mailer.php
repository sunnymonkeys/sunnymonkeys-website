<?php
// Minimal SMTP sender for the Titan mailbox (implicit TLS + AUTH LOGIN), no libraries needed.
// Settings come from config/mail.php (not in the repo). Returns true, or an error string.

function sm_send_mail(string $to, string $subject, string $body, ?string $replyTo = null)
{
    if (!defined('MAIL_HOST')) {
        return 'not configured (portal/config/mail.php is missing)';
    }

    $fp = @stream_socket_client('ssl://' . MAIL_HOST . ':' . MAIL_PORT, $errno, $errstr, 10);
    if (!$fp) {
        return "could not connect to " . MAIL_HOST . ":" . MAIL_PORT . " ($errstr)";
    }
    stream_set_timeout($fp, 15);

    // Send one command and check the reply code. $label is what gets logged, never the command itself
    // (so the password can't end up in an error message).
    $step = function (string $label, ?string $command, array $okCodes) use ($fp) {
        if ($command !== null) {
            fwrite($fp, $command . "\r\n");
        }
        $reply = '';
        while (($line = fgets($fp, 515)) !== false) {
            $reply .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') {
                break;
            }
        }
        if (!in_array((int) substr($reply, 0, 3), $okCodes, true)) {
            throw new RuntimeException($label . ' rejected: ' . trim($reply));
        }
    };

    try {
        $step('greeting', null, [220]);
        $step('EHLO', 'EHLO sunnymonkeys.com', [250]);
        $step('AUTH', 'AUTH LOGIN', [334]);
        $step('username', base64_encode(MAIL_USER), [334]);
        $step('password', base64_encode(MAIL_PASS), [235]);
        $step('MAIL FROM', 'MAIL FROM:<' . MAIL_USER . '>', [250]);
        $step('RCPT TO', 'RCPT TO:<' . $to . '>', [250, 251]);
        $step('DATA', 'DATA', [354]);

        $headers = [
            'Date: ' . date('r'),
            'From: Sunny Monkeys Website <' . MAIL_USER . '>',
            'To: <' . $to . '>',
            'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=',
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@sunnymonkeys.com>',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
        ];
        if ($replyTo) {
            $headers[] = 'Reply-To: <' . $replyTo . '>';
        }
        // Base64 body lines never start with ".", so no SMTP dot-stuffing is needed.
        $data = implode("\r\n", $headers) . "\r\n\r\n" . rtrim(chunk_split(base64_encode($body), 76, "\r\n"));
        $step('message', $data . "\r\n.", [250]);
        $step('QUIT', 'QUIT', [221]);
        fclose($fp);
        return true;
    } catch (Throwable $e) {
        fclose($fp);
        return $e->getMessage();
    }
}
