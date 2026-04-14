<?php
/**
 * Gamifikácia: XP, úroveň, streak, odznaky.
 * Vyžaduje migration_gamification.sql.
 */

if (!function_exists('gamification_streak_and_activity')) {
    /**
     * Aktualizuje streak podľa dátumu poslednej aktivity.
     */
    function gamification_streak_and_activity(PDO $pdo, int $userId): void {
        $today = date('Y-m-d');
        $stmt = $pdo->prepare('
            SELECT streak_current, streak_best, last_activity_date
            FROM users WHERE id = :id LIMIT 1
        ');
        $stmt->execute(['id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return;
        }

        $last = $row['last_activity_date'];
        $current = (int)($row['streak_current'] ?? 0);
        $best = (int)($row['streak_best'] ?? 0);

        if ($last === $today) {
            return;
        }

        if ($last === null || $last === '') {
            $newStreak = 1;
        } else {
            $yesterday = date('Y-m-d', strtotime('-1 day'));
            if ($last === $yesterday) {
                $newStreak = $current + 1;
            } else {
                $newStreak = 1;
            }
        }

        $newBest = max($best, $newStreak);

        $upd = $pdo->prepare('
            UPDATE users
            SET streak_current = :sc,
                streak_best = :sb,
                last_activity_date = :lad
            WHERE id = :id
        ');
        $upd->execute([
            'sc' => $newStreak,
            'sb' => $newBest,
            'lad' => $today,
            'id' => $userId,
        ]);

        if ($newStreak >= 7) {
            gamification_award_badge($pdo, $userId, 'STREAK_7');
        }
    }

    function gamification_add_xp(PDO $pdo, int $userId, int $xp): void {
        if ($xp <= 0) {
            return;
        }
        $upd = $pdo->prepare('
            UPDATE users
            SET xp_total = xp_total + :xp,
                level = LEAST(99, FLOOR((xp_total + :xp2) / 200) + 1)
            WHERE id = :id
        ');
        $upd->execute(['xp' => $xp, 'xp2' => $xp, 'id' => $userId]);
    }

    function gamification_award_badge(PDO $pdo, int $userId, string $badgeCode): void {
        try {
            $ins = $pdo->prepare('
                INSERT IGNORE INTO user_badges (user_id, badge_code, awarded_at)
                VALUES (:uid, :code, NOW())
            ');
            $ins->execute(['uid' => $userId, 'code' => $badgeCode]);
        } catch (PDOException $e) {
            // tabuľka alebo stĺpce neexistujú
        }
    }

    function gamification_after_quiz_result(PDO $pdo, int $userId, int $correct, int $total): void {
        if ($total <= 0) {
            return;
        }

        try {
            gamification_streak_and_activity($pdo, $userId);
        } catch (PDOException $e) {
            return;
        }

        $pct = (int)round($correct / $total * 100);
        if ($correct === $total) {
            $xp = 50;
        } elseif ($pct >= 60) {
            $xp = 30;
        } else {
            $xp = 10;
        }

        try {
            gamification_add_xp($pdo, $userId, $xp);
        } catch (PDOException $e) {
            return;
        }

        if ($correct === $total) {
            try {
                $cnt = $pdo->prepare('
                    SELECT COUNT(*) FROM results
                    WHERE user_id = :uid AND total > 0 AND correct = total
                ');
                $cnt->execute(['uid' => $userId]);
                $n = (int)$cnt->fetchColumn();
                if ($n === 1) {
                    gamification_award_badge($pdo, $userId, 'FIRST_TEST_100');
                }
            } catch (PDOException $e) {
                // ignore
            }
        }
    }

    function gamification_after_code_task_pass(PDO $pdo, int $userId): void {
        try {
            gamification_streak_and_activity($pdo, $userId);
        } catch (PDOException $e) {
            return;
        }

        try {
            gamification_add_xp($pdo, $userId, 40);
        } catch (PDOException $e) {
            return;
        }

        try {
            $cnt = $pdo->prepare('
                SELECT COUNT(*) FROM code_task_results
                WHERE user_id = :uid AND passed = 1
            ');
            $cnt->execute(['uid' => $userId]);
            if ((int)$cnt->fetchColumn() === 1) {
                gamification_award_badge($pdo, $userId, 'FIRST_CODE_PASS');
            }
        } catch (PDOException $e) {
            // ignore
        }
    }
    
    function gamification_badge_label(string $code): string {
        $map = [
            'FIRST_TEST_100' => 'Prvý test na 100 %',
            'FIRST_CODE_PASS' => 'Prvá kódová úloha',
            'STREAK_7' => '7 dní v rade',
        ];
        return $map[$code] ?? $code;
    }
}
