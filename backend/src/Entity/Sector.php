<?php

namespace App\Entity;

use App\Repository\SectorRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SectorRepository::class)]
#[ORM\Table(name: 'sectors')]
class Sector
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100, unique: true)]
    private ?string $tickerSymbol = null;

    #[ORM\Column(length: 100)]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $gicsCode = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $gicsName = null;

    /**
     * @var Collection<int, Company>
     */
    #[ORM\OneToMany(mappedBy: 'sector', targetEntity: Company::class)]
    private Collection $companies;

    public function __construct()
    {
        $this->companies = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTickerSymbol(): ?string
    {
        return $this->tickerSymbol;
    }

    public function setTickerSymbol(string $tickerSymbol): static
    {
        $this->tickerSymbol = $tickerSymbol;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getGicsCode(): ?string
    {
        return $this->gicsCode;
    }

    public function setGicsCode(?string $gicsCode): static
    {
        $this->gicsCode = $gicsCode;

        return $this;
    }

    public function getGicsName(): ?string
    {
        return $this->gicsName;
    }

    public function setGicsName(?string $gicsName): static
    {
        $this->gicsName = $gicsName;

        return $this;
    }

    /**
     * @return Collection<int, Company>
     */
    public function getCompanies(): Collection
    {
        return $this->companies;
    }

    public function addCompany(Company $company): static
    {
        if (!$this->companies->contains($company)) {
            $this->companies->add($company);
            $company->setSector($this);
        }

        return $this;
    }

    public function removeCompany(Company $company): static
    {
        if ($this->companies->removeElement($company)) {
            if ($company->getSector() === $this) {
                $company->setSector(null);
            }
        }

        return $this;
    }
}
