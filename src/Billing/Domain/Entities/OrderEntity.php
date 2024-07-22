<?php

declare(strict_types=1);

namespace Billing\Domain\Entities;

use Billing\Domain\Exceptions\AlreadyFulfilledException;
use Billing\Domain\Exceptions\AlreadyPaidException;
use Billing\Domain\ValueObjects\ExternalId;
use Billing\Domain\ValueObjects\OrderStatus;
use Billing\Domain\ValueObjects\PaymentGateway;
use Billing\Domain\ValueObjects\Price;
use Billing\Domain\ValueObjects\TrialPeriodDays;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use DomainException;
use Shared\Domain\ValueObjects\CurrencyCode;
use Shared\Domain\ValueObjects\Id;
use Workspace\Domain\Entities\WorkspaceEntity;

#[ORM\Entity]
#[ORM\Table(name: '`order`')]
#[ORM\HasLifecycleCallbacks]
class OrderEntity
{
    /** A unique numeric identifier of the entity. */
    #[ORM\Embedded(class: Id::class, columnPrefix: false)]
    private Id $id;

    #[ORM\Column(name: "currency_code", type: Types::STRING, length: 3, enumType: CurrencyCode::class)]
    private CurrencyCode $currencyCode;

    #[ORM\Embedded(class: TrialPeriodDays::class, columnPrefix: false)]
    private TrialPeriodDays $trialPeriodDays;

    #[ORM\Embedded(class: PaymentGateway::class, columnPrefix: false)]
    private PaymentGateway $paymentGateway;

    #[ORM\Embedded(class: ExternalId::class, columnPrefix: false)]
    private ExternalId $externalId;

    #[ORM\Column(type: Types::BOOLEAN, name: 'is_paid', options: ['default' => false])]
    private bool $isPaid = false;

    #[ORM\Column(type: Types::BOOLEAN, name: 'is_fulfilled', options: ['default' => false])]
    private bool $isFullfilled = false;

    /** Creation date and time of the entity */
    #[ORM\Column(type: 'datetime', name: 'created_at')]
    private DateTimeInterface $createdAt;

    /** The date and time when the entity was last modified. */
    #[ORM\Column(type: 'datetime', name: 'updated_at', nullable: true)]
    private ?DateTimeInterface $updatedAt = null;

    #[ORM\ManyToOne(targetEntity: WorkspaceEntity::class, inversedBy: 'orders')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private WorkspaceEntity $workspace;

    #[ORM\ManyToOne(targetEntity: PlanSnapshotEntity::class, cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(name: "plan_snapshot_id", nullable: false)]
    private PlanSnapshotEntity $plan;

    #[ORM\OneToOne(targetEntity: SubscriptionEntity::class, mappedBy: 'order')]
    private ?SubscriptionEntity $subscription = null;

    public function __construct(
        WorkspaceEntity $workspace,
        PlanEntity $plan,
        CurrencyCode $currencyCode,
        ?TrialPeriodDays $trialPeriodDays = null
    ) {
        if ($plan->getPrice()->value == 0 && $plan->getBillingCycle()->isRecurring()) {
            $sub = $workspace->getSubscription();

            if ($sub && $sub->getPlan()->getPrice()->value == 0) {
                throw new DomainException('Workspace already has a free subscription');
            }

            if (!$workspace->isEligibleForFreePlan()) {
                throw new DomainException('Workspace is not eligible for a free plan');
            }
        }

        $this->id = new Id();
        $this->currencyCode = $currencyCode;

        $this->trialPeriodDays = $trialPeriodDays
            && $workspace->isEligibleForTrial()
            && $plan->getPrice()->value > 0
            && $plan->getBillingCycle()->isRecurring() ? $trialPeriodDays
            : new TrialPeriodDays();

        $this->paymentGateway = new PaymentGateway();
        $this->externalId = new ExternalId();

        $this->createdAt = new DateTimeImmutable();
        $this->workspace = $workspace;
        $this->plan = $plan->getSnapshot();
    }

    public function getId(): Id
    {
        return $this->id;
    }

    public function getCurrencyCode(): CurrencyCode
    {
        return $this->currencyCode;
    }

    public function getTrialPeriodDays(): TrialPeriodDays
    {
        return $this->trialPeriodDays;
    }

    public function getPaymentGateway(): PaymentGateway
    {
        return $this->paymentGateway;
    }

    public function getExternalId(): ExternalId
    {
        return $this->externalId;
    }

    public function isPaid(): bool
    {
        return $this->isPaid;
    }

    public function isFulfilled(): bool
    {
        return $this->isFullfilled;
    }

    public function getCreatedAt(): DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?DateTimeInterface
    {
        return $this->updatedAt;
    }

    #[ORM\PreUpdate]
    public function preUpdate(): void
    {
        $this->updatedAt = new DateTime();
    }

    public function getWorkspace(): WorkspaceEntity
    {
        return $this->workspace;
    }

    public function getPlan(): PlanSnapshotEntity
    {
        return $this->plan;
    }

    public function getSubscription(): ?SubscriptionEntity
    {
        return $this->subscription;
    }

    public function getStatus(): OrderStatus
    {
        if ($this->isPaid && $this->isFullfilled) {
            return OrderStatus::COMPLETED;
        }

        if ($this->isPaid()) {
            return OrderStatus::PENDING;
        }

        return OrderStatus::FAILED;
    }

    public function getTotalPrice(): Price
    {
        return $this->plan->getPrice();
    }

    public function fulfill(): void
    {
        if ($this->isFullfilled) {
            throw new AlreadyFulfilledException($this);
        }

        $this->isFullfilled = true;
    }

    public function pay(
        ?PaymentGateway $paymentGateway = null,
        ?ExternalId $externalId = null,
    ): void {
        if ($this->isFullfilled) {
            throw new AlreadyFulfilledException($this);
        }

        if ($this->isPaid) {
            throw new AlreadyPaidException($this);
        }

        $this->paymentGateway = $paymentGateway ?: new PaymentGateway();
        $this->externalId = $externalId ?: new ExternalId();

        $this->isPaid = true;
    }

    public function initiatePayment(
        PaymentGateway $paymentGateway,
        ExternalId $externalId,
    ): void {
        if ($this->isFullfilled) {
            throw new AlreadyFulfilledException($this);
        }

        if ($this->isPaid) {
            throw new AlreadyPaidException($this);
        }

        $this->paymentGateway = $paymentGateway ?: new PaymentGateway();
        $this->externalId = $externalId ?: new ExternalId();
    }
}
