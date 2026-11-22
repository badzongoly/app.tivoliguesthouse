<?php
namespace Tivoli\Infrastructure;
use RuntimeException;
final class ResetEnvelope
{
    public static function encode(array $payload,string $secret): string {
        $nonce=random_bytes(12);$tag='';$key=hash('sha256','reset-delivery|'.$secret,true);
        $cipher=openssl_encrypt(json_encode($payload,JSON_THROW_ON_ERROR),'aes-256-gcm',$key,OPENSSL_RAW_DATA,$nonce,$tag);
        if($cipher===false){throw new RuntimeException('Recovery queue encryption failed.');}
        return base64_encode($nonce.$tag.$cipher);
    }
    public static function decode(string $payload,string $secret): array {
        $bytes=base64_decode($payload,true);if($bytes===false || strlen($bytes)<29){throw new RuntimeException('Invalid recovery envelope.');}
        $plain=openssl_decrypt(substr($bytes,28),'aes-256-gcm',hash('sha256','reset-delivery|'.$secret,true),OPENSSL_RAW_DATA,substr($bytes,0,12),substr($bytes,12,16));
        if($plain===false){throw new RuntimeException('Recovery envelope authentication failed.');}
        return json_decode($plain,true,512,JSON_THROW_ON_ERROR);
    }
}