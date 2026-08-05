<?php

namespace App\Controller;

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
    public function index(): Response
    {
        $map = (new Map())
            ->center(new Point(53.1235, 18.0084))
            ->zoom(12)
            ->addMarker(new Marker(
                position: new Point(53.1235, 18.0084),
                title: 'Bydgoszcz',
                infoWindow: new InfoWindow(
                    content: '<h3>Bydgoszcz</h3><p>Stary Rynek</p>'
                ),
            ));

        return $this->render('mapa/index.html.twig', [
            'mapa' => $map,
        ]);
    }
}

