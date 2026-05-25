<?php
class TOTP {
    const DIGITS = 6;
    const PERIOD = 30;
    const WINDOW = 1;

    public static function generateSecret(): string {
        return self::base32Encode(random_bytes(20));
    }

    public static function getCode(string $secret, int $time = 0): string {
        if ($time === 0) $time = time();
        $counter = pack('N*', 0) . pack('N*', (int)floor($time / self::PERIOD));
        $key     = self::base32Decode($secret);
        $hash    = hash_hmac('sha1', $counter, $key, true);
        $offset  = ord($hash[19]) & 0x0f;
        $code    = (
            ((ord($hash[$offset])     & 0x7f) << 24) |
            ((ord($hash[$offset + 1]) & 0xff) << 16) |
            ((ord($hash[$offset + 2]) & 0xff) << 8)  |
             (ord($hash[$offset + 3]) & 0xff)
        ) % 1000000;
        return str_pad((string)$code, self::DIGITS, '0', STR_PAD_LEFT);
    }

    public static function verify(string $secret, string $code): bool {
        $time = time();
        for ($i = -self::WINDOW; $i <= self::WINDOW; $i++) {
            if (hash_equals(self::getCode($secret, $time + $i * self::PERIOD), $code)) {
                return true;
            }
        }
        return false;
    }

    public static function getOtpAuthUri(string $secret, string $username, string $appName): string {
        return 'otpauth://totp/' . rawurlencode($appName . ':' . $username)
             . '?secret='   . $secret
             . '&issuer='   . rawurlencode($appName)
             . '&digits=6&period=30&algorithm=SHA1';
    }

    public static function generateBackupCodes(int $count = 8): array {
        $plain  = [];
        $hashed = [];
        for ($i = 0; $i < $count; $i++) {
            $code     = strtoupper(bin2hex(random_bytes(4)));
            $plain[]  = implode('-', str_split($code, 4));
            $hashed[] = password_hash($code, PASSWORD_BCRYPT);
        }
        return ['plain' => $plain, 'hashed' => $hashed];
    }

    public static function verifyBackupCode(string $input, array $hashedCodes): int {
        $input = strtoupper(str_replace(['-', ' '], '', $input));
        foreach ($hashedCodes as $i => $hash) {
            if (password_verify($input, $hash)) {
                return $i;
            }
        }
        return -1;
    }

    private static function base32Encode(string $bytes): string {
        $alpha   = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $result  = '';
        $buffer  = 0;
        $bitsLeft = 0;
        foreach (str_split($bytes) as $byte) {
            $buffer    = ($buffer << 8) | ord($byte);
            $bitsLeft += 8;
            while ($bitsLeft >= 5) {
                $bitsLeft -= 5;
                $result   .= $alpha[($buffer >> $bitsLeft) & 31];
            }
        }
        if ($bitsLeft > 0) {
            $result .= $alpha[($buffer << (5 - $bitsLeft)) & 31];
        }
        return $result;
    }

    private static function base32Decode(string $encoded): string {
        $alpha   = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $encoded = strtoupper(preg_replace('/[^A-Z2-7]/', '', $encoded));
        $result  = '';
        $buffer  = 0;
        $bitsLeft = 0;
        foreach (str_split($encoded) as $char) {
            $pos = strpos($alpha, $char);
            if ($pos === false) continue;
            $buffer    = ($buffer << 5) | $pos;
            $bitsLeft += 5;
            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $result   .= chr(($buffer >> $bitsLeft) & 0xff);
            }
        }
        return $result;
    }
}
