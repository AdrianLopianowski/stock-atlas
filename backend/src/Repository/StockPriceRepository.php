<?php

namespace App\Repository;

use App\Entity\StockPrice;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StockPrice>
 */
class StockPriceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StockPrice::class);
    }

    /**
     * Wyszukuje ceny historyczne dla spółki na podstawie jej symbolu
     *
     * @return StockPrice[]
     */
    public function findByTicker(string $ticker): array
    {
        return $this->createQueryBuilder('sp')
            ->join('sp.company', 'c')
            ->andWhere('c.ticker = :ticker')
            ->setParameter('ticker', $ticker)
            ->orderBy('sp.date', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
