<?php

declare(strict_types=1);

namespace App\Services\ExternalSubscriptions\V2RayTun;

use RuntimeException;

class V2RayTunCryptLinkEncoder
{
    private const PREFIX = 'v2raytun://crypt/';

    private const PUBLIC_KEY = <<<'KEY'
-----BEGIN PUBLIC KEY-----
MIICIjANBgkqhkiG9w0BAQEFAAOCAg8AMIICCgKCAgEArK77160gyNm0olpdA+WO
f1ClV4ndeRhBDYPQYs4hUq3YDwP1dQTdxaILcdnYJS2Wpfzqo8JiAvwhatBHJ2Kq
p/KSll5JSoqYAKj+1GdSF+nOCXc3wBeGR8mD6KdSRnoAE+x6wZcydNggQluClcx3
zTGjwWnBxUWfKlcQeHxHTtO+2i6Dga2o4it5J2uXOupEo9mrBZdc1BSKvrmoycMp
iaRF4YKRhwY1jZnEjx2BKA/xFQIDiIFQIAIKKPNKoIWbnQ66lEJSOr1DuIVGYgdr
xyupIQW3rvkGirybgx0+lIOn9J7c9doBDWHknOqGG0VeGKiVFMv5klG7KTsH89qe
nXfgrGQVEknAeGOrMPgjF+Zs52eHLeaWXCr4sCRgvAoPeBfMTfavu/Y0mnufD8SN
z8OdQSVT9jphXeM2YXtnwi971fsF97bykEK5ytco4zf9hgbEjioU7/cAvz20RyxY
EFCouOZsGXkwlUq+xDEPRIyQj2OwGl5xpjDJ4uAq5Shi4EUk01wUfRzTVDQIWJXC
O7Z9K4FcKRKY3m42fWr8fZl5rQbnmrLMLnD88n7ZVBRkIhfnt7XHtTCVWBwCDqsG
ceUX0Xf+ZwQ8tNYfE5ipy6RlkuZD8Ddlpk8qhstCBu82igNfRcSsJ5KT36aAhfZ+
WYqjdHmjzdjEGJqpfg1K1JMCAwEAAQ==
-----END PUBLIC KEY-----
KEY;

    public function encode(string $url): string
    {
        $url = trim($url);
        $key = openssl_pkey_get_public(self::PUBLIC_KEY);

        if ($url === '' || $key === false || ! openssl_public_encrypt($url, $encrypted, $key, OPENSSL_PKCS1_PADDING)) {
            throw new RuntimeException('Unable to encode V2RayTun crypt link.');
        }

        return self::PREFIX.$this->base64UrlEncode($encrypted);
    }

    private function base64UrlEncode(string $value): string
    {
        return base64_encode($value);
    }
}
