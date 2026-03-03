<?php
/**
 * Gemini AI Service
 *
 * Connects to Google's Gemini API for intelligent task suggestions.
 */

class GeminiService {
    private $apiKey;
    private $model;
    private $apiUrl;

    public function __construct() {
        $this->apiKey = defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '';
        $this->model  = defined('GEMINI_MODEL')   ? GEMINI_MODEL   : 'models/gemini-2.5-flash';
        $this->apiUrl = "https://generativelanguage.googleapis.com/v1beta/{$this->model}:generateContent?key={$this->apiKey}";
    }

    public function getTaskSuggestion($title) {
        if (empty($this->apiKey)) {
            throw new Exception("Gemini API key is missing. Please set GEMINI_API_KEY in your .env file.", 500);
        }

        $prompt = "You are a helpful to-do list assistant. A user has given a task title. "
                . "Provide a brief, helpful description for this task. If it's complex, suggest 2-3 sub-tasks using bullet points. Keep it under 60 words. "
                . "Task Title: \"{$title}\"";

        $payload = [
            'contents' => [
                ['parts' => [['text' => $prompt]]]
            ],
            'generationConfig' => [
                'maxOutputTokens' => 250,
                'temperature'     => 0.7
            ]
        ];

        $jsonData = json_encode($payload);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($jsonData)
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);

        $response  = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($curl_error) {
            throw new Exception("cURL Error: " . $curl_error, 500);
        }

        if ($http_code !== 200) {
            $this->logDebug($response, $http_code);
            throw new Exception("Gemini API returned HTTP {$http_code}. See debug file.", $http_code);
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            $this->logDebug($response, $http_code);
            throw new Exception("Invalid JSON from Gemini. See debug file.", 500);
        }

        $candidatesToTry = [
            'candidates.0.content.parts.0.text',
            'candidates.0.output.0.content.0.text',
            'output.0.content.0.text',
            'candidates.0.content.text',
            'candidates.0.text',
            'content.0.parts.0.text',
            'response.outputText',
        ];

        foreach ($candidatesToTry as $path) {
            $value = $this->dig($data, $path);
            if ($value !== null && is_string($value) && strlen(trim($value)) > 0) {
                return trim($value);
            }
        }

        $fuzzy = $this->findLikelyText($data);
        if ($fuzzy !== null) {
            return trim($fuzzy);
        }

        $debugFile = $this->logDebug($response, $http_code);
        throw new Exception("Unexpected Gemini response structure. Raw response saved to: {$debugFile}", 500);
    }

    private function dig(array $array, $path) {
        $parts = explode('.', $path);
        $cur = $array;
        foreach ($parts as $p) {
            if (is_array($cur) && array_key_exists($p, $cur)) {
                $cur = $cur[$p];
            } elseif (preg_match('/^\d+$/', $p) && is_array($cur)) {
                $idx = (int)$p;
                if (array_key_exists($idx, $cur)) {
                    $cur = $cur[$idx];
                } else {
                    return null;
                }
            } else {
                return null;
            }
        }
        return $cur;
    }

    private function findLikelyText($node) {
        if (is_string($node) && strlen(trim($node)) > 5) {
            return $node;
        }
        if (is_array($node)) {
            foreach ($node as $k => $v) {
                $found = $this->findLikelyText($v);
                if ($found !== null) return $found;
            }
        }
        return null;
    }

    private function logDebug($responseBody, $httpCode = null) {
        $dir = __DIR__ . '/../../storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $filename = $dir . '/gemini_debug_' . date('Ymd_His') . '.json';
        $payload = [
            'timestamp'    => date('c'),
            'http_code'    => $httpCode,
            'api_url'      => $this->apiUrl,
            'raw_response' => $responseBody
        ];
        @file_put_contents($filename, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return $filename;
    }
}
