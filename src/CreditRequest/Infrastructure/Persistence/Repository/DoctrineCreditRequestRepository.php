<?php

declare(strict_types=1);

namespace App\CreditRequest\Infrastructure\Persistence\Repository;

use App\CreditRequest\Domain\Model\CreditRequest;
use App\CreditRequest\Domain\Model\CreditRequestStatus;
use App\CreditRequest\Domain\Model\Installment;
use App\CreditRequest\Domain\Model\InstallmentStatus;
use App\CreditRequest\Domain\Repository\CreditRequestRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

class DoctrineCreditRequestRepository implements CreditRequestRepositoryInterface
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    public function save(CreditRequest $creditRequest): void
    {
        $this->em->persist($creditRequest);
        $this->em->flush();
    }

    public function saveInstallment(Installment $installment): void
    {
        $this->em->persist($installment);
        $this->em->flush();
    }

    public function findById(string $id): ?CreditRequest
    {
        return $this->em->find(CreditRequest::class, $id);
    }

    public function findInstallmentById(string $id): ?Installment
    {
        return $this->em->find(Installment::class, $id);
    }

    public function findExpiredProposals(\DateTimeImmutable $now): array
    {
        return $this->em->createQueryBuilder()
            ->select('creditRequest')
            ->from(CreditRequest::class, 'creditRequest')
            ->where('creditRequest.status = :status')
            ->andWhere('creditRequest.approvalLimitDate < :now')
            ->setParameter('status', CreditRequestStatus::Proposal)
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();
    }

    public function findWithOverdueInstallments(\DateTimeImmutable $now): array
    {
        return $this->em->createQueryBuilder()
            ->select('installment')
            ->from(Installment::class, 'installment')
            ->where('installment.status = :status')
            ->andWhere('installment.dueDate < :now')
            ->setParameter('status', InstallmentStatus::Pending)
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();
    }
}
