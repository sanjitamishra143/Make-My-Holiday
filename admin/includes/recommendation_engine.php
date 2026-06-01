<?php
require_once __DIR__ . '/../config.php';

class RecommendationEngine {

    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    //  STEP 1 — Build a PACKAGE feature vector
    public function buildPackageVector(array $package): array {
        $vector = [];

        // One-hot encode: category
        foreach ($this->getAllCategories() as $cat) {
            $vector['cat_' . strtolower($cat)] =
                (strtolower($package['category'] ?? '') === strtolower($cat)) ? 1 : 0;
        }

        // Normalize price to [0, 1]  (max budget ceiling: 200,000 NPR)
        $vector['price'] = min(($package['price'] ?? 0) / 200000, 1);

        // Normalize duration to [0, 1]  (max: 30 days)
        $vector['duration'] = min(($package['duration_days'] ?? 0) / 30, 1);

        // One-hot encode: destination/activity tags
        $pkgTags = array_map('strtolower', explode(',', $package['tags'] ?? ''));
        foreach ($this->getAllTags() as $tag) {
            $vector['tag_' . strtolower($tag)] =
                in_array(strtolower($tag), $pkgTags) ? 1 : 0;
        }

        return $vector;
    }

    //  STEP 2 — Build a TOURIST preference vector
    public function buildTouristVector(array $preferences): array {
        $vector   = [];
        $prefCats = array_map('strtolower', (array)($preferences['categories'] ?? []));

        foreach ($this->getAllCategories() as $cat) {
            $vector['cat_' . strtolower($cat)] =
                in_array(strtolower($cat), $prefCats) ? 1 : 0;
        }

        $vector['price']    = min(($preferences['max_budget']     ?? 100000) / 200000, 1);
        $vector['duration'] = min(($preferences['preferred_days'] ?? 7)      / 30,     1);

        $prefTags = array_map('strtolower', (array)($preferences['tags'] ?? []));
        foreach ($this->getAllTags() as $tag) {
            $vector['tag_' . strtolower($tag)] =
                in_array(strtolower($tag), $prefTags) ? 1 : 0;
        }

        return $vector;
    }

    //  STEP 3 — COSINE SIMILARITY
    //  Formula:  similarity(A, B) = (A · B) / (||A|| × ||B||)
 
    public function cosineSimilarity(array $vecA, array $vecB): float {
        $keys = array_unique(array_merge(array_keys($vecA), array_keys($vecB)));

        $dot  = 0.0;
        $magA = 0.0;
        $magB = 0.0;

        foreach ($keys as $k) {
            $a    = $vecA[$k] ?? 0;
            $b    = $vecB[$k] ?? 0;
            $dot  += $a * $b;
            $magA += $a * $a;
            $magB += $b * $b;
        }

        $magA = sqrt($magA);
        $magB = sqrt($magB);

        // Guard: avoid division by zero
        if ($magA == 0 || $magB == 0) return 0.0;

        return $dot / ($magA * $magB);
    }

    //  STEP 4 — Get TOP-N recommendations for a tourist
    public function getRecommendations(int $touristId, array $preferences, int $topN = 5): array {
        $packages      = $this->getAllPackages();
        $touristVector = $this->buildTouristVector($preferences);
        $scored        = [];

        foreach ($packages as $pkg) {
            $pkgVector = $this->buildPackageVector($pkg);
            $score     = $this->cosineSimilarity($touristVector, $pkgVector);
            $scored[]  = [
                'package'    => $pkg,
                'similarity' => round($score, 4),
            ];
        }

        // Sort by similarity (highest first)
        usort($scored, fn($a, $b) => $b['similarity'] <=> $a['similarity']);

        $results = array_slice($scored, 0, $topN);
        $this->logRecommendations($touristId, $results);

        return $results;
    }

    //  STEP 5 — Load saved tourist preferences from DB
    public function getTouristPreferences(int $touristId): ?array {
        $stmt = $this->conn->prepare(
            "SELECT categories, max_budget, preferred_days, destination_tags
             FROM tourist_preferences WHERE tourist_id = ? LIMIT 1"
        );
        $stmt->bind_param("i", $touristId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if (!$row) return null;

        return [
            'categories'     => array_filter(explode(',', $row['categories']       ?? '')),
            'max_budget'     => (float)$row['max_budget'],
            'preferred_days' => (int)$row['preferred_days'],
            'tags'           => array_filter(explode(',', $row['destination_tags'] ?? '')),
        ];
    }

    // Private DB helpers 

    private function getAllPackages(): array {
        $sql = "SELECT p.*, c.name AS category,
                       IFNULL(GROUP_CONCAT(DISTINCT t.tag_name ORDER BY t.tag_name), '') AS tags
                FROM packages p
                LEFT JOIN categories  c  ON c.id  = p.category_id
                LEFT JOIN package_tags pt ON pt.package_id = p.id
                LEFT JOIN tags         t  ON t.id  = pt.tag_id
                WHERE p.status = 'active'
                GROUP BY p.id";
        $res = $this->conn->query($sql);
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }

    private function getAllCategories(): array {
        $res = $this->conn->query("SELECT name FROM categories ORDER BY name");
        return $res ? array_column($res->fetch_all(MYSQLI_ASSOC), 'name') : [];
    }

    private function getAllTags(): array {
        $res = $this->conn->query("SELECT tag_name FROM tags ORDER BY tag_name");
        return $res ? array_column($res->fetch_all(MYSQLI_ASSOC), 'tag_name') : [];
    }

    private function logRecommendations(int $touristId, array $results): void {
        $stmt = $this->conn->prepare(
            "INSERT INTO recommendation_logs (tourist_id, package_id, similarity_score, recommended_at)
             VALUES (?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE similarity_score = VALUES(similarity_score),
                                     recommended_at   = NOW()"
        );
        foreach ($results as $item) {
            $pkgId = (int)$item['package']['id'];
            $score = $item['similarity'];
            $stmt->bind_param("iid", $touristId, $pkgId, $score);
            $stmt->execute();
        }
    }
}