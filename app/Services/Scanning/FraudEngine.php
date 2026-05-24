<?php

declare(strict_types=1);

namespace App\Services\Scanning;

use App\Services\Scanning\Contracts\FraudRule;
use App\Services\Scanning\Data\FraudVerdict;
use App\Services\Scanning\Data\ScanContext;
use Illuminate\Contracts\Container\Container;

/**
 * Runs every registered FraudRule against a ScanContext and combines
 * their verdicts into one final outcome.
 *
 * Combination rule: strongest verdict wins (highest severity). Ties
 * resolved by outcome priority deny > warn > allow. Every rule's
 * verdict is preserved in the `flags` list so the dashboard / mobile
 * UI can show "Allowed, but: 2 warnings" alongside the headline.
 *
 * Rules are configured in `config/scanning.php`:
 *
 *   'fraud_rules' => [
 *       \App\Services\Scanning\Rules\TicketNotFoundRule::class,
 *       \App\Services\Scanning\Rules\VoidedTicketRule::class,
 *       // …
 *   ]
 *
 * Third parties can drop a class into that list to add or replace
 * rules — no engine code edits needed.
 */
class FraudEngine
{
    public function __construct(protected Container $container) {}

    /**
     * @return array{verdict: FraudVerdict, flags: array<int, array<string, mixed>>}
     */
    public function evaluate(ScanContext $context): array
    {
        $verdicts = [];
        foreach ($this->rules() as $rule) {
            $v = $rule->evaluate($context);
            if ($v->outcome !== FraudVerdict::OUTCOME_ALLOW) {
                $verdicts[] = $v;
            }
        }

        if ($verdicts === []) {
            return ['verdict' => FraudVerdict::allow(), 'flags' => []];
        }

        usort($verdicts, function (FraudVerdict $a, FraudVerdict $b) {
            $rank = fn (string $o) => match ($o) {
                FraudVerdict::OUTCOME_DENY => 2,
                FraudVerdict::OUTCOME_WARN => 1,
                default => 0,
            };

            $diff = $rank($b->outcome) - $rank($a->outcome);
            if ($diff !== 0) {
                return $diff;
            }

            return $b->severity - $a->severity;
        });

        return [
            'verdict' => $verdicts[0],
            'flags' => array_map(fn (FraudVerdict $v) => $v->toArray(), $verdicts),
        ];
    }

    /**
     * @return iterable<FraudRule>
     */
    protected function rules(): iterable
    {
        foreach ((array) config('scanning.fraud_rules', []) as $class) {
            if (! class_exists($class)) {
                continue;
            }
            $instance = $this->container->make($class);
            if ($instance instanceof FraudRule) {
                yield $instance;
            }
        }
    }
}
