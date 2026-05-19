<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\PaymentProof;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PaymentProofController extends Controller
{
    /**
     * Mostrar el formulario público para subir soporte de pago.
     */
    public function show(string $token)
    {
        $proof = PaymentProof::where('token', $token)->first();

        if (!$proof) {
            return view('payment-proofs.invalid', [
                'message' => 'Este enlace no es válido.',
            ]);
        }

        if ($proof->isExpired()) {
            $proof->update(['status' => 'expired']);
            return view('payment-proofs.invalid', [
                'message' => 'Este enlace ha expirado. Solicita uno nuevo a tu asesor.',
            ]);
        }

        if ($proof->status === 'uploaded' || $proof->status === 'downloaded') {
            return view('payment-proofs.success', [
                'alreadyUploaded' => true,
                'proof' => $proof,
            ]);
        }

        return view('payment-proofs.upload', [
            'proof' => $proof,
        ]);
    }

    /**
     * Procesar la subida del soporte de pago.
     */
    public function upload(Request $request, string $token)
    {
        $proof = PaymentProof::where('token', $token)->first();

        if (!$proof) {
            return back()->withErrors(['file' => 'Enlace no válido.']);
        }

        if (!$proof->canUpload()) {
            return back()->withErrors(['file' => 'Este enlace ya no está disponible.']);
        }

        $request->validate([
            'file' => 'required|file|mimes:jpg,jpeg,png,pdf|max:10240',
        ], [
            'file.required' => 'Debes seleccionar un archivo.',
            'file.mimes' => 'Solo se permiten imágenes (JPG, PNG) o PDF.',
            'file.max' => 'El archivo no debe superar 10MB.',
        ]);

        try {
            $file = $request->file('file');
            $extension = $file->getClientOriginalExtension();
            $filename = 'soporte_' . $proof->id . '_' . time() . '.' . $extension;
            $path = 'payment-proofs/' . date('Y/m') . '/' . $filename;

            Storage::disk('public')->putFileAs(
                'payment-proofs/' . date('Y/m'),
                $file,
                $filename
            );

            $proof->update([
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'status' => 'uploaded',
                'uploaded_at' => now(),
            ]);

            // Crear mensaje en el chat para que el agente lo vea
            $this->createChatMessage($proof);

            return view('payment-proofs.success', [
                'alreadyUploaded' => false,
                'proof' => $proof,
            ]);
        } catch (\Exception $e) {
            Log::error('Error uploading payment proof', [
                'token' => $token,
                'error' => $e->getMessage(),
            ]);
            return back()->withErrors(['file' => 'Ocurrió un error al subir el archivo. Intenta de nuevo.']);
        }
    }

    /**
     * Crear un mensaje en el chat con la imagen del soporte.
     */
    private function createChatMessage(PaymentProof $proof): void
    {
        try {
            $isImage = str_starts_with($proof->mime_type ?? '', 'image/');
            $mediaUrl = Storage::disk('public')->url($proof->file_path);

            Message::create([
                'contact_id' => $proof->contact_id,
                'channel_id' => $proof->channel_id,
                'message_id' => 'payment_proof_' . $proof->id,
                'direction' => 'incoming',
                'type' => $isImage ? 'image' : 'document',
                'content' => '💰 Soporte de pago adjuntado por el cliente',
                'media_url' => $mediaUrl,
                'media_mime_type' => $proof->mime_type,
                'status' => 'delivered',
                'is_read' => false,
                'metadata' => [
                    'payment_proof_id' => $proof->id,
                    'is_payment_proof' => true,
                ],
                'sent_at' => now(),
            ]);

            // Disparar evento de broadcasting para notificar al agente en tiempo real
            try {
                $message = Message::where('message_id', 'payment_proof_' . $proof->id)->first();
                if ($message) {
                    broadcast(new \App\Events\NewWhatsAppMessage($message));
                }
            } catch (\Exception $e) {
                // Broadcasting no crítico
            }
        } catch (\Exception $e) {
            Log::warning('Error creating chat message for payment proof', [
                'proof_id' => $proof->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
