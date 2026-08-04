<?php

namespace App\Controller;

use App\Entity\Company;
use App\Repository\CompanyRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class CompanyController extends AbstractController
{

    #[Route('/api/companies', name: 'app_company_api_index', methods: ['GET'])]
    public function index(Request $request, CompanyRepository $companyRepository): JsonResponse
    {
        $query = $request->query->get('q');

        $companies = $query
            ? $companyRepository->search($query)
            : $companyRepository->findAllWithRelations();

        $formattedCompanies = array_map(function (Company $company) {
            return [
                'id' => $company->getId(),
                'name' => $company->getName(),
                'ticker' => $company->getTicker(),
                'logoUrl' => $company->getLogoUrl(),
                'latitude' => $company->getLatitude(),
                'longitude' => $company->getLongitude(),
                'address' => $company->getAddress(),
                'city' => $company->getCity(),
                'country' => $company->getCountry(),
                'description' => $company->getDescription(),
                'websiteUrl' => $company->getWebsiteUrl(),
                'sector' => $company->getSector() ? [
                    'id' => $company->getSector()->getId(),
                    'tickerSymbol' => $company->getSector()->getTickerSymbol(),
                    'name' => $company->getSector()->getName(),
                    'description' => $company->getSector()->getDescription(),
                    'gicsCode' => $company->getSector()->getGicsCode(),
                    'gicsName' => $company->getSector()->getGicsName(),
                ] : null,
            ];
        }, $companies);

        return $this->json([
            'companies' => $formattedCompanies,
            'count' => count($formattedCompanies),
        ], JsonResponse::HTTP_OK);
    }
}
