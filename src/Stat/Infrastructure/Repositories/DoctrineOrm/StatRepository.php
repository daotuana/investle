<?php

declare(strict_types=1);

namespace Stat\Infrastructure\Repositories\DoctrineOrm;

use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use InvalidArgumentException;
use Override;
use RuntimeException;
use Shared\Infrastructure\Repositories\DoctrineOrm\AbstractRepository;
use Stat\Domain\Entities\AbstractStatEntity;
use Stat\Domain\Entities\OrderStatEntity;
use Stat\Domain\Entities\SignupStatEntity;
use Stat\Domain\Entities\SubscriptionStatEntity;
use Stat\Domain\Entities\UsageStatEntity;
use Stat\Domain\Repositories\StatRepositoryInterface;
use Stat\Domain\ValueObjects\DatasetCategory;
use Stat\Domain\ValueObjects\StatType;
use Traversable;
use Workspace\Domain\Entities\WorkspaceEntity;

class StatRepository extends AbstractRepository implements
    StatRepositoryInterface
{
    private const ENTITY_CLASS = AbstractStatEntity::class;
    private const ALIAS = 'stat';

    /**
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em, self::ENTITY_CLASS, self::ALIAS);
    }

    #[Override]
    public function add(AbstractStatEntity $stat): static
    {
        // Get the repository based on the stat entity type
        $repo = $this->getRepositoryForStat($stat);

        // Determine the unique criteria to find an existing stat
        $criteria = $this->getCriteriaForStat($stat);

        // Find the existing stat or return null
        $existingStat = $repo->findOneBy($criteria);

        // If an existing stat is found, increment its metric; 
        // otherwise, persist the new stat
        if ($existingStat) {
            $existingStat->incrementMetric($stat->getMetric());
        } else {
            $this->em->persist($stat);
        }

        return $this;
    }

    /**
     * Determine and return the repository for the given stat type.
     */
    private function getRepositoryForStat(
        AbstractStatEntity $stat
    ): EntityRepository {
        return match (true) {
            $stat instanceof UsageStatEntity =>
            $this->em->getRepository(UsageStatEntity::class),

            $stat instanceof SignupStatEntity =>
            $this->em->getRepository(SignupStatEntity::class),

            $stat instanceof SubscriptionStatEntity =>
            $this->em->getRepository(SubscriptionStatEntity::class),

            $stat instanceof OrderStatEntity =>
            $this->em->getRepository(OrderStatEntity::class),

            default => throw new InvalidArgumentException('Invalid stat type')
        };
    }

    /**
     * Get the search criteria based on the type of stat.
     */
    private function getCriteriaForStat(AbstractStatEntity $stat): array
    {
        return match (true) {
            $stat instanceof SignupStatEntity => [
                'date' => $stat->getDate(),
                'countryCode' => $stat->getCountryCode(),
            ],
            $stat instanceof UsageStatEntity => [
                'date' => $stat->getDate(),
                'workspace' => $stat->getWorkspace(),
            ],
            default => ['date' => $stat->getDate()],
        };
    }

    #[Override]
    public function filterByType(StatType $type): static
    {
        return $this->filter(static function (QueryBuilder $qb) use ($type) {
            match ($type) {
                StatType::USAGE => $qb->resetDQLPart('from')
                    ->from(UsageStatEntity::class, self::ALIAS),

                StatType::SIGNUP => $qb->resetDQLPart('from')
                    ->from(SignupStatEntity::class, self::ALIAS),

                StatType::SUBSCRIPTION => $qb->resetDQLPart('from')
                    ->from(SubscriptionStatEntity::class, self::ALIAS),

                StatType::ORDER => $qb->resetDQLPart('from')
                    ->from(OrderStatEntity::class, self::ALIAS),

                default => null,
            };
        });
    }

    #[Override]
    public function filterByYear(DateTimeInterface $date): static
    {
        return $this->filter(static function (QueryBuilder $qb) use ($date) {
            $qb->andWhere(self::ALIAS . '.date LIKE :date')
                ->setParameter(
                    ':date',
                    $date->format('Y') . '%',
                    Types::STRING
                );
        });
    }

    #[Override]
    public function filterByMonth(DateTimeInterface $date): static
    {
        return $this->filter(static function (QueryBuilder $qb) use ($date) {
            $qb->andWhere(self::ALIAS . '.date LIKE :date')
                ->setParameter(
                    ':date',
                    $date->format('Y-m') . '%',
                    Types::STRING
                );
        });
    }

    #[Override]
    public function filterByDay(DateTimeInterface $date): static
    {
        return $this->filter(static function (QueryBuilder $qb) use ($date) {
            $qb->andWhere(self::ALIAS . '.date = :date')
                ->setParameter(
                    ':date',
                    $date->format('Y-m-d'),
                    Types::STRING
                );
        });
    }

    #[Override]
    public function stat(): int
    {
        $stat = $this->query()
            ->select('SUM(' . self::ALIAS . '.metric.value)')
            ->getQuery()
            ->getSingleScalarResult();

        return (int) round((float) $stat);
    }

    #[Override]
    public function getDataset(DatasetCategory $type = DatasetCategory::DATE): Traversable
    {
        $qb = match ($type) {
            DatasetCategory::COUNTRY =>
            $this->query()
                ->select(self::ALIAS . '.countryCode')
                ->groupBy(self::ALIAS . '.countryCode'),
            default =>
            $this->query()
                ->select(self::ALIAS . '.date')
                ->groupBy(self::ALIAS . '.date')
        };

        $result = $qb
            ->addSelect('SUM(' . self::ALIAS . '.metric.value) as metric')
            ->getQuery()
            ->getArrayResult();

        foreach ($result as $row) {
            $category = match ($type) {
                DatasetCategory::COUNTRY => $row['countryCode'] ? $row['countryCode']->value : null,
                default => $row['date']->format('Y-m-d'),
            };

            yield [
                'category' => $category,
                'value' => (int) round((float) $row['metric']),
            ];
        }
    }
}
