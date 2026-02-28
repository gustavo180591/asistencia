<?php

namespace App\Controller;

use App\Repository\AsistenciaRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AsistenciaController extends AbstractController
{
    /**
     * @Route("/asistencia", name="asistencia_index", methods={"GET"})
     */
    public function index(): Response
    {
        return $this->render('asistencia/index.html.twig');
    }

    /**
     * @Route("/asistencia/list", name="asistencia_list", methods={"GET"})
     */
    public function list(Request $request, AsistenciaRepository $repo): JsonResponse
    {
        return $this->json($repo->search($request->query->all()));
    }

    
    /**
     * @Route("/asistencia", name="asistencia_create", methods={"POST"})
     */
    public function create(Request $request, AsistenciaRepository $repo): JsonResponse
    {
        $csrf = $request->headers->get('X-CSRF-TOKEN');
        if (!$this->isCsrfTokenValid('asistencia', (string)$csrf)) {
            return $this->json(['error' => 'CSRF inválido'], 403);
        }

        $payload = json_decode($request->getContent(), true) ?? [];

        // validación mínima (porque id y direction son NOT NULL)
        if (!isset($payload['id']) || trim((string)$payload['id']) === '') {
            return $this->json(['error' => 'id es obligatorio'], 422);
        }
        if (!isset($payload['direction']) || trim((string)$payload['direction']) === '') {
            return $this->json(['error' => 'direction es obligatorio'], 422);
        }

        $affected = $repo->create($payload);
        return $this->json(['affected' => $affected]);
    }

    /**
     * @Route("/asistencia/{rowKey}", name="asistencia_update", methods={"PATCH"})
     */
    public function update(string $rowKey, Request $request, AsistenciaRepository $repo): JsonResponse
    {
        $csrf = $request->headers->get('X-CSRF-TOKEN');
        if (!$this->isCsrfTokenValid('asistencia', (string)$csrf)) {
            return $this->json(['error' => 'CSRF inválido'], 403);
        }

        $payload = json_decode($request->getContent(), true) ?? [];

        // validación mínima (porque id y direction son NOT NULL)
        if (!isset($payload['id']) || trim((string)$payload['id']) === '') {
            return $this->json(['error' => 'id es obligatorio'], 422);
        }
        if (!isset($payload['direction']) || trim((string)$payload['direction']) === '') {
            return $this->json(['error' => 'direction es obligatorio'], 422);
        }

        $affected = $repo->updateByRowKey($rowKey, $payload);
        return $this->json(['affected' => $affected]);
    }

    /**
     * @Route("/asistencia/{rowKey}", name="asistencia_delete", methods={"DELETE"})
     */
    public function delete(string $rowKey, Request $request, AsistenciaRepository $repo): JsonResponse
    {
        $csrf = $request->headers->get('X-CSRF-TOKEN');
        if (!$this->isCsrfTokenValid('asistencia', (string)$csrf)) {
            return $this->json(['error' => 'CSRF inválido'], 403);
        }

        $affected = $repo->deleteByRowKey($rowKey);
        return $this->json(['affected' => $affected]);
    }
}