<?php
/**
 * Lightweight Firebase Firestore REST Client for PHP
 * 
 * Provides native document operations (CRUD, Query, Listing) using cURL and Firestore REST API v1.
 * Handles automatic encoding/decoding between PHP types and Firestore document values.
 * 
 * @package AmuletPlatform
 */

declare(strict_types=1);

require_once __DIR__ . '/firebase_config.php';

class FirestoreClient
{
    private string $projectId;
    private string $apiKey;
    private string $endpoint;

    public function __construct(?string $projectId = null, ?string $apiKey = null)
    {
        $this->projectId = $projectId ?: (defined('FIREBASE_PROJECT_ID') ? FIREBASE_PROJECT_ID : 'amuletsiam');
        $this->apiKey = $apiKey ?: (defined('FIREBASE_API_KEY') ? FIREBASE_API_KEY : '');
        $this->endpoint = 'https://firestore.googleapis.com/v1/projects/' . $this->projectId . '/databases/(default)/documents/';
    }

    /**
     * Convert PHP primitive/array data into Firestore Typed Value map.
     */
    public static function encodeValue(mixed $value): array
    {
        if (is_null($value)) {
            return ['nullValue' => null];
        }
        if (is_bool($value)) {
            return ['booleanValue' => $value];
        }
        if (is_int($value)) {
            return ['integerValue' => (string)$value];
        }
        if (is_float($value)) {
            return ['doubleValue' => $value];
        }
        if (is_string($value)) {
            return ['stringValue' => $value];
        }
        if (is_array($value)) {
            $isAssoc = array_keys($value) !== range(0, count($value) - 1);
            if ($isAssoc) {
                $fields = [];
                foreach ($value as $k => $v) {
                    $fields[$k] = self::encodeValue($v);
                }
                return ['mapValue' => ['fields' => $fields]];
            } else {
                $values = [];
                foreach ($value as $v) {
                    $values[] = self::encodeValue($v);
                }
                return ['arrayValue' => ['values' => $values]];
            }
        }
        return ['stringValue' => (string)$value];
    }

    /**
     * Decode Firestore Typed Value map into standard PHP types.
     */
    public static function decodeValue(array $field): mixed
    {
        if (array_key_exists('stringValue', $field)) return $field['stringValue'];
        if (array_key_exists('integerValue', $field)) return (int)$field['integerValue'];
        if (array_key_exists('doubleValue', $field)) return (float)$field['doubleValue'];
        if (array_key_exists('booleanValue', $field)) return (bool)$field['booleanValue'];
        if (array_key_exists('nullValue', $field)) return null;
        if (array_key_exists('timestampValue', $field)) return $field['timestampValue'];
        if (array_key_exists('mapValue', $field)) {
            $result = [];
            $subFields = $field['mapValue']['fields'] ?? [];
            foreach ($subFields as $k => $v) {
                $result[$k] = self::decodeValue($v);
            }
            return $result;
        }
        if (array_key_exists('arrayValue', $field)) {
            $result = [];
            $values = $field['arrayValue']['values'] ?? [];
            foreach ($values as $v) {
                $result[] = self::decodeValue($v);
            }
            return $result;
        }
        return null;
    }

    /**
     * Transform a raw Firestore document into a friendly PHP associative array.
     */
    public static function parseDocument(array $doc): array
    {
        $data = [];
        if (!empty($doc['name'])) {
            $parts = explode('/', $doc['name']);
            $data['id'] = end($parts);
            $data['_name'] = $doc['name'];
        }
        if (!empty($doc['fields'])) {
            foreach ($doc['fields'] as $key => $val) {
                $data[$key] = self::decodeValue($val);
            }
        }
        if (!empty($doc['createTime'])) $data['createTime'] = $doc['createTime'];
        if (!empty($doc['updateTime'])) $data['updateTime'] = $doc['updateTime'];
        return $data;
    }

