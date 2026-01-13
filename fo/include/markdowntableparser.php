<?php
/**
 * Класс для парсинга табличных данных из Markdown файла
 * Thank You, mashaGPT!
 * created 2026-01-13
 */
class MarkdownTableParser
{
    /**
     * Парсит Markdown файл и извлекает табличные данные
     *
     * @param string $filePath Путь к Markdown файлу
     * @param bool $hasHeaders Есть ли заголовки в таблице (первая строка)
     * @return array Ассоциативный массив с данными таблицы
     * @throws Exception Если файл не найден или не удалось прочитать
     */
    public static function parseFile(string $filePath, bool $hasHeaders = true): array
    {
        if (!file_exists($filePath)) {
            throw new Exception("Файл не найден: {$filePath}");
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new Exception("Не удалось прочитать файл: {$filePath}");
        }

        return self::parseContent($content, $hasHeaders);
    }

    /**
     * Парсит Markdown контент и извлекает табличные данные
     *
     * @param string $content Содержимое Markdown
     * @param bool $hasHeaders Есть ли заголовки в таблице
     * @return array Ассоциативный массив с данными таблицы
     */
    public static function parseContent(string $content, bool $hasHeaders = true): array
    {
        // Находим все таблицы в Markdown
        $tables = self::extractTables($content);

        if (empty($tables)) {
            return [];
        }

        $result = [];

        foreach ($tables as $tableIndex => $tableContent) {
            $result[$tableIndex] = self::parseSingleTable($tableContent, $hasHeaders);
        }

        // Если только одна таблица, возвращаем её данные напрямую
        if (count($result) === 1) {
            return reset($result);
        }

        return $result;
    }

    /**
     * Извлекает таблицы из Markdown контента
     *
     * @param string $content Markdown контент
     * @return array Массив с содержимым таблиц
     */
    private static function extractTables(string $content): array
    {
        $tables = [];
        $lines = explode("\n", $content);
        $inTable = false;
        $currentTable = [];

        foreach ($lines as $line) {
            $trimmedLine = trim($line);

            // Проверяем, является ли строка частью таблицы
            if (self::isTableRow($trimmedLine)) {
                if (!$inTable) {
                    $inTable = true;
                }
                $currentTable[] = $trimmedLine;
            } else {
                if ($inTable && !empty($currentTable)) {
                    $tables[] = implode("\n", $currentTable);
                    $currentTable = [];
                    $inTable = false;
                }
            }
        }

        // Добавляем последнюю таблицу, если она есть
        if ($inTable && !empty($currentTable)) {
            $tables[] = implode("\n", $currentTable);
        }

        return $tables;
    }

    /**
     * Проверяет, является ли строка частью таблицы
     *
     * @param string $line Строка для проверки
     * @return bool
     */
    private static function isTableRow(string $line): bool
    {
        // Строка таблицы начинается и заканчивается на | или содержит разделитель
        return preg_match('/^\|.*\|$/', $line) ||
               preg_match('/^[\+\-]{3,}/', $line) ||
               preg_match('/^\s*[\|\+]?[\-\s]+\|/', $line);
    }

    /**
     * Парсит одну таблицу Markdown
     *
     * @param string $tableContent Содержимое таблицы
     * @param bool $hasHeaders Есть ли заголовки
     * @return array Данные таблицы
     */
    private static function parseSingleTable(string $tableContent, bool $hasHeaders = true): array
    {
        $lines = explode("\n", trim($tableContent));
        $data = [];
        $headers = [];
        $foundSeparator = false;
        $isFirstRow = true;

        foreach ($lines as $line) {
            $trimmedLine = trim($line);

            // Пропускаем пустые строки
            if (empty($trimmedLine)) {
                continue;
            }

            // Проверяем, является ли строка разделителем
            if (self::isSeparatorLine($trimmedLine)) {
                $foundSeparator = true;
                continue; // Пропускаем разделитель - он не должен попадать в данные
            }

            // Извлекаем ячейки из строки
            $cells = self::extractCells($trimmedLine);

            if (empty($cells)) {
                continue;
            }

            if ($hasHeaders) {
                if (!$foundSeparator && $isFirstRow) {
                    // Первая строка до разделителя - заголовки
                    $headers = $cells;
                    $isFirstRow = false;
                } elseif ($foundSeparator) {
                    // Строки после разделителя - данные
                    if (empty($headers)) {
                        // Если заголовков нет, используем числовые индексы
                        $row = array_combine(range(0, count($cells) - 1), $cells);
                    } else {
                        // Создаем ассоциативный массив
                        $row = self::createAssocRow($cells, $headers);
                    }
                    $data[] = $row;
                }
            } else {
                // Все строки - данные (без заголовков)
                $row = array_combine(range(0, count($cells) - 1), $cells);
                $data[] = $row;
            }
        }

        // Если заголовки есть, но разделителя не нашли, то первая строка - заголовки,
        // а остальные - данные
        if ($hasHeaders && !$foundSeparator && !empty($headers)) {
            // Пропускаем первую строку (заголовки) и обрабатываем остальные как данные
            $dataLines = array_slice($lines, 1);
            foreach ($dataLines as $dataLine) {
                $trimmedDataLine = trim($dataLine);
                if (empty($trimmedDataLine) || self::isSeparatorLine($trimmedDataLine)) {
                    continue;
                }
                $cells = self::extractCells($trimmedDataLine);
                if (!empty($cells)) {
                    $row = self::createAssocRow($cells, $headers);
                    $data[] = $row;
                }
            }
        }

        return $data;
    }

