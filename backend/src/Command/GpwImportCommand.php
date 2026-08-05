<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Company;
use App\Entity\Sector;
use App\Service\Gpw\GpwClient;
use App\Service\Gpw\GpwSectorCatalog;
use App\Service\Gpw\NominatimGeocoder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:gpw:import',
    description: 'Importuje sektory i spółki Głównego Rynku GPW do bazy danych.',
)]
final class GpwImportCommand extends Command
{
    /** @var array<string, Sector> klucz: znormalizowana nazwa sektora */
    private array $sectorIndex = [];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly GpwClient $gpw,
        private readonly NominatimGeocoder $geocoder,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('sectors-only', null, InputOption::VALUE_NONE, 'Zaimportuj tylko sektory i zakończ.')
            ->addOption('skip-details', null, InputOption::VALUE_NONE, 'Nie pobieraj kart spółek (szybko: tylko nazwa + ticker).')
            ->addOption('geocode', null, InputOption::VALUE_NONE, 'Uzupełnij latitude/longitude przez Nominatim (~1 s na spółkę).')
            ->addOption('logos', null, InputOption::VALUE_NONE, 'Ustaw logoUrl na favicon domeny spółki.')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Ogranicz liczbę importowanych spółek (do testów).')
            ->addOption('only', null, InputOption::VALUE_REQUIRED, 'Zaimportuj tylko podane tickery, np. --only=CDR,PKO,DNP')
            ->addOption('overwrite', null, InputOption::VALUE_NONE, 'Nadpisz pola, które już mają wartość (domyślnie uzupełniane są tylko puste).')
            ->addOption('debug-isin', null, InputOption::VALUE_REQUIRED, 'Wypisz pary etykieta=>wartość z karty spółki o danym ISIN i zakończ.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (null !== $isin = $input->getOption('debug-isin')) {
            foreach ($this->gpw->debugLabels((string) $isin) as $label => $value) {
                $io->writeln(sprintf('<info>%s</info> => %s', $label, $value));
            }

            return Command::SUCCESS;
        }

        $io->title('Import danych z GPW');

        $this->importSectors($io);

        if ($input->getOption('sectors-only')) {
            return Command::SUCCESS;
        }

