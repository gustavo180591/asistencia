<?php

namespace App\Controller;

use App\Repository\AsistenciaRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DashboardController extends AbstractController
{
    /**
     * @Route("/", name="dashboard_index", methods={"GET"})
     */
    public function index(): Response
    {
        return $this->render('dashboard/index.html.twig');
    }

    /**
     * @Route("/dashboard/stats", name="dashboard_stats", methods={"GET"})
     */
    public function stats(AsistenciaRepository $repo): JsonResponse
    {
        $stats = $repo->getDashboardStats();
        return $this->json($stats);
    }
}
