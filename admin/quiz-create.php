<?php
$pdo = require dirname(__DIR__) . '/config.php';
require dirname(__DIR__) . '/auth.php';
require_login();

// Učitelia a admini môžu vstúpiť do administrácie
if (!is_admin() && !is_teacher()) {
    header('Location: ../login.php');
    exit;
}

$lessonId = isset($_GET['lesson_id']) ? (int)$_GET['lesson_id'] : 0;
if ($lessonId <= 0) {
    header('Location: courses.php');
    exit;
}

// Kontrolujeme existenciu lekcie
$stmt = $pdo->prepare('
    SELECT l.*, c.title AS course_title, c.teacher_id
    FROM lessons l
    JOIN courses c ON l.course_id = c.id
    WHERE l.id = :id
');
$stmt->execute(['id' => $lessonId]);
$lesson = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$lesson) {
    header('Location: courses.php');
    exit;
}

// Kontrolujeme práva prístupu: učiteľ môže vytvárať otázky len vo svojich lekciách
if (is_teacher()) {
    $teacherId = current_user_teacher_id();
    if (!$teacherId) {
        $teacherId = get_user_teacher_id($pdo, current_user_id());
        if ($teacherId) {
            $_SESSION['teacher_id'] = $teacherId;
        }
    }
    if ($lesson['teacher_id'] != $teacherId) {
        header('Location: courses.php');
        exit;
    }
}

$errors = [];
$question = '';
$option_a = '';
$option_b = '';
$option_c = '';
$option_d = '';
$correct_option = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $question = trim((string)($_POST['question'] ?? ''));
    $option_a = trim((string)($_POST['option_a'] ?? ''));
    $option_b = trim((string)($_POST['option_b'] ?? ''));
    $option_c = trim((string)($_POST['option_c'] ?? ''));
    $option_d = trim((string)($_POST['option_d'] ?? ''));
    $correct_option = strtolower(trim((string)($_POST['correct_option'] ?? '')));

    if ($question === '') {
        $errors[] = 'Otázka nemôže byť prázdna.';
    }

    if ($option_a === '' || $option_b === '') {
        $errors[] = 'Musia byť aspoň dve možnosti (A a B).';
    }

    if ($correct_option === '' || !in_array($correct_option, ['a', 'b', 'c', 'd'])) {
        $errors[] = 'Musí byť zvolená správna odpoveď (a, b, c alebo d).';
    }

    // Kontrolujeme, že zvolená správna možnosť nie je prázdna
    $option_field = 'option_' . $correct_option;
    if ($$option_field === '') {
        $errors[] = 'Správna odpoveď nemôže byť prázdna.';
    }

    if (!$errors) {
        $ins = $pdo->prepare('
            INSERT INTO quizzes (lesson_id, question, option_a, option_b, option_c, option_d, correct_option)
            VALUES (:lesson_id, :question, :option_a, :option_b, :option_c, :option_d, :correct_option)
        ');
        $ins->execute([
            'lesson_id' => $lessonId,
            'question' => $question,
            'option_a' => $option_a ?: null,
            'option_b' => $option_b ?: null,
            'option_c' => $option_c ?: null,
            'option_d' => $option_d ?: null,
            'correct_option' => $correct_option,
        ]);
        header('Location: quizzes.php?lesson_id=' . $lessonId);
        exit;
    }
}

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
?>
<!doctype html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <title>Pridať otázku – Administrácia</title>
    <style>
        body{margin:0;font-family:system-ui,-apple-system,"Segoe UI",sans-serif;background:#0f172a;color:#e5e7eb}
        .wrap{max-width:1000px;margin:40px auto;padding:0 16px}
        .card{background:#020617;border:1px solid #1e293b;border-radius:16px;padding:20px;margin-bottom:14px}
        .row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
        label{display:block;margin:10px 0 6px;color:#cbd5e1;font-size:14px}
        input,textarea,select{width:100%;box-sizing:border-box;padding:10px 12px;border-radius:10px;border:1px solid #1e293b;background:#0b1220;color:#e5e7eb}
        textarea{min-height:80px;resize:vertical;font-family:inherit}
        .btn{margin-top:12px;background:#38bdf8;color:#020617;border:none;padding:10px 18px;border-radius:999px;font-weight:800;cursor:pointer}
        .btn:hover{background:#0ea5e9}
        .btn-secondary{margin-left:8px;background:#475569;color:#e5e7eb}
        .btn-secondary:hover{background:#64748b}
        a{color:#e5e7eb;text-decoration:none}
        a:hover{text-decoration:underline}
        .muted{color:#9ca3af}
        .err{margin:10px 0;padding:10px 12px;border-radius:12px;background:rgba(220,38,38,.15);border:1px solid #ef4444;font-size:14px}
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <div class="muted"><a href="quizzes.php?lesson_id=<?= (int)$lessonId ?>">← Späť na otázky</a></div>
        <h1>Pridať otázku</h1>
        <p class="muted">Lekcia: <strong><?= h($lesson['title']) ?></strong> (<?= h($lesson['course_title']) ?>)</p>

        <?php if ($errors): ?>
            <div class="err">
                <?php foreach ($errors as $e): ?><div>• <?= h($e) ?></div><?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="post" autocomplete="off">
            <label for="question">Otázka</label>
            <textarea id="question" name="question" required><?= h($question) ?></textarea>

            <div class="row">
                <div>
                    <label for="option_a">Možnosť A *</label>
                    <input id="option_a" name="option_a" value="<?= h($option_a) ?>" required>
                </div>
                <div>
                    <label for="option_b">Možnosť B *</label>
                    <input id="option_b" name="option_b" value="<?= h($option_b) ?>" required>
                </div>
            </div>

            <div class="row">
                <div>
                    <label for="option_c">Možnosť C (voliteľné)</label>
                    <input id="option_c" name="option_c" value="<?= h($option_c) ?>">
                </div>
                <div>
                    <label for="option_d">Možnosť D (voliteľné)</label>
                    <input id="option_d" name="option_d" value="<?= h($option_d) ?>">
                </div>
            </div>

            <label for="correct_option">Správna odpoveď *</label>
            <select id="correct_option" name="correct_option" required>
                <option value="">-- Vyberte --</option>
                <option value="a" <?= $correct_option === 'a' ? 'selected' : '' ?>>A</option>
                <option value="b" <?= $correct_option === 'b' ? 'selected' : '' ?>>B</option>
                <option value="c" <?= $correct_option === 'c' ? 'selected' : '' ?>>C</option>
                <option value="d" <?= $correct_option === 'd' ? 'selected' : '' ?>>D</option>
            </select>

            <button class="btn" type="submit">Pridať otázku</button>
            <a href="quizzes.php?lesson_id=<?= (int)$lessonId ?>" class="btn btn-secondary">Zrušiť</a>
        </form>
    </div>
</div>
</body>
</html>