        return $this->importCompanies($io, $input);
    }

    private function importSectors(SymfonyStyle $io): void
    {
        $io->section('Sektory (klasyfikacja GPW)');

        $created = 0;
        $updated = 0;

        foreach (GpwSectorCatalog::all() as $definition) {
            $sector = $this->em->getRepository(Sector::class)
                ->findOneBy(['tickerSymbol' => $definition['symbol']]);

            if (null === $sector) {
                $sector = new Sector();
                $sector->setTickerSymbol($definition['symbol']);
                $this->em->persist($sector);
                ++$created;
            } else {
                ++$updated;
            }

            $sector->setName($definition['name']);
            $sector->setDescription('Makrosektor: ' . $definition['macro']);
            $sector->setGicsCode($definition['gicsCode']);
            $sector->setGicsName($definition['gicsName']);

            $this->sectorIndex[GpwSectorCatalog::normalize($definition['name'])] = $sector;
        }

        $this->em->flush();

        $io->success(sprintf('Sektory: %d nowych, %d zaktualizowanych.', $created, $updated));
    }

    private function importCompanies(SymfonyStyle $io, InputInterface $input): int
    {
        $io->section('Spółki Głównego Rynku');

        try {
            $companies = $this->gpw->fetchCompanyList();
        } catch (\Throwable $e) {
            $io->error('Nie udało się pobrać listy spółek: ' . $e->getMessage());

            return Command::FAILURE;
        }

        if ($only = $input->getOption('only')) {
            $wanted = array_map('strtoupper', array_map('trim', explode(',', (string) $only)));
            $companies = array_values(array_filter(
                $companies,
                static fn(array $c): bool => \in_array($c['ticker'], $wanted, true),
            ));
        }

        if ($limit = $input->getOption('limit')) {
            $companies = \array_slice($companies, 0, (int) $limit);
        }

        $io->writeln(sprintf('Pobrano %d spółek z listy GPW.', \count($companies)));

        $skipDetails = (bool) $input->getOption('skip-details');
        $doGeocode = (bool) $input->getOption('geocode');
        $doLogos = (bool) $input->getOption('logos');
        $overwrite = (bool) $input->getOption('overwrite');

        $created = 0;
        $failures = [];

        $io->progressStart(\count($companies));

        foreach ($companies as $i => $row) {
            $company = $this->em->getRepository(Company::class)->findOneBy(['ticker' => $row['ticker']]);

            if (null === $company) {
                $company = new Company();
                $company->setTicker($row['ticker']);
                $this->em->persist($company);
                ++$created;
            }

            if ($overwrite || null === $company->getName()) {
                $company->setName($row['name']);
            }
            if ($overwrite || null === $company->getCountry()) {
                $company->setCountry('Polska');
            }

            if (!$skipDetails) {
                try {
                    $details = $this->gpw->fetchCompanyDetails($row['isin']);

                    $this->fill($company, 'setAddress', $company->getAddress(), $details['address'], $overwrite);
                    $this->fill($company, 'setCity', $company->getCity(), $details['city'], $overwrite);
                    $this->fill($company, 'setWebsiteUrl', $company->getWebsiteUrl(), $details['website'], $overwrite);
                    $this->fill($company, 'setDescription', $company->getDescription(), $details['description'], $overwrite);

                    if (null !== $details['sector'] && (null === $company->getSector() || $overwrite)) {
                        $company->setSector($this->resolveSector($details['sector'], $details['macroSector']));
                    }
                } catch (\Throwable $e) {
                    $failures[] = sprintf('%s (%s): %s', $row['ticker'], $row['isin'], $e->getMessage());
                }

                usleep(300_000);
            }

            if ($doLogos && null !== $company->getWebsiteUrl() && ($overwrite || null === $company->getLogoUrl())) {
                $host = parse_url($company->getWebsiteUrl(), \PHP_URL_HOST);
                if (\is_string($host)) {
                    $company->setLogoUrl(sprintf('https://www.google.com/s2/favicons?sz=128&domain=%s', $host));
                }
            }

            if ($doGeocode && ($overwrite || null === $company->getLatitude())) {
                $coords = $this->geocoder->geocode($company->getAddress(), $company->getCity(), 'Polska');
                if (null !== $coords) {
                    $company->setLatitude($coords[0]);
                    $company->setLongitude($coords[1]);
                }
            }

            if (0 === ($i + 1) % 25) {
                $this->em->flush();
            }

            $io->progressAdvance();
        }

        $this->em->flush();
        $io->progressFinish();

        $io->success(sprintf(
            'Spółki: %d nowych, %d zaktualizowanych.',
            $created,
            \count($companies) - $created,
        ));

        if ([] !== $failures) {
            $io->warning(sprintf('Nie udało się pobrać szczegółów dla %d spółek:', \count($failures)));
            $io->listing(\array_slice($failures, 0, 20));
        }

        return Command::SUCCESS;
    }


    private function resolveSector(string $name, ?string $macro): Sector
    {
        $key = GpwSectorCatalog::normalize($name);

        if (isset($this->sectorIndex[$key])) {
            return $this->sectorIndex[$key];
        }

        $symbol = 'GPW-' . GpwSectorCatalog::slug($name);
        $sector = $this->em->getRepository(Sector::class)->findOneBy(['tickerSymbol' => $symbol]);

        if (null === $sector) {
            $sector = new Sector();
            $sector->setTickerSymbol(mb_substr($symbol, 0, 100));
            $sector->setName(mb_substr($name, 0, 100));
            $sector->setDescription(null !== $macro ? 'Makrosektor: ' . $macro : null);
            $this->em->persist($sector);
        }

        return $this->sectorIndex[$key] = $sector;
    }

    private function fill(Company $company, string $setter, ?string $current, ?string $value, bool $overwrite): void
    {
        if (null === $value || '' === $value) {
            return;
        }
        if (null !== $current && !$overwrite) {
            return;
        }

        $company->{$setter}($value);
    }
}
