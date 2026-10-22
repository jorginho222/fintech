<?php

declare(strict_types=1);

namespace App\Company\Infrastructure\Persistence\Repository;

use App\Company\Domain\Model\MemberUser;
use App\Company\Domain\Model\User;
use App\Company\Domain\Repository\UserRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

class DoctrineUserRepository implements UserRepositoryInterface
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    public function save(User $user): void
    {
        $this->em->persist($user);
        $this->em->flush();
    }

    public function findById(string $id): ?User
    {
        return $this->em->find(User::class, $id);
    }

    public function findByTaxIdentifier(string $taxIdentifier): ?User
    {
        return $this->em->getRepository(User::class)->findOneBy(['taxIdentifier' => $taxIdentifier]);
    }

    public function findMemberByIdAndCompany(string $id, string $companyId): ?MemberUser
    {
        return $this->em->getRepository(MemberUser::class)->findOneBy(['id' => $id, 'company' => $companyId]);
    }

    public function findMembersByCompany(string $companyId): array
    {
        return $this->em->getRepository(MemberUser::class)->findBy(['company' => $companyId], ['displayName' => 'ASC']);
    }

    public function countActiveMembersByCompany(string $companyId): int
    {
        return $this->em->getRepository(MemberUser::class)->count(['company' => $companyId, 'active' => true]);
    }
}
