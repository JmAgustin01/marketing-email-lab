<?php
namespace Lab;

use PDO;
use RuntimeException;

final class LocalDatabaseTools
{
    public function __construct(private PDO $db) {}

    /** Runs the statements found in schema.sql (statements separated by semicolons). */
    public function initialize(string $schemaFile): int
    {
        $sql = preg_replace('/^\s*--.*$/m', '', file_get_contents($schemaFile));
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        if (!$statements) {
            throw new RuntimeException('schema.sql has no statements. Complete TODO D1 first.');
        }
        foreach ($statements as $statement) {
            $this->db->exec($statement);
        }
        return count($statements);
    }

    public function display(): void
    {
        $this->print('Customers', 'SELECT COUNT(*) AS total_customers FROM customers');
        $this->print('Consent', 'SELECT marketing_opt_in, COUNT(*) AS total FROM customers GROUP BY marketing_opt_in');
        $this->print('Outbox', 'SELECT campaign_id, status, COUNT(*) AS total FROM marketing_outbox GROUP BY campaign_id, status');
        $this->print('History', 'SELECT campaign_id, status, COUNT(*) AS total FROM sent_marketing_emails GROUP BY campaign_id, status');
    }

    private function print(string $title, string $query): void
    {
        echo "== $title ==\n";
        $rows = $this->db->query($query)->fetchAll();
        if (!$rows) {
            echo "(no rows)\n\n";
            return;
        }
        foreach ($rows as $row) {
            echo implode(' | ', array_map(fn($k, $v) => "$k=$v", array_keys($row), $row)) . "\n";
        }
        echo "\n";
    }
}
