<?php
namespace Lab;

use Faker\Generator;
use PDO;

final class CustomerSeeder
{
    public function __construct(private PDO $db, private Generator $faker) {}

    public function seed(): void
    {
        $stmt = $this->db->prepare(
            'INSERT IGNORE INTO customers (customer_id, full_name, email, marketing_opt_in, created_at)
             VALUES (?, ?, ?, ?, ?)'
        );
        $now = gmdate('Y-m-d H:i:s');

        for ($i = 1; $i <= 10000; $i++) {
            $id    = sprintf('CUST-%05d', $i);
            $email = sprintf('customer%05d@example.com', $i);

            $name    = $this->faker->name();   // S1: name from the injected Faker object
            $consent = $i <= 8000 ? 1 : 0;     // S2: 1-8000 opted in, 8001-10000 opted out

            $stmt->execute([$id, $name, $email, $consent, $now]);
        }
        echo "Seeding finished.\n";
    }
}
