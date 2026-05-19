<?php

namespace App\Services;

/**
 * Parsed einen curl-Command-String in seine Bestandteile:
 * URL, Method, Headers, Body, Auth (wenn via -H Authorization oder -u).
 *
 * Verarbeitet die haeufigsten Varianten:
 *   curl -X POST 'https://…' -H 'A: B' -d '{"a":1}'
 *   curl 'https://…' --header "A: B" --data-raw '...'
 *   curl -u user:pass 'https://…'
 *   Multi-Line via Backslash-Newline.
 */
class CurlParser
{
    /**
     * @return array{url:?string, method:string, headers:array<string,string>, body:?string, auth:?array{type:string, token?:string, username?:string, password?:string, header_name?:string}}
     */
    public static function parse(string $cmd): array
    {
        // Backslash-Newline-Joins entfernen, sonst spaltet die Tokenisierung.
        $cmd = preg_replace('/\\\\\s*\R/', ' ', $cmd);
        $cmd = trim($cmd);

        $tokens = self::tokenize($cmd);

        $url = null;
        $method = null;
        $headers = [];
        $body = null;
        $auth = null;

        for ($i = 0; $i < count($tokens); $i++) {
            $t = $tokens[$i];
            if ($t === 'curl' || $t === '') continue;

            $next = fn () => $tokens[++$i] ?? '';

            switch ($t) {
                case '-X':
                case '--request':
                    $method = strtoupper($next());
                    break;
                case '-H':
                case '--header':
                    $h = $next();
                    if (strpos($h, ':') !== false) {
                        [$k, $v] = explode(':', $h, 2);
                        $headers[trim($k)] = trim($v);
                    }
                    break;
                case '-d':
                case '--data':
                case '--data-raw':
                case '--data-binary':
                case '--data-ascii':
                    $body = $next();
                    if ($method === null) $method = 'POST';
                    break;
                case '-u':
                case '--user':
                    $userpass = $next();
                    [$un, $pw] = array_pad(explode(':', $userpass, 2), 2, '');
                    $auth = ['type' => 'basic', 'username' => $un, 'password' => $pw];
                    break;
                case '--url':
                    $url = $next();
                    break;
                case '-A':
                case '--user-agent':
                    $headers['User-Agent'] = $next();
                    break;
                case '--compressed':
                case '-k':
                case '--insecure':
                case '-L':
                case '--location':
                case '-s':
                case '--silent':
                case '-i':
                case '--include':
                case '-v':
                case '--verbose':
                    break;
                default:
                    if (preg_match('/^https?:\/\//i', $t)) {
                        $url = $t;
                    } elseif (str_starts_with($t, '-')) {
                        // unbekannter Flag — wenn er ein Arg erwartet (heuristisch), skip naechstes
                        // hier konservativ: ueberspringen
                    }
            }
        }

        // Authorization-Header in struct auth uebersetzen
        foreach ($headers as $name => $value) {
            if (strcasecmp($name, 'Authorization') !== 0) continue;
            $val = trim($value);
            if (stripos($val, 'Bearer ') === 0) {
                $auth = ['type' => 'bearer', 'token' => trim(substr($val, 7))];
                unset($headers[$name]);
            } elseif (stripos($val, 'Basic ') === 0) {
                $decoded = base64_decode(trim(substr($val, 6)), true);
                if ($decoded && str_contains($decoded, ':')) {
                    [$un, $pw] = explode(':', $decoded, 2);
                    $auth = ['type' => 'basic', 'username' => $un, 'password' => $pw];
                    unset($headers[$name]);
                }
            }
            break;
        }

        // Sonderfall: X-API-Key / API-Key Headers → eigener Auth-Typ.
        foreach ($headers as $name => $value) {
            if (preg_match('/^(x-api-key|api-key|x-auth-token)$/i', $name)) {
                $auth = ['type' => 'api_key_header', 'header_name' => $name, 'token' => $value];
                unset($headers[$name]);
                break;
            }
        }

        return [
            'url' => $url,
            'method' => $method ?? 'GET',
            'headers' => $headers,
            'body' => $body,
            'auth' => $auth,
        ];
    }

    /** Shell-aehnliches Tokenisieren — respektiert Single + Double Quotes. */
    private static function tokenize(string $cmd): array
    {
        $tokens = [];
        $current = '';
        $quote = null;
        $len = strlen($cmd);
        for ($i = 0; $i < $len; $i++) {
            $c = $cmd[$i];
            if ($quote === null) {
                if ($c === "'" || $c === '"') {
                    $quote = $c;
                } elseif (ctype_space($c)) {
                    if ($current !== '') { $tokens[] = $current; $current = ''; }
                } else {
                    $current .= $c;
                }
            } else {
                if ($c === $quote) {
                    $quote = null;
                } else {
                    $current .= $c;
                }
            }
        }
        if ($current !== '') $tokens[] = $current;
        return $tokens;
    }
}
