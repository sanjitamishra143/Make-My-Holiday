<?php
/**
 * ============================================================
 * MAKE MY HOLIDAY — Collaborative Filtering Algorithm
 * File: recommendation.php
 * ============================================================
 *
 * HOW COLLABORATIVE FILTERING WORKS (IMPLICIT / COSINE):
 * ─────────────────────────────────────────────────────────────
 * Instead of relying on star ratings, this system uses
 * IMPLICIT FEEDBACK — i.e., booking and interaction history.
 *
 * Each tourist is represented as a BINARY VECTOR in package space:
 *
 *           PKG1  PKG2  PKG3  PKG4  PKG5
 *  User 1 [  1,    1,    0,    1,    0  ]
 *  User 2 [  1,    0,    1,    0,    1  ]
 *  User 3 [  0,    1,    1,    1,    0  ]
 *
 *  1 = booked or reviewed this package
 *  0 = no interaction
 *
 * SIMILARITY is measured using COSINE SIMILARITY:
 *
 *  cos(θ) = (A · B) / (||A|| × ||B||)
 *
 *  Result:
 *   1.0 = identical booking behaviour
 *   0.0 = no packages in common
 *
 * "Users who booked similar packages are similar users."
 * This is called: Implicit User-Based Collaborative Filtering.
 * ============================================================
 */

require_once 'config.php';

