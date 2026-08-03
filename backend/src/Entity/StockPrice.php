<?php

namespace App\Entity;

use App\Repository\StockPriceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StockPriceRepository::class)]
#[ORM\Table(name: 'stock_prices')]
#[ORM\UniqueConstraint(name: 'company_date_unique', columns: ['company_id', 'date'])]
#[ORM\Index(name: 'idx_stock_price_date', columns: ['date'])]
class StockPrice
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $date = null;

    #[ORM\Column(type: Types::FLOAT)]
    private ?float $open = null;

    #[ORM\Column(type: Types::FLOAT)]
    private ?float $high = null;

    #[ORM\Column(type: Types::FLOAT)]
    private ?float $low = null;

    #[ORM\Column(type: Types::FLOAT)]
    private ?float $close = null;

    #[ORM\Column(type: Types::BIGINT, nullable: true)]
    private ?string $volume = null;

    #[ORM\ManyToOne(targetEntity: Company::class, inversedBy: 'stockPrices')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Company $company = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDate(): ?\DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(\DateTimeImmutable $date): static
    {
        $this->date = $date;

        return $this;
    }

    public function getOpen(): ?float
    {
        return $this->open;
    }

    public function setOpen(float $open): static
    {
        $this->open = $open;

        return $this;
    }

    public function getHigh(): ?float
    {
        return $this->high;
    }

    public function setHigh(float $high): static
    {
        $this->high = $high;

        return $this;
    }

    public function getLow(): ?float
    {
        return $this->low;
    }

    public function setLow(float $low): static
    {
        $this->low = $low;

        return $this;
    }

    public function getClose(): ?float
    {
        return $this->close;
    }

    public function setClose(float $close): static
    {
        $this->close = $close;

        return $this;
    }

    public function getVolume(): ?string
    {
        return $this->volume;
    }

    public function setVolume(?string $volume): static
    {
        $this->volume = $volume;

        return $this;
    }

    public function getCompany(): ?Company
    {
        return $this->company;
    }

    public function setCompany(?Company $company): static
    {
        $this->company = $company;

        return $this;
    }
}