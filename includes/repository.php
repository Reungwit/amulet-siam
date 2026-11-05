<?php
/**
 * Unified Database Access Layer (Firestore / MySQL Adapter)
 * 
 * Provides unified repository functions for Amulets, Categories, Users,
 * Auctions, Bids, and Orders. Supports Firestore seamlessly with automatic
 * MySQL fallback if Firestore credentials or security rules are restricted.
 * 
 * @package AmuletPlatform
 */

declare(strict_types=1);

require_once __DIR__ . '/firebase_config.php';
require_once __DIR__ . '/firestore_client.php';

class AmuletRepository
{
    private FirestoreClient $firestore;
    private ?PDO $pdo = null;
    private bool $useFirestore = true;

    public function __construct()
    {
        $this->firestore = get_firestore();
        // Check if API key is populated
        if (FIREBASE_API_KEY === 'YOUR_FIREBASE_API_KEY_HERE') {
            $this->useFirestore = false;
        }
    }

    private function getPdo(): PDO
    {
        if ($this->pdo === null) {
            $this->pdo = get_db_connection();
        }
        return $this->pdo;
    }

    /**
     * Get all categories.
     */
    public function getCategories(): array
    {
        if ($this->useFirestore) {
            try {
                $docs = $this->firestore->listDocuments('categories');
                if (!empty($docs)) {
                    usort($docs, fn($a, $b) => strcmp($a['name'] ?? '', $b['name'] ?? ''));
                    return $docs;
                }
            } catch (Exception $e) {
                error_log('[DB ADAPTER] Firestore getCategories error, using PDO: ' . $e->getMessage());
            }
        }
        return $this->getPdo()->query("SELECT * FROM categories ORDER BY id ASC")->fetchAll();
    }

    /**
     * Get available amulets with optional filtering.
     */
    public function getAvailableAmulets(string $search = '', int $categoryId = 0, string $typeFilter = ''): array
    {
        if ($this->useFirestore) {
            try {
                $docs = $this->firestore->listDocuments('amulets');
                if (!empty($docs)) {
                    $results = [];
                    foreach ($docs as $d) {
                        $isAvail = !empty($d['is_available']);
                        if (!$isAvail) continue;

                        if ($categoryId > 0 && (int)($d['category_id'] ?? 0) !== $categoryId) {
                            continue;
                        }
                        if (!empty($typeFilter) && ($d['type'] ?? '') !== $typeFilter) {
                            continue;
                        }
                        if (!empty($search)) {
                            $term = mb_strtolower($search, 'UTF-8');
                            $title = mb_strtolower($d['title'] ?? '', 'UTF-8');
                            $monk = mb_strtolower($d['monk_name'] ?? '', 'UTF-8');
                            $temple = mb_strtolower($d['temple_name'] ?? '', 'UTF-8');
                            if (!str_contains($title, $term) && !str_contains($monk, $term) && !str_contains($temple, $term)) {
                                continue;
                            }
                        }
                        $results[] = $d;
                    }
                    return $results;
                }
            } catch (Exception $e) {
                error_log('[DB ADAPTER] Firestore getAvailableAmulets error, using PDO: ' . $e->getMessage());
            }
        }

        // MySQL fallback query
        $sql = "SELECT a.*, c.name AS category_name, 
                       s.protection, s.popularity, s.charm, s.evasion, s.invincibility, s.wealth
                FROM amulets a
                LEFT JOIN categories c ON a.category_id = c.id
                LEFT JOIN amulet_stats s ON a.id = s.amulet_id
                WHERE a.is_available = 1";
        $params = [];
        if (!empty($search)) {
            $sql .= " AND (a.title LIKE :s OR a.monk_name LIKE :s OR a.temple_name LIKE :s)";
            $params['s'] = "%{$search}%";
        }
        if ($categoryId > 0) {
            $sql .= " AND a.category_id = :cid";
            $params['cid'] = $categoryId;
        }
        if (!empty($typeFilter)) {
            $sql .= " AND a.type = :type";
            $params['type'] = $typeFilter;
        }
        $sql .= " ORDER BY a.id DESC";

        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Get single amulet by ID.
     */
    public function getAmuletById(int|string $id): ?array
    {
        $idStr = (string)$id;
        if ($this->useFirestore) {
            try {
                $doc = $this->firestore->getDocument('amulets', $idStr);
                if ($doc) return $doc;
            } catch (Exception $e) {
                error_log('[DB ADAPTER] Firestore getAmuletById error, using PDO: ' . $e->getMessage());
            }
        }

        $stmt = $this->getPdo()->prepare("SELECT a.*, c.name AS category_name,
                                                 s.protection, s.popularity, s.charm, s.evasion, s.invincibility, s.wealth
                                          FROM amulets a
                                          LEFT JOIN categories c ON a.category_id = c.id
                                          LEFT JOIN amulet_stats s ON a.id = s.amulet_id
                                          WHERE a.id = :id LIMIT 1");
        $stmt->execute(['id' => (int)$id]);
        $res = $stmt->fetch();
        if ($res) {
            $res['stats'] = [
                'protection' => (int)($res['protection'] ?? 5),
                'popularity' => (int)($res['popularity'] ?? 5),
                'charm' => (int)($res['charm'] ?? 5),
                'evasion' => (int)($res['evasion'] ?? 5),
                'invincibility' => (int)($res['invincibility'] ?? 5),
                'wealth' => (int)($res['wealth'] ?? 5),
            ];
            return $res;
        }
        return null;
    }
}

/**
 * Global Helper to get Amulet Repository
 */
function get_amulet_repository(): AmuletRepository
{
    static $repo = null;
    if ($repo === null) {
        $repo = new AmuletRepository();
    }
    return $repo;
}

