<?php

class WhatsAppClient
{
    private $config;

    public function __construct($config)
    {
        $this->config = $config;
    }

    public function isConfigured()
    {
        return $this->config['phone_number_id'] !== '' && $this->config['access_token'] !== '';
    }

    public function sendText($to, $body)
    {
        return $this->send($to, array(
            'type' => 'text',
            'text' => array(
                'preview_url' => false,
                'body' => $body,
            ),
        ));
    }

    public function sendTemplate($to, $template, $language, $bodyParameters, $buttonParameters)
    {
        $components = array();
        if (!empty($bodyParameters)) {
            $components[] = array(
                'type' => 'body',
                'parameters' => $this->textParameters($bodyParameters),
            );
        }
        foreach ($buttonParameters as $index => $value) {
            $components[] = array(
                'type' => 'button',
                'sub_type' => 'url',
                'index' => (string) $index,
                'parameters' => array(
                    array('type' => 'text', 'text' => (string) $value),
                ),
            );
        }

        $payload = array(
            'type' => 'template',
            'template' => array(
                'name' => $template,
                'language' => array('code' => $language),
            ),
        );
        if (!empty($components)) {
            $payload['template']['components'] = $components;
        }

        return $this->send($to, $payload);
    }

    private function send($to, $message)
    {
        $url = 'https://graph.facebook.com/' . $this->config['graph_version']
            . '/' . rawurlencode($this->config['phone_number_id']) . '/messages';

        $payload = array_merge(array(
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
        ), $message);

        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => array(
                'Authorization: Bearer ' . $this->config['access_token'],
                'Content-Type: application/json',
            ),
            CURLOPT_POSTFIELDS => json_encode($payload),
        ));
        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno) {
            return array('ok' => false, 'status' => 0, 'error' => $error);
        }

        $decoded = json_decode($raw, true);
        if ($status >= 200 && $status < 300 && isset($decoded['messages'][0]['id'])) {
            return array(
                'ok' => true,
                'status' => $status,
                'message_id' => $decoded['messages'][0]['id'],
            );
        }

        $messageText = 'WhatsApp request failed';
        if (isset($decoded['error']['message'])) {
            $messageText = $decoded['error']['message'];
        }

        return array(
            'ok' => false,
            'status' => $status,
            'error' => $messageText,
            'response' => $decoded,
        );
    }

    private function textParameters($values)
    {
        $parameters = array();
        foreach ($values as $value) {
            $parameters[] = array('type' => 'text', 'text' => (string) $value);
        }
        return $parameters;
    }
}
