<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Category;
use PDO;
use RuntimeException;

final class CategoryRepository
{
    private const SELECT_COLUMNS = 'id, name, slug, created_at, updated_at';

    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return list<Category>
     */
    public function findAll(): array
    {
        $statement = $this->pdo->query(
            'SELECT ' . self::SELECT_COLUMNS . ' FROM categories ORDER BY name ASC',
        );

        if ($statement === false) {
            return [];
        }

        $rows = $statement->fetchAll();

        return array_map(static fn (array $row): Category => Category::fromRow($row), $rows);
    }

    public function findById(int $id): ?Category
    {
        $statement = $this->pdo->prepare(
            'SELECT ' . self::SELECT_COLUMNS . ' FROM categories WHERE id = :id LIMIT 1',
        );
        $statement->execute(['id' => $id]);

        $row = $statement->fetch();

        if ($row === false) {
            return null;
        }

        return Category::fromRow($row);
    }

    public function findByName(string $name): ?Category
    {
        $statement = $this->pdo->prepare(
            'SELECT ' . self::SELECT_COLUMNS . ' FROM categories WHERE name = :name LIMIT 1',
        );
        $statement->execute(['name' => $name]);

        $row = $statement->fetch();

        if ($row === false) {
            return null;
        }

        return Category::fromRow($row);
    }

    public function create(string $name, string $slug): Category
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO categories (name, slug) VALUES (:name, :slug)',
        );
        $statement->execute(['name' => $name, 'slug' => $slug]);

        $category = $this->findById((int) $this->pdo->lastInsertId());

        if ($category === null) {
            throw new RuntimeException('Failed to load category after insert.');
        }

        return $category;
    }

    public function update(int $id, string $name, string $slug): ?Category
    {
        $statement = $this->pdo->prepare(
            'UPDATE categories SET name = :name, slug = :slug WHERE id = :id',
        );
        $statement->execute(['id' => $id, 'name' => $name, 'slug' => $slug]);

        return $this->findById($id);
    }

    public function delete(int $id): bool
    {
        $statement = $this->pdo->prepare('DELETE FROM categories WHERE id = :id');
        $statement->execute(['id' => $id]);

        return $statement->rowCount() > 0;
    }
}
