<?php

namespace App\Repository;

use App\Entity\Company;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Company>
 */
class CompanyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Company::class);
    }

    /**
     * @return Company[]
     */
    public function findAllWithRelations(): array
    {
        return $this->createQueryBuilder('c')
            ->addSelect('s')
            ->leftJoin('c.sector', 's')
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Wyszukiwanie po nazwie lub tickerze.
     *
     * @return Company[]
     */
    public function search(string $query): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.name LIKE :query OR c.ticker LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}