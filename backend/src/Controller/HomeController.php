<?php

namespace App\Controller;

use App\Repository\CompanyRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\UX\Map\InfoWindow;
use Symfony\UX\Map\Map;
use Symfony\UX\Map\Marker;
use Symfony\UX\Map\Point;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_mapa')]
    public function index(CompanyRepository $companyRepository): Response
    {
        $companies = $companyRepository->findGeocodedWithRelations();

        $map = (new Map())
            ->center(new Point(52.0, 19.4))
            ->zoom(6);

        foreach ($companies as $company) {
            $logoHtml = $company->getLogoUrl()
                ? sprintf('<img src="%s" alt="%s" class="map-popup-logo" />', htmlspecialchars($company->getLogoUrl()), htmlspecialchars($company->getTicker()))
                : sprintf('<div class="map-popup-logo-placeholder">%s</div>', htmlspecialchars($company->getTicker()));

            $sectorBadge = $company->getSector()
                ? sprintf('<span class="map-popup-badge">%s</span>', htmlspecialchars($company->getSector()->getName()))
                : '';

            $websiteHtml = $company->getWebsiteUrl()
                ? sprintf('<a href="%s" target="_blank" rel="noopener noreferrer" class="map-popup-link">Strona WWW &rarr;</a>', htmlspecialchars($company->getWebsiteUrl()))
                : '';

            $descText = $company->getDescription();
            $descHtml = $descText
                ? sprintf('<p class="map-popup-desc">%s</p>', htmlspecialchars(mb_strimwidth($descText, 0, 150, '...')))
                : '';

            $locationText = trim(implode(', ', array_filter([$company->getAddress(), $company->getCity()])));

            $infoContent = sprintf(
                '<div class="map-popup-card">
                    <div class="map-popup-header">
                        %s
                        <div class="map-popup-info">
                            <span class="map-popup-ticker">%s</span>
                            <h4 class="map-popup-title">%s</h4>
                            %s
                        </div>
                    </div>
                    %s
                    %s
                    %s
                </div>',
                $logoHtml,
                htmlspecialchars($company->getTicker()),
                htmlspecialchars($company->getName()),
                $sectorBadge,
                $locationText !== '' ? sprintf('<p class="map-popup-location">📍 %s</p>', htmlspecialchars($locationText)) : '',
                $descHtml,
                $websiteHtml
            );

            $map->addMarker(new Marker(
                position: new Point($company->getLatitude(), $company->getLongitude()),
                title: sprintf('%s (%s)', $company->getName(), $company->getTicker()),
                infoWindow: new InfoWindow(
                    content: $infoContent
                ),
            ));
        }

        return $this->render('mapa/index.html.twig', [
            'mapa' => $map,
            'companyCount' => count($companies),
        ]);
    }
}

