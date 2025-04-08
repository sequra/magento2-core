<?php

namespace Sequra\Core\Services\BusinessLogic\Utility;

use SeQura\Core\BusinessLogic\Utility\EncryptorInterface;
use Magento\Framework\Encryption\EncryptorInterface as MagentoEncryptorInterface;

class Encryptor implements EncryptorInterface
{
    /**
     * @var MagentoEncryptorInterface
     */
    protected $encryptor;

    /**
     * @param MagentoEncryptorInterface $encryptor
     */
    public function __construct(MagentoEncryptorInterface $encryptor)
    {
        $this->encryptor = $encryptor;
    }

    /**
     * @inheritDoc
     */
    public function encrypt(string $data): string
    {
        return $this->encryptor->encrypt($data);
    }

    /**
     * @inheritDoc
     */
    public function decrypt(string $encryptedData): string
    {
        return $this->encryptor->decrypt($encryptedData);
    }
}
