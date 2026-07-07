<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Mail\SupportRequestMail;

class SupportController extends Controller
{
    /**
     * Enviar mensagem de suporte
     */
    public function send(Request $request)
    {
        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'message' => 'required|string|max:5000',
        ], [
            'subject.required' => 'Assunto é obrigatório',
            'subject.max' => 'Assunto não pode ter mais de 255 caracteres',
            'message.required' => 'Mensagem é obrigatória',
            'message.max' => 'Mensagem não pode ter mais de 5000 caracteres',
        ]);

        try {
            // Enviar email para o suporte
            Mail::to(config('mail.support_email', 'luanacarvalhomesquita@gmail.com'))
                ->send(new SupportRequestMail(
                    $request->user(),
                    $validated['subject'],
                    $validated['message']
                ));

            return response()->json([
                'message' => 'Mensagem enviada com sucesso!',
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Erro ao enviar mensagem. Tente novamente mais tarde.',
            ], 500);
        }
    }
}
