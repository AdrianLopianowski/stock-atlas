<?php

namespace App\Controller;

use App\Entity\Sector;
use App\Repository\SectorRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class SectorController extends AbstractController
{
    #[Route('/api/sectors', name: 'app_sector_api_index', methods: ['GET'])]
    public function index(SectorRepository $sectorRepository): JsonResponse
    {
        $sectors = $sectorRepository->findAll();

        $formattedSectors = array_map(function (Sector $sector) {
            return [
                'id' => $sector->getId(),
                'tickerSymbol' => $sector->getTickerSymbol(),
                'name' => $sector->getName(),
                'description' => $sector->getDescription(),
                'gicsCode' => $sector->getGicsCode(),
                'gicsName' => $sector->getGicsName(),
            ];
        }, $sectors);

        return $this->json([
            'sectors' => $formattedSectors,
            'count' => count($formattedSectors),
        ]);
    }
}
