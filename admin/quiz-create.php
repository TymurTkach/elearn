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

        $ins = $pdo->prepare('
            INSERT INTO quizzes (lesson_id, question, options, correct_options, option_a, option_b, option_c, option_d, correct_option)
            VALUES (:lesson_id, :question, :options, :correct_options, :option_a, :option_b, :option_c, :option_d, :correct_option)
        ');
        $ins->execute([
            'lesson_id' => $lessonId,
            'question' => $question,
            'options' => $optionsJson,
            'correct_options' => $correctOptionsJson,
            'option_a' => $option_a,
            'option_b' => $option_b,
            'option_c' => $option_c,
            'option_d' => $option_d,
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
    <link rel="stylesheet" href="../styles.css?v=2">
    <script src="../theme.js" defer></script>
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
</head>
<body class="admin-body">
<div class="admin-wrap">
    <div class="admin-card">
        <div class="muted"><a href="quizzes.php?lesson_id=<?= (int)$lessonId ?>">← Späť na otázky</a></div>
        <h1>Pridať otázku</h1>
        <p class="muted">Lekcia: <strong><?= h($lesson['title']) ?></strong> (<?= h($lesson['course_title']) ?>)</p>

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
                    <div class="option-row" data-index="0">
                        <input type="text" name="options[]" placeholder="Možnosť 1" required>
                        <button type="button" class="btn-remove-option" onclick="removeOption(this)" style="display: none;">Odstrániť</button>
                    </div>
                    <div class="option-row" data-index="1">
                        <input type="text" name="options[]" placeholder="Možnosť 2" required>
                        <button type="button" class="btn-remove-option" onclick="removeOption(this)">Odstrániť</button>
                    </div>
                </div>
                <button type="button" class="btn btn-secondary" onclick="addOption()" style="margin-top: 10px;">+ Pridať možnosť</button>
            </div>

            <div style="margin: 20px 0;">
                <label>Správne odpovede *</label>
                <small class="muted inline-text-sm">Označte všetky správne odpovede (môže byť viacero).</small>
                <div id="correct-options-container">
                    <!-- Будет заполнено через JavaScript -->
                </div>
            </div>

            <button class="btn" type="submit">Pridať otázku</button>
            <a href="quizzes.php?lesson_id=<?= (int)$lessonId ?>" class="btn btn-secondary">Zrušiť</a>
        </form>
    </div>
</div>

<script>
    let optionCount = 2;

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
            const label = document.createElement('label');
            label.style.display = 'flex';
            label.style.alignItems = 'center';
            label.style.marginBottom = '8px';
            label.style.cursor = 'pointer';
            const letter = String.fromCharCode(65 + index);
            const displayText = input.value.trim() || `Možnosť ${index + 1}`;
            label.innerHTML = `
            <input type="checkbox" name="correct_options[]" value="${index}" style="width: auto; margin-right: 8px; accent-color: var(--accent);">
            <span><strong>${letter}:</strong> ${displayText}</span>
        `;
            container.appendChild(label);
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

        const optionsInputs = document.querySelectorAll('#options-container input[type="text"]');
        optionsInputs.forEach(input => {
            input.addEventListener('input', function() {
                updateCorrectOptions();
            });
        });
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

    #correct-options-container {
        margin-top: 12px;
        padding: 12px;
        background: var(--bg-surface-alt);
        border-radius: 8px;
        border: 1px solid var(--border-subtle);
    }

    #correct-options-container label {
        cursor: pointer;
        padding: 8px;
        border-radius: 6px;
        transition: background 0.2s;
    }

    #correct-options-container label:hover {
        background: rgba(56, 189, 248, 0.1);
    }
</style>
</body>
</html>
