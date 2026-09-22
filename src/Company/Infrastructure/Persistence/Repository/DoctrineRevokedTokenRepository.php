<?php

declare(strict_types=1);

namespace App\Company\Infrastructure\Persistence\Repository;

use App\Company\Domain\Model\RevokedToken;
use App\Company\Domain\Repository\RevokedTokenRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

class DoctrineRevokedTokenRepository implements RevokedTokenRepositoryInterface
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    public function save(RevokedToken $revokedToken): void
    {
        $this->em->persist($revokedToken);
        $this->em->flush();
    }

    public function existsByJti(string $jti): bool
    {
        return $this->em->find(RevokedToken::class, $jti) !== null;
    }
}
