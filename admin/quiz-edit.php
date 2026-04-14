<?php
$pdo = require dirname(__DIR__) . '/config.php';
require dirname(__DIR__) . '/auth.php';
require_login();

function normalize_correct_option_indexes(array $rawValues, int $optionCount): array {
    $map = ['a' => 0, 'b' => 1, 'c' => 2, 'd' => 3];
    $normalized = [];
    foreach ($rawValues as $raw) {
        $idx = null;
        if (is_int($raw) || (is_string($raw) && ctype_digit($raw))) {
            $idx = (int)$raw;
        } elseif (is_string($raw)) {
            $key = strtolower(trim($raw));
            if (isset($map[$key])) {
                $idx = $map[$key];
            }
        }
        if ($idx !== null && $idx >= 0 && $idx < $optionCount) {
            $normalized[$idx] = $idx;
        }
    }
    ksort($normalized);
    return array_values($normalized);
}

// Učitelia a admini môžu vstúpiť do administrácie
if (!is_admin() && !is_teacher()) {
    header('Location: ../login.php');
    exit;
}

$quizId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($quizId <= 0) {
    header('Location: courses.php');
    exit;
}

// Načítame otázku
$stmt = $pdo->prepare('
    SELECT q.*, l.title AS lesson_title, c.title AS course_title, c.teacher_id
    FROM quizzes q
    JOIN lessons l ON q.lesson_id = l.id
    JOIN courses c ON l.course_id = c.id
    WHERE q.id = :id
');
$stmt->execute(['id' => $quizId]);
$quiz = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$quiz) {
    header('Location: courses.php');
    exit;
}

// Kontrolujeme práva prístupu: učiteľ môže upravovať len otázky vo svojich lekciách
if (is_teacher()) {
    $teacherId = current_user_teacher_id();
    if (!$teacherId) {
        $teacherId = get_user_teacher_id($pdo, current_user_id());
        if ($teacherId) {
            $_SESSION['teacher_id'] = $teacherId;
        }
    }
    if ($quiz['teacher_id'] != $teacherId) {
        header('Location: courses.php');
        exit;
    }
}

$options = [];
$correctOptions = [];

if (!empty($quiz['options'])) {
    $decoded = json_decode($quiz['options'], true);
    if (is_array($decoded)) {
        $options = array_filter($decoded, function($v) { return $v !== '' && $v !== null; });
    }
}

if (!empty($quiz['correct_options'])) {
    $decoded = json_decode($quiz['correct_options'], true);
    if (is_array($decoded)) {
        $correctOptions = $decoded;
    }
}

if (empty($options)) {
    if ($quiz['option_a']) $options[] = $quiz['option_a'];
    if ($quiz['option_b']) $options[] = $quiz['option_b'];
    if ($quiz['option_c']) $options[] = $quiz['option_c'];
    if ($quiz['option_d']) $options[] = $quiz['option_d'];
    $options = array_filter($options);
}

if (empty($correctOptions) && $quiz['correct_option']) {
    $map = ['a' => 0, 'b' => 1, 'c' => 2, 'd' => 3];
    if (isset($map[$quiz['correct_option']])) {
        $correctOptions = [$map[$quiz['correct_option']]];
    }
}

