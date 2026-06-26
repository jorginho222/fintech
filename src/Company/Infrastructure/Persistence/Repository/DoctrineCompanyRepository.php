<?php

declare(strict_types=1);

namespace App\Company\Infrastructure\Persistence\Repository;

use App\Company\Domain\Model\Company;
use App\Company\Domain\Repository\CompanyRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

class DoctrineCompanyRepository implements CompanyRepositoryInterface
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    public function save(Company $company): void
    {
        $this->em->persist($company);
        $this->em->flush();
    }

    public function findById(string $id): ?Company
    {
        return $this->em->find(Company::class, $id);
    }

    public function findAll(): array
    {
        return $this->em->getRepository(Company::class)->findAll();
    }

    public function delete(Company $company): void
    {
        $this->em->remove($company);
        $this->em->flush();
    }
}
