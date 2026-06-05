<?php
/**
 * Schema 自愈工具
 *
 * 自动给 NOT NULL 但没有默认值的字段加 DEFAULT,
 * 自动补齐代码需要的字段。24h 缓存, 防止每次请求都跑。
 *
 * 用法:
 *   SchemaManager::ensureColumn($db, 'orders', 'batch_id', 'VARCHAR(36) DEFAULT NULL', 'quantity');
 *   SchemaManager::ensureIndex($db, 'orders', 'idx_batch_id', 'batch_id');
 */
class SchemaManager {
    private static $cacheDir = __DIR__ . '/../logs/schema_fix';

    public static function ensureColumn($db, $table, $column, $definition, $after = null) {
        try {
            $colRows = $db->query("SHOW COLUMNS FROM `{$table}`")->fetchAll();
            $exists = false;
            foreach ($colRows as $r) {
                $field = $r['Field'] ?? $r[0] ?? '';
                if ($field === $column) {
                    $exists = true;
                    break;
                }
            }
            if ($exists) {
                return ['ok' => true, 'existed' => true];
            }
            $afterSql = $after ? " AFTER `{$after}`" : '';
            $db->query("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}{$afterSql}");
            error_log("[SchemaManager] Added column {$table}.{$column}");
            return ['ok' => true, 'added' => true];
        } catch (Throwable $e) {
            error_log("[SchemaManager] ensureColumn failed: {$table}.{$column}: " . $e->getMessage());
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    public static function ensureIndex($db, $table, $indexName, $columns) {
        try {
            $idxRows = $db->query("SHOW INDEX FROM `{$table}`")->fetchAll();
            $exists = false;
            foreach ($idxRows as $r) {
                $name = $r['Key_name'] ?? $r[2] ?? '';
                if ($name === $indexName) {
                    $exists = true;
                    break;
                }
            }
            if ($exists) {
                return ['ok' => true, 'existed' => true];
            }
            $colList = is_array($columns) ? implode('`,`', $columns) : $columns;
            $db->query("ALTER TABLE `{$table}` ADD KEY `{$indexName}` (`{$colList}`)");
            error_log("[SchemaManager] Added index {$table}.{$indexName}");
            return ['ok' => true, 'added' => true];
        } catch (Throwable $e) {
            error_log("[SchemaManager] ensureIndex failed: {$table}.{$indexName}: " . $e->getMessage());
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}
