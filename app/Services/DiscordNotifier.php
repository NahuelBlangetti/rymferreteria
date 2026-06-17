<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class DiscordNotifier
{
    /**
     * Envía un embed al webhook de Discord configurado en services.discord.webhook_url.
     * Pensado como punto único de notificaciones de la app (uso de IA, errores, etc.).
     */
    public function notify(string $title, string $description, int $color = 0x5865F2): void
    {
        $webhookUrl = config('services.discord.webhook_url');

        if (! $webhookUrl) {
            return;
        }

        try {
            Http::timeout(5)->post($webhookUrl, [
                'embeds' => [[
                    'title' => $title,
                    'description' => $description,
                    'color' => $color,
                ]],
            ]);
        } catch (\Throwable) {
            // Discord no disponible: no debe interrumpir el flujo principal.
        }
    }

    public function notifyOpenAiUsage(string $context, int $promptTokens, int $completionTokens, float $estimatedCost): void
    {
        $this->notify(
            'Uso de OpenAI',
            sprintf(
                "**Contexto:** %s\n**Tokens de entrada:** %s\n**Tokens de salida:** %s\n**Costo estimado (entrada):** USD %s",
                $context,
                number_format($promptTokens),
                number_format($completionTokens),
                number_format($estimatedCost, 4)
            ),
            0x10A37F
        );
    }
}