    /**
     * Проверяет, является ли строка разделителем таблицы
     *
     * @param string $line Строка для проверки
     * @return bool
     */
    private static function isSeparatorLine(string $line): bool
    {
        if(in_array(substr($line,0,2), ['|-', '+-'])) return true;
        // Проверяем различные форматы разделителей
        $patterns = [
            // Стандартный формат: |---|----|---|
            '/^[\|\s]*[\+\-]{3,}[\|\s]*$/',

            // Альтернативный формат: ---|----|---
            '/^[\|\s]*\-{3,}[\|\s]*$/',

            // Формат без вертикальных линий: ---
            '/^[\+\-]{3,}$/',

            // Формат с плюсами: +---+---+---+
            '/^[\+\-]+\+[\+\-]+$/',

            // Формат с вертикальными линиями и дефисами
            '/^[\|\s]*[\-\:]{3,}[\|\s]*$/',

            // Формат с двоеточиями для выравнивания: |:---|:---:|---:|
            '/^[\|\s]*[\:\-\+]{3,}[\|\s]*$/'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $line)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Извлекает ячейки из строки таблицы
     *
     * @param string $line Строка таблицы
     * @return array Массив ячеек
     */
    private static function extractCells(string $line): array
    {
        // Если строка является разделителем, возвращаем пустой массив
        if (self::isSeparatorLine($line)) {
            return [];
        }

        // Удаляем начальные и конечные символы |
        $line = trim($line, '|');

        // Разделяем по |, учитывая экранирование
        $cells = preg_split('/\s*\|\s*/', $line);

        if ($cells === false) {
            return [];
        }

        // Очищаем ячейки от лишних пробелов
        $cells = array_map('trim', $cells);

        // Удаляем пустые ячейки (если они были в начале или конце)
        $cells = array_filter($cells, function($cell) {
            return $cell !== '';
        });

        return array_values($cells);
    }

    /**
     * Создает ассоциативный массив строки
     *
     * @param array $cells Значения ячеек
     * @param array $headers Заголовки столбцов
     * @return array Ассоциативный массив
     */
    private static function createAssocRow(array $cells, array $headers): array
    {
        $row = [];

        // Создаем ассоциативный массив
        foreach ($headers as $index => $header) {
            $key = self::normalizeKey($header);
            $row[$key] = $cells[$index] ?? '';
        }

        return $row;
    }

    /**
     * Нормализует ключ для ассоциативного массива
     *
     * @param string $key Исходный ключ
     * @return string Нормализованный ключ
     */
    private static function normalizeKey(string $key): string
    {
        // Удаляем спецсимволы, оставляем только буквы, цифры и подчеркивания
        $key = preg_replace('/[^\p{L}\p{N}_]/u', '_', $key);

        // Удаляем множественные подчеркивания
        $key = preg_replace('/_+/', '_', $key);

        // Удаляем подчеркивания в начале и конце
        $key = trim($key, '_');

        // Приводим к нижнему регистру
        $key = strtolower($key);

        // Если ключ пустой, создаем числовой
        if (empty($key)) {
            $key = 'col_' . uniqid();
        }

        return $key;
    }

    /**
     * Получает информацию о таблицах в файле
     *
     * @param string $filePath Путь к файлу
     * @return array Информация о таблицах
     */
    public static function getTableInfo(string $filePath): array
    {
        $content = file_get_contents($filePath);
        $tables = self::extractTables($content);

        $info = [
            'total_tables' => count($tables),
            'tables' => []
        ];

        foreach ($tables as $index => $tableContent) {
            $lines = explode("\n", trim($tableContent));
            $firstLine = $lines[0] ?? '';
            $cells = self::extractCells($firstLine);

            $info['tables'][] = [
                'index' => $index,
                'rows' => count($lines),
                'columns' => count($cells),
                'first_row_preview' => $cells,
                'content_preview' => substr($tableContent, 0, 100) . (strlen($tableContent) > 100 ? '...' : '')
            ];
        }

        return $info;
    }
}
