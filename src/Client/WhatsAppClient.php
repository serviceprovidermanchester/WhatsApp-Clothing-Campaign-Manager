<?php

declare(strict_types=1);

namespace SPM\WhatsApp\Client;

/**
 * WhatsApp Cloud API Client
 * Built by SPManchester Private Limited (https://www.spmanchester.com).
 * Architect: Sayed Mudassar Akbar
 */
class WhatsAppClient
{
    private string $graphVersion;
    private string $phoneNumberId;
    private string $accessToken;

    public function __construct(string $graphVersion, string $phoneNumberId, string $accessToken)
    {
        $this->graphVersion = $graphVersion;
        $this->phoneNumberId = $phoneNumberId;
        $this->accessToken = $accessToken;
    }

    public function sendTemplateMessage(string $recipientPhone, array $campaign): array
    {
        $components = [];

        if (!empty($campaign['image_url'])) {
            $components[] = [
                'type' => 'header',
                'parameters' => [
                    [
                        'type' => 'image',
                        'image' => ['link' => $campaign['image_url']]
                    ]
                ]
            ];
        }

        if (!empty($campaign['website_url'])) {
            $components[] = [
                'type' => 'body',
                'parameters' => [
                    [
                        'type' => 'text',
                        'text' => $campaign['website_url']
                    ]
                ]
            ];
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $recipientPhone,
            'type' => 'template',
            'template' => [
                'name' => $campaign['template_name'],
                'language' => ['code' => $campaign['language'] ?? 'en']
            ]
        ];

        if (!empty($components)) {
            $payload['template']['components'] = $components;
        }

        $url = sprintf(
            'https://graph.facebook.com/%s/%s/messages',
            rawurlencode($this->graphVersion),
            rawurlencode($this->phoneNumberId)
        );

        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->accessToken,
                'Content-Type: application/json'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 25
        ]);

        $response = curl_exec($curl);
        $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = curl_error($curl);
        curl_close($curl);

        $data = json_decode((string) $response, true) ?: [];

        return [
            'success' => ($httpCode >= 200 && $httpCode < 300 && !empty($data['messages'][0]['id'])),
            'message_id' => $data['messages'][0]['id'] ?? null,
            'error' => $data['error']['message'] ?? ($curlError ?: 'HTTP Code ' . $httpCode),
            'http_code' => $httpCode
        ];
    }
}