// ================================================================
//  CLASS: CollaborativeFilter
// ================================================================
class CollaborativeFilter {

    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    // ────────────────────────────────────────────────────────────
    //  STEP 1 — Build Binary Interaction Matrix
    //
    //  Creates a matrix like:
    //
    //           PKG1  PKG2  PKG3  PKG4  PKG5
    //  User 1 [  1,    1,    0,    1,    0  ]
    //  User 2 [  1,    0,    1,    0,    1  ]
    //  User 3 [  0,    1,    1,    1,    0  ]
    //
    //  1 = tourist has booked OR reviewed this package
    //  0 = no interaction (not stored — sparse matrix)
    //
    //  NOTE: We use binary implicit signals instead of star ratings.
    //  Booking = strong implicit signal of interest.
    //  Approved review = also a strong signal of engagement.
    // ────────────────────────────────────────────────────────────
    public function buildRatingMatrix(): array {
        $matrix = [];

        // Signal 1: Approved reviews → tourist engaged with this package
        $reviews = $this->conn->query("
            SELECT tourist_id, package_id
            FROM comments
            WHERE status = 'Approved'
        ");
        while ($row = $reviews->fetch_assoc()) {
            $matrix[$row['tourist_id']][$row['package_id']] = 1;
        }

        // Signal 2: Bookings (any status except Cancelled)
        // Booking = clear intent/interaction signal
        $bookings = $this->conn->query("
            SELECT tourist_id, package_id
            FROM bookings
            WHERE status != 'Cancelled'
        ");
        while ($row = $bookings->fetch_assoc()) {
            // Mark as 1 regardless of whether review already exists
            $matrix[$row['tourist_id']][$row['package_id']] = 1;
        }

        return $matrix;
    }

    // ────────────────────────────────────────────────────────────
    //  STEP 2 — Calculate Cosine Similarity
    //
    //  Measures how similarly two tourists interact with packages.
    //
    //  Formula:
    //  cos(θ) = (A · B) / (||A|| × ||B||)
    //
    //  Where:
    //   A · B   = dot product  = number of packages BOTH booked
    //   ||A||   = magnitude of A = sqrt(number of packages A booked)
    //   ||B||   = magnitude of B = sqrt(number of packages B booked)
    //
    //  Because values are binary (0 or 1):
    //   A · B   = count of common packages
    //   ||A||   = sqrt(total packages booked by A)
    //   ||B||   = sqrt(total packages booked by B)
    //
    //  Result:
    //   1.0 = identical booking pattern
    //   0.0 = no packages in common (completely dissimilar)
    // ────────────────────────────────────────────────────────────
    public function cosineSimilarity(array $vectorA, array $vectorB): float {
        // Dot product = number of packages both tourists booked
        $dotProduct = 0.0;
        foreach ($vectorA as $pkgId => $valA) {
            if (isset($vectorB[$pkgId])) {
                $dotProduct += $valA * $vectorB[$pkgId];
            }
        }

        // If no packages in common, similarity is 0
        if ($dotProduct == 0.0) {
            return 0.0;
        }

        // Magnitudes (for binary vectors: sqrt of count of 1s)
        $magnitudeA = sqrt(array_sum(array_map(fn($v) => $v * $v, $vectorA)));
        $magnitudeB = sqrt(array_sum(array_map(fn($v) => $v * $v, $vectorB)));

        if ($magnitudeA == 0.0 || $magnitudeB == 0.0) {
            return 0.0;
        }

        return $dotProduct / ($magnitudeA * $magnitudeB);
    }

    // ────────────────────────────────────────────────────────────
    //  STEP 3 — Find Similar Tourists (Neighbours)
    //
    //  Compares target tourist against ALL other tourists
    //  using Cosine Similarity on their booking vectors.
    //  Returns top N most similar tourists (neighbours).
    // ────────────────────────────────────────────────────────────
    public function findSimilarTourists(int $targetId, array $matrix, int $topN = 5): array {
        $similarities = [];

        if (!isset($matrix[$targetId])) {
            return [];
        }

        $targetVector = $matrix[$targetId];

        foreach ($matrix as $userId => $vector) {
            if ($userId === $targetId) continue;

            $similarity = $this->cosineSimilarity($targetVector, $vector);

            // Only include tourists with at least some similarity
            if ($similarity > 0.0) {
                $similarities[$userId] = $similarity;
            }
        }

        // Sort by similarity descending — most similar neighbours first
        arsort($similarities);

        return array_slice($similarities, 0, $topN, true);
    }

    // ────────────────────────────────────────────────────────────
    //  STEP 4 — Score Unseen Packages Using Neighbour Interactions
    //
    //  For each package the target tourist has NOT interacted with:
    //  Calculates a recommendation score as a weighted sum of
    //  neighbour similarity scores.
    //
    //  Formula (weighted vote):
    //
    //  score(i) = Σ [ sim(target, neighbour) × interaction(neighbour, i) ]
    //             ─────────────────────────────────────────────────────────
    //                          Σ sim(target, neighbour)
    //
    //  Because interaction is binary (1 or 0):
    //  score(i) = Σ sim(target, neighbour)  for all neighbours who booked i
    //             ──────────────────────────────────────────────────────────
    //                        Σ all neighbour similarities
    //
    //  Higher score = more (and more similar) neighbours booked this package.
    //
    //  NOTE: No mean-centering is needed (unlike Pearson/explicit ratings)
    //  because we are working with binary implicit feedback, not numeric
    //  star ratings. The weighted vote directly reflects neighbour interest.
    // ────────────────────────────────────────────────────────────
    public function predictRatings(int $targetId, array $matrix, array $neighbours): array {
        $scores        = [];
        $targetVector  = $matrix[$targetId] ?? [];

        // Total similarity weight across all neighbours
        $totalSimilarity = array_sum($neighbours);

        // Get all ACTIVE package IDs from DB
        $pkgResult = $this->conn->query("
            SELECT id
            FROM packages
            WHERE status = 'Active'
        ");
        $allPackages = array_column(
            $pkgResult->fetch_all(MYSQLI_ASSOC),
            'id'
        );

        // Get packages already booked by this tourist (to exclude)
        $bookedPackages = [];
        $bookedQuery = $this->conn->query("
            SELECT package_id
            FROM bookings
            WHERE tourist_id = $targetId
        ");
        while ($row = $bookedQuery->fetch_assoc()) {
            $bookedPackages[] = $row['package_id'];
        }

        // Score each package the tourist has not interacted with
        foreach ($allPackages as $pkgId) {

            // Skip packages already in tourist's interaction vector
            if (isset($targetVector[$pkgId])) {
                continue;
            }

            // Skip already booked packages
            if (in_array($pkgId, $bookedPackages)) {
                continue;
            }

            $weightedVote = 0.0;

            // Accumulate similarity weight from neighbours who interacted
            foreach ($neighbours as $neighbourId => $similarity) {
                if (isset($matrix[$neighbourId][$pkgId])) {
                    // Neighbour booked this package → add their similarity weight
                    $weightedVote += $similarity * $matrix[$neighbourId][$pkgId];
                }
            }

            // Only recommend if at least one neighbour interacted
            if ($weightedVote == 0.0) {
                continue;
            }

            // Normalise by total neighbour similarity to get a 0–1 score
            $scores[$pkgId] = round($weightedVote / $totalSimilarity, 4);
        }

        // Sort highest scored packages first
        arsort($scores);

        return $scores;
    }

    // ────────────────────────────────────────────────────────────
    //  STEP 5 — Get Full Package Details for Recommendations
    // ────────────────────────────────────────────────────────────
    public function getPackageDetails(array $packageIds): array {
        if (empty($packageIds)) return [];

        $ids    = implode(',', array_map('intval', $packageIds));
        $result = $this->conn->query("
            SELECT p.*, c.name as category_name
            FROM packages p
            LEFT JOIN categories c ON c.id = p.category_id
            WHERE p.id IN ($ids) AND p.status = 'Active'
        ");

        $packages = [];
        while ($pkg = $result->fetch_assoc()) {
            $packages[$pkg['id']] = $pkg;
        }

        return $packages;
    }

    // ────────────────────────────────────────────────────────────
    //  MAIN METHOD — Get Recommendations for a Tourist
    //
    //  @param int $touristId  — logged in tourist
    //  @param int $topN       — how many recommendations to return
    //  @return array          — recommended packages with scores
    // ────────────────────────────────────────────────────────────
    public function getRecommendations(int $touristId, int $topN = 5): array {
        // Build binary interaction matrix from bookings + reviews
        $matrix = $this->buildRatingMatrix();

        // Tourist has no history → cold start → return popular packages
        if (!isset($matrix[$touristId]) || empty($matrix[$touristId])) {
            return $this->getPopularPackages($topN);
        }

        // Find top 10 most similar tourists using cosine similarity
        $neighbours = $this->findSimilarTourists($touristId, $matrix, topN: 10);

        // No similar tourists found → fall back to popular packages
        if (empty($neighbours)) {
            return $this->getPopularPackages($topN);
        }

        // Score unseen packages using neighbour interactions
        $scores = $this->predictRatings($touristId, $matrix, $neighbours);

        // Get top N package IDs
        $topPackageIds = array_keys(array_slice($scores, 0, $topN, true));

        // Fetch full package details from DB
        $packageDetails = $this->getPackageDetails($topPackageIds);

        // Build result array preserving ranked order
        $result = [];
        foreach ($topPackageIds as $pkgId) {
            if (isset($packageDetails[$pkgId])) {
                $result[] = [
                    'package'          => $packageDetails[$pkgId],
                    'predicted_rating' => round($scores[$pkgId], 2),
                    'neighbours_used'  => count($neighbours),
                ];
            }
        }

        // Log recommendations to DB
        $this->logRecommendations($touristId, $result);

        return $result;
    }

    // ────────────────────────────────────────────────────────────
    //  FALLBACK — Popular Packages (cold start problem)
    //  Used when tourist has no booking/review history at all.
    // ────────────────────────────────────────────────────────────
    public function getPopularPackages(int $topN = 5): array {
        $result = $this->conn->query("
            SELECT p.*, c.name as category_name,
                   COUNT(DISTINCT b.id) as booking_count,
                   AVG(cm.rating)       as avg_rating
            FROM packages p
            LEFT JOIN categories c  ON c.id  = p.category_id
            LEFT JOIN bookings b    ON b.package_id = p.id
            LEFT JOIN comments cm   ON cm.package_id = p.id AND cm.status = 'Approved'
            WHERE p.status = 'Active'
            GROUP BY p.id
            ORDER BY booking_count DESC, avg_rating DESC
            LIMIT $topN
        ");

        $packages = [];
        while ($pkg = $result->fetch_assoc()) {
            $packages[] = [
                'package'          => $pkg,
                'predicted_rating' => round($pkg['avg_rating'] ?? 3.0, 2),
                'neighbours_used'  => 0,
            ];
        }

        return $packages;
    }

    // ────────────────────────────────────────────────────────────
    //  LOG recommendations to DB
    // ────────────────────────────────────────────────────────────
    private function logRecommendations(int $touristId, array $results): void {
        $stmt = $this->conn->prepare("
            INSERT INTO recommendation_logs
                (tourist_id, package_id, similarity_score, recommended_at)
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                similarity_score = VALUES(similarity_score),
                recommended_at   = NOW()
        ");
        foreach ($results as $item) {
            $pkgId = (int)$item['package']['id'];
            $score = $item['predicted_rating'];
            $stmt->bind_param("iid", $touristId, $pkgId, $score);
            $stmt->execute();
        }
    }
}

// ================================================================
//  GLOBAL FUNCTION — used by my_bookings.php and other pages
// ================================================================
function getRecommendedPackages(int $touristId, int $topN = 5): array {
    $conn = getConnection();
    $cf   = new CollaborativeFilter($conn);
    $recs = $cf->getRecommendations($touristId, $topN);

    // Return just the package arrays for backward compatibility
    return array_map(fn($r) => array_merge($r['package'], [
        'predicted_rating' => $r['predicted_rating'],
    ]), $recs);
}