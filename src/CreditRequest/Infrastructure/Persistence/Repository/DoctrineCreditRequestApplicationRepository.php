<?php

declare(strict_types=1);

namespace App\CreditRequest\Infrastructure\Persistence\Repository;

use App\CreditRequest\Domain\Model\CreditRequestApplication;
use App\CreditRequest\Domain\Repository\CreditRequestApplicationRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

class DoctrineCreditRequestApplicationRepository implements CreditRequestApplicationRepositoryInterface
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    public function save(CreditRequestApplication $creditRequestApplication): void
    {
        $this->em->persist($creditRequestApplication);
        $this->em->flush();
    }
}
