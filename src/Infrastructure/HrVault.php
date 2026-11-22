<?php
namespace Tivoli\Infrastructure;
final class HrVault {
    public function __construct(private readonly string $secret){if(!preg_match('/^[a-f0-9]{64}$/D',$secret))throw new \RuntimeException('HR encryption key is unavailable.');}
    public function seal(string $plain,string $purpose):string {$nonce=random_bytes(12);$tag='';$key=hash_hmac('sha256','tivoli-hr|'.$purpose,hex2bin($this->secret),true);$cipher=openssl_encrypt($plain,'aes-256-gcm',$key,OPENSSL_RAW_DATA,$nonce,$tag,$purpose);if($cipher===false)throw new \RuntimeException('Private HR encryption failed.');return 'h1.'.base64_encode($nonce.$tag.$cipher);}
    public function open(string $envelope,string $purpose):string {if(!str_starts_with($envelope,'h1.'))throw new \RuntimeException('Invalid private HR envelope.');$bytes=base64_decode(substr($envelope,3),true);if($bytes===false||strlen($bytes)<28)throw new \RuntimeException('Invalid private HR envelope.');$plain=openssl_decrypt(substr($bytes,28),'aes-256-gcm',hash_hmac('sha256','tivoli-hr|'.$purpose,hex2bin($this->secret),true),OPENSSL_RAW_DATA,substr($bytes,0,12),substr($bytes,12,16),$purpose);if($plain===false)throw new \RuntimeException('Private HR authentication failed.');return $plain;}
}
