<?php

namespace App\Http\Controllers;

use App\Services\WhatsappNodeService;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;

class WhatsappController extends Controller
{
    public function __construct(protected WhatsappNodeService $whatsappNodeService) {}

    public function index()
    {
        $health = $this->whatsappNodeService->health();
        $status = $this->whatsappNodeService->status();

        return view('admin.whatsapp.index', [
            'nodeHealth' => $health,
            'initialStatus' => $status,
        ]);
    }

    public function status(): JsonResponse
    {
        $status = $this->whatsappNodeService->status();

        if (!($status['success'] ?? false)) {
            return response()->json($status, 502);
        }

        return response()->json($status);
    }

    public function qr(): JsonResponse
    {
        $qr = $this->whatsappNodeService->qr();

        if (!($qr['success'] ?? false)) {
            return response()->json($qr, 502);
        }

        return response()->json($qr);
    }

    public function qrImage(): Response|JsonResponse
    {
        $qrImage = $this->whatsappNodeService->qrImage();

        if (!($qrImage['success'] ?? false)) {
            return response()->json($qrImage, 502);
        }

        return response($qrImage['svg'], 200, [
            'Content-Type' => 'image/svg+xml; charset=utf-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
        ]);
    }

    public function connect(): JsonResponse
    {
        $status = $this->whatsappNodeService->status();
        $qr = $this->whatsappNodeService->qr();

        return response()->json([
            'success' => ($status['success'] ?? false) && ($qr['success'] ?? false),
            'status' => $status,
            'qr' => $qr,
            'message' => 'Conexão iniciada no processo do Node. Escaneie o QR abaixo se disponível.',
        ]);
    }
}