    /**
     * Execute HTTP request via cURL.
     */
    private function request(string $method, string $url, ?array $payload = null): array
    {
        $separator = strpos($url, '?') !== false ? '&' : '?';
        $fullUrl = $url . $separator . 'key=' . urlencode($this->apiKey);

        $ch = curl_init($fullUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        $headers = ['Content-Type: application/json'];
        if ($payload !== null) {
            $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
            $headers[] = 'Content-Length: ' . strlen($json);
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("Firestore cURL Error: " . $error);
        }

        $decoded = json_decode($response, true) ?: [];

        if ($httpCode >= 400) {
            $msg = $decoded['error']['message'] ?? "HTTP {$httpCode} error from Firestore";
            throw new Exception("Firestore API Error ({$httpCode}): {$msg}");
        }

        return $decoded;
    }

    /**
     * Get a single document by collection and document ID.
     */
    public function getDocument(string $collection, string $docId): ?array
    {
        try {
            $url = $this->endpoint . urlencode($collection) . '/' . urlencode((string)$docId);
            $doc = $this->request('GET', $url);
            return self::parseDocument($doc);
        } catch (Exception $e) {
            if (strpos($e->getMessage(), '404') !== false || strpos($e->getMessage(), 'NOT_FOUND') !== false) {
                return null;
            }
            error_log("[FIRESTORE GET ERROR] {$collection}/{$docId}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * List all documents in a collection.
     */
    public function listDocuments(string $collection, int $pageSize = 200): array
    {
        try {
            $url = $this->endpoint . urlencode($collection) . '?pageSize=' . $pageSize;
            $res = $this->request('GET', $url);

            $documents = [];
            foreach ($res['documents'] ?? [] as $doc) {
                $documents[] = self::parseDocument($doc);
            }
            return $documents;
        } catch (Exception $e) {
            error_log("[FIRESTORE LIST ERROR] {$collection}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Create or Overwrite a document with specific document ID.
     */
    public function setDocument(string $collection, string $docId, array $data): array
    {
        $fields = [];
        foreach ($data as $k => $v) {
            if ($k === 'id' || $k === '_name' || $k === 'createTime' || $k === 'updateTime') continue;
            $fields[$k] = self::encodeValue($v);
        }

        $url = $this->endpoint . urlencode($collection) . '/' . urlencode((string)$docId);
        $res = $this->request('PATCH', $url, ['fields' => $fields]);
        return self::parseDocument($res);
    }

    /**
     * Add a new document with an auto-generated Firestore ID or custom ID.
     */
    public function addDocument(string $collection, array $data, ?string $customId = null): array
    {
        if ($customId !== null) {
            return $this->setDocument($collection, $customId, $data);
        }

        $fields = [];
        foreach ($data as $k => $v) {
            if ($k === 'id') continue;
            $fields[$k] = self::encodeValue($v);
        }

        $url = $this->endpoint . urlencode($collection);
        $res = $this->request('POST', $url, ['fields' => $fields]);
        return self::parseDocument($res);
    }

    /**
     * Update specific fields in a document.
     */
    public function updateDocument(string $collection, string $docId, array $data): array
    {
        $existing = $this->getDocument($collection, $docId) ?: [];
        $merged = array_merge($existing, $data);
        return $this->setDocument($collection, $docId, $merged);
    }

    /**
     * Delete a document.
     */
    public function deleteDocument(string $collection, string $docId): bool
    {
        try {
            $url = $this->endpoint . urlencode($collection) . '/' . urlencode((string)$docId);
            $this->request('DELETE', $url);
            return true;
        } catch (Exception $e) {
            error_log("[FIRESTORE DELETE ERROR] {$collection}/{$docId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate next incremental integer string ID for a collection.
     */
    public function getNextId(string $collection): string
    {
        $docs = $this->listDocuments($collection, 300);
        $maxId = 0;
        foreach ($docs as $d) {
            $val = (int)($d['id'] ?? 0);
            if ($val > $maxId) {
                $maxId = $val;
            }
        }
        return (string)($maxId + 1);
    }
}

/**
 * Global Helper to obtain shared Firestore Client instance.
 */
function get_firestore(): FirestoreClient
{
    static $client = null;
    if ($client === null) {
        $client = new FirestoreClient();
    }
    return $client;
}
