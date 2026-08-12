# UML Use Case Diagram - E-Learn System

## Описание
Диаграмма вариантов использования для системы онлайн-обучения E-Learn.

## Варианты реализации

### 1. PlantUML (рекомендуется)
Файл: `use-case-diagram.puml`

**Как использовать:**
- Онлайн: http://www.plantuml.com/plantuml/uml/ (вставьте содержимое файла)
- VS Code: Установите расширение "PlantUML"
- IntelliJ IDEA: Встроенная поддержка PlantUML
- Онлайн редактор: https://plantuml-editor.kkeisuke.com/

### 2. Mermaid (альтернатива)
Файл: `use-case-diagram-mermaid.md`

**Как использовать:**
- GitHub/GitLab: Автоматически рендерится в README.md
- VS Code: Расширение "Markdown Preview Mermaid Support"
- Онлайн: https://mermaid.live/

## Основные актеры и их функции

### Admin (Администратор)
- **Správa používateľov** - Управление всеми пользователями (студенты, учителя, админы)
- **Správa kurzov** - Управление всеми курсами
- **Správa lekcií** - Управление всеми уроками
- **Správa testov** - Управление всеми тестами
- **Pohľad na výsledky** - Просмотр всех результатов
- **Modifikovať vlastné údaje** - Изменение своих данных

### Teacher (Учитель)
- **Správa vlastných kurzov** - Управление своими курсами
  - Включает: **Nahrať obrázok kurzu** (Загрузка изображения курса)
- **Správa lekcií** - Управление уроками
  - Включает: **Správa testov** (Управление тестами)
- **Pohľad na študentov** - Просмотр своих студентов
- **Pohľad na výsledky študentov** - Просмотр результатов своих студентов
- **Získať kód učiteľa** - Получение кода учителя для регистрации студентов
- **Modifikovať vlastné údaje** - Изменение своих данных

### Student (Студент)
- **Zaregistrovať sa (s kódom učiteľa)** - Регистрация с кодом учителя
  - Включает: **Prihlásiť sa** (Автоматический вход после регистрации)
- **Prihlásiť sa** - Вход в систему
- **Prezerať kurzy** - Просмотр доступных курсов
- **Prezerať lekcie** - Просмотр уроков
  - Включает: **Napísať testy** (Прохождение тестов)
- **Pohľad na vlastné výsledky** - Просмотр своих результатов
- **Modifikovať vlastné údaje** - Изменение своих данных
- **Odhlásiť sa** - Выход из системы

## Связи (Include)
- **Správa vlastných kurzov** включает **Nahrať obrázok kurzu**
- **Správa lekcií** включает **Správa testov**
- **Prezerať lekcie** включает **Napísať testy**
- **Zaregistrovať sa** включает **Prihlásiť sa**
