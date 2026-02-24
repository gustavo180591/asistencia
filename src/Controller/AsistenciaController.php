<?php

namespace App\Controller;

use App\Repository\AsistenciaRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class AsistenciaController extends AbstractController
{
    /**
     * @Route("/asistencia", name="asistencia_index", methods={"GET"})
     */
    public function index(): \Symfony\Component\HttpFoundation\Response
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
     * @Route("/asistencia/export", name="asistencia_export", methods={"GET"})
     */
    public function export(Request $request, AsistenciaRepository $repo): \Symfony\Component\HttpFoundation\Response
    {
        $filters = $request->query->all();
        $data = $repo->exportData($filters);
        
        // Generate filename with date range
        $from = $filters['authDateFrom'] ?? 'todos';
        $to = $filters['authDateTo'] ?? 'todos';
        $filename = "asistencia_{$from}_a_{$to}.csv";
        
        // Create CSV content with header info
        $csv = "REPORTE DE ASISTENCIA\n";
        $csv .= "Desde: " . ($filters['authDateFrom'] ?? 'Todas las fechas') . "\n";
        $csv .= "Hasta: " . ($filters['authDateTo'] ?? 'Todas las fechas') . "\n";
        $csv .= "Generado: " . date('d/m/Y H:i:s') . "\n\n";
        $csv .= "Legajo,Nombre y Apellido,Fecha,Entrada,Salida,Promedio Tiempo\n";
        
        foreach ($data as $row) {
            $csv .= sprintf(
                "%s,%s,%s,%s,%s,%s\n",
                $row['id'] ?? '',
                $row['PersonName'] ?? '',
                $row['authDate'] ?? '',
                $row['entrada'] ?? '',
                $row['salida'] ?? '',
                $row['promedio_tiempo'] ?? ''
            );
        }
        
        $response = new \Symfony\Component\HttpFoundation\Response($csv);
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
        
        return $response;
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