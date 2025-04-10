<?php

namespace Vendor\EmailCollection\Domain\Model;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

class Subscriber extends AbstractEntity
{
    protected string $email = '';

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }
}