$errors = [];
$question = $quiz['question'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $question = trim((string)($_POST['question'] ?? ''));

    $options = [];
    $correctOptions = [];

    if (isset($_POST['options']) && is_array($_POST['options'])) {
        foreach ($_POST['options'] as $opt) {
            $opt = trim((string)$opt);
            if ($opt !== '') {
                $options[] = $opt;
            }
        }
    }

    if (isset($_POST['correct_options']) && is_array($_POST['correct_options'])) {
        foreach ($_POST['correct_options'] as $idx) {
            $idx = (int)$idx;
            if ($idx >= 0 && $idx < count($options)) {
                $correctOptions[] = $idx;
            }
        }
    }

    if ($question === '') {
        $errors[] = 'Otázka nemôže byť prázdna.';
    }

    if (count($options) < 2) {
        $errors[] = 'Musia byť aspoň dve možnosti odpovede.';
    }

    if (count($correctOptions) === 0) {
        $errors[] = 'Musí byť zvolená aspoň jedna správna odpoveď.';
    }

    if (!$errors) {
        $optionsJson = json_encode($options, JSON_UNESCAPED_UNICODE);
        $correctOptionsJson = json_encode($correctOptions, JSON_UNESCAPED_UNICODE);

        $option_a = $options[0] ?? null;
        $option_b = $options[1] ?? null;
        $option_c = $options[2] ?? null;
        $option_d = $options[3] ?? null;
        $correct_option = count($correctOptions) === 1 ? ['a', 'b', 'c', 'd'][$correctOptions[0]] ?? null : null;

        $upd = $pdo->prepare('
            UPDATE quizzes
            SET question = :question, options = :options, correct_options = :correct_options,
                option_a = :option_a, option_b = :option_b, option_c = :option_c, option_d = :option_d, correct_option = :correct_option
            WHERE id = :id
        ');
        $upd->execute([
            'question' => $question,
            'options' => $optionsJson,
            'correct_options' => $correctOptionsJson,
            'option_a' => $option_a,
            'option_b' => $option_b,
            'option_c' => $option_c,
            'option_d' => $option_d,
            'correct_option' => $correct_option,
            'id' => $quizId,
        ]);
        header('Location: quizzes.php?lesson_id=' . (int)$quiz['lesson_id']);
        exit;
    }
}

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
?>
<!doctype html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <title>Upraviť otázku – Administrácia</title>
    <link rel="stylesheet" href="../styles.css">
    <script src="../theme.js" defer></script>
</head>
<body class="admin-body">
<div class="admin-wrap">
    <div class="admin-card">
        <div class="muted"><a href="quizzes.php?lesson_id=<?= (int)$quiz['lesson_id'] ?>">← Späť na otázky</a></div>
        <h1>Upraviť otázku</h1>
        <p class="muted">Lekcia: <strong><?= h($quiz['lesson_title']) ?></strong> (<?= h($quiz['course_title']) ?>)</p>

        <?php if ($errors): ?>
            <div class="err">
                <?php foreach ($errors as $e): ?><div>• <?= h($e) ?></div><?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="post" autocomplete="off" id="quiz-form">
            <label for="question">Otázka *</label>
            <textarea id="question" name="question" required><?= h($question) ?></textarea>

            <div style="margin: 20px 0;">
                <label>Možnosti odpovede *</label>
                <small class="muted inline-text-sm">Pridajte aspoň 2 možnosti. Označte správne odpovede nižšie.</small>
                <div id="options-container">
                    <?php foreach ($options as $index => $opt): ?>
                        <div class="option-row" data-index="<?= $index ?>">
                            <input type="text" name="options[]" value="<?= h($opt) ?>" placeholder="Možnosť <?= $index + 1 ?>" <?= $index < 2 ? 'required' : '' ?>>
                            <button type="button" class="btn-remove-option" onclick="removeOption(this)" <?= count($options) <= 2 ? 'style="display: none;"' : '' ?>>Odstrániť</button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="btn btn-secondary" onclick="addOption()" style="margin-top: 10px;">+ Pridať možnosť</button>
            </div>

            <div style="margin: 20px 0;">
                <label>Správne odpovede *</label>
                <small class="muted inline-text-sm">Označte všetky správne odpovede (môže byť viacero).</small>
                <div id="correct-options-container">
                </div>
            </div>

            <button class="btn" type="submit">Uložiť zmeny</button>
            <a href="quizzes.php?lesson_id=<?= (int)$quiz['lesson_id'] ?>" class="btn btn-secondary">Zrušiť</a>
        </form>
    </div>
</div>

<script>
    let optionCount = <?= count($options) ?>;
    const initialCorrectOptions = <?= json_encode($correctOptions, JSON_UNESCAPED_UNICODE) ?>;

    function addOption() {
        const container = document.getElementById('options-container');
        const newRow = document.createElement('div');
        newRow.className = 'option-row';
        newRow.setAttribute('data-index', optionCount);
        newRow.innerHTML = `
        <input type="text" name="options[]" placeholder="Možnosť ${optionCount + 1}">
        <button type="button" class="btn-remove-option" onclick="removeOption(this)">Odstrániť</button>
    `;
        container.appendChild(newRow);
        optionCount++;
        updateCorrectOptions();
        updateRemoveButtons();
    }

    function removeOption(btn) {
        const row = btn.closest('.option-row');
        const container = document.getElementById('options-container');
        if (container.children.length > 2) {
            row.remove();
            updateCorrectOptions();
            updateRemoveButtons();
        }
    }

    function updateRemoveButtons() {
        const rows = document.querySelectorAll('.option-row');
        rows.forEach((row, index) => {
            const btn = row.querySelector('.btn-remove-option');
            if (rows.length > 2) {
                btn.style.display = 'inline-block';
            } else {
                btn.style.display = 'none';
            }
        });
    }

    function updateCorrectOptions() {
        const container = document.getElementById('correct-options-container');
        const options = document.querySelectorAll('#options-container input[type="text"]');
        container.innerHTML = '';

        options.forEach((input, index) => {
            if (input.value.trim() !== '') {
                const label = document.createElement('label');
                label.style.display = 'flex';
                label.style.alignItems = 'center';
                label.style.marginBottom = '8px';
                // Проверяем, был ли этот вариант правильным при загрузке страницы
                const wasChecked = initialCorrectOptions && initialCorrectOptions.includes(index);
                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.name = 'correct_options[]';
                checkbox.value = index;
                checkbox.checked = wasChecked;
                checkbox.style.width = 'auto';
                checkbox.style.marginRight = '8px';
                const span = document.createElement('span');
                span.textContent = String.fromCharCode(65 + index) + ': ' + (input.value || `Možnosť ${index + 1}`);
                label.appendChild(checkbox);
                label.appendChild(span);
                container.appendChild(label);
            }
        });
    }

    document.getElementById('options-container').addEventListener('input', function(e) {
        if (e.target.tagName === 'INPUT') {
            updateCorrectOptions();
        }
    });

    document.addEventListener('DOMContentLoaded', function() {
        updateCorrectOptions();
        updateRemoveButtons();
    });
</script>

<style>
    .option-row {
        display: flex;
        gap: 10px;
        margin-bottom: 10px;
        align-items: center;
    }

    .option-row input {
        flex: 1;
    }

    .btn-remove-option {
        padding: 8px 12px;
        background: rgba(220, 38, 38, 0.1);
        border: 1px solid #ef4444;
        color: #ef4444;
        border-radius: 8px;
        cursor: pointer;
        font-size: 14px;
    }

    .btn-remove-option:hover {
        background: rgba(220, 38, 38, 0.2);
    }

    #correct-options-container label {
        cursor: pointer;
    }
</style>
</body>
</html>
