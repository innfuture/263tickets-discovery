<?php

declare(strict_types=1);

namespace App\Services\Stakeholders;

use App\Enums\StakeholderType;
use App\Services\Stakeholders\Workflows\Contracts\StakeholderWorkflow;
use App\Services\Stakeholders\Workflows\DefaultStakeholderWorkflow;
use App\Services\Stakeholders\Workflows\FoodVendorWorkflow;
use App\Services\Stakeholders\Workflows\MediaPartnerWorkflow;
use App\Services\Stakeholders\Workflows\ServiceProviderWorkflow;
use App\Services\Stakeholders\Workflows\SponsorWorkflow;
use Illuminate\Contracts\Container\Container;

/**
 * Strategy lookup. Defaults: 4 dedicated workflows for the most
 * common types; everything else falls through to DefaultStakeholderWorkflow.
 * Override by binding your own impl against the type's key.
 */
class StakeholderWorkflowRegistry
{
    public function __construct(protected Container $container) {}

    public function for(string|StakeholderType $type): StakeholderWorkflow
    {
        $value = $type instanceof StakeholderType ? $type->value : $type;

        return match ($value) {
            StakeholderType::Sponsor->value => $this->container->make(SponsorWorkflow::class),
            StakeholderType::MediaPartner->value => $this->container->make(MediaPartnerWorkflow::class),
            StakeholderType::FoodVendor->value => $this->container->make(FoodVendorWorkflow::class),
            StakeholderType::ServiceProvider->value => $this->container->make(ServiceProviderWorkflow::class),
            default => new DefaultStakeholderWorkflow(StakeholderType::from($value)),
        };
    }
}
