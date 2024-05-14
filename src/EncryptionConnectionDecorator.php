<?php

namespace alexeevdv\Flysystem\Imap;

final class EncryptionConnectionDecorator implements Connection
{
    private const CYPHER_ALGO = 'AES-128-CTR';

    private Connection $inner;

    private string $encryptionKey;

    private string $initializationVector;

    public function __construct(
        Connection $inner,
        string $encryptionKey,
        string $initializationVector,
    ) {
        $this->inner = $inner;
        $this->encryptionKey = $encryptionKey;
        $this->initializationVector = $initializationVector;
    }

    public function getUid(string $subject): ?string
    {
        return $this->inner->getUid($this->encrypt($subject));
    }

    public function readResource(string $uid)
    {
        // TODO read file, decrypt, create new file, delete old file
        return $this->inner->readResource($uid);
    }

    public function read(string $uid): string
    {
        return $this->decrypt($this->inner->read($uid));
    }

    public function write(string $subject, string $contents): bool
    {
        return $this->inner->write($this->encrypt($subject), $this->encrypt($contents));
    }

    public function delete(string $uid): bool
    {
        return $this->inner->delete($uid);
    }

    public function encrypt(string $value): string
    {
        $encryptedContents = openssl_encrypt(
            $value,
            self::CYPHER_ALGO,
            $this->encryptionKey,
            0,
            $this->initializationVector,
        );

        if ($encryptedContents === false) {
            throw new \RuntimeException('Cant encrypt content');
        }

        return $encryptedContents;
    }

    public function decrypt(string $value): string
    {
        $content = openssl_decrypt(
            $value,
            self::CYPHER_ALGO,
            $this->encryptionKey,
            0,
            $this->initializationVector,
        );

        if ($content === false) {
            throw new \RuntimeException('Cant decrypt content');
        }

        return $content;
    }
}
