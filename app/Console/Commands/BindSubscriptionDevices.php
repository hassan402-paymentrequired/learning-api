<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Services\SubscriptionDeviceResolver;
use Illuminate\Console\Command;

class BindSubscriptionDevices extends Command
{
    protected $signature = 'subscriptions:bind-devices
                            {--dry-run : Preview bindings without writing to the database}
                            {--user= : Only process subscriptions for this user ID}';

    protected $description = 'One-time backfill: bind device_id on active subscriptions that were never linked to a device';

    public function handle(SubscriptionDeviceResolver $resolver): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $userId = $this->option('user');

        if ($dryRun) {
            $this->warn('Dry run mode — no changes will be saved.');
        }

        $query = Subscription::query()
            ->with('user:id,name,email')
            ->where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '>', now())
            ->whereNull('device_id');

        if ($userId) {
            $query->where('user_id', $userId);
        }

        $subscriptions = $query->orderBy('user_id')->orderBy('starts_at')->get();

        if ($subscriptions->isEmpty()) {
            $this->info('No active unbound subscriptions found.');

            return self::SUCCESS;
        }

        $this->info("Found {$subscriptions->count()} active unbound subscription(s).");
        $this->newLine();

        $bound = 0;
        $skipped = 0;
        $unresolved = [];

        foreach ($subscriptions as $subscription) {
            $resolution = $resolver->resolve($subscription);

            if (!$resolution) {
                $skipped++;
                $unresolved[] = [
                    'subscription_id' => $subscription->id,
                    'user_id' => $subscription->user_id,
                    'user' => $subscription->user?->email ?? 'unknown',
                    'type' => $subscription->type,
                    'starts_at' => $subscription->starts_at?->toDateTimeString(),
                    'premium_practice' => $resolver->hasPremiumPracticeSinceSubscription($subscription) ? 'yes' : 'no',
                ];
                continue;
            }

            $this->line(sprintf(
                '  Subscription #%d (user #%d, %s) → device %s [%s]',
                $subscription->id,
                $subscription->user_id,
                $subscription->type ?? 'unknown',
                $this->shortDeviceId($resolution['device_id']),
                $resolution['source']
            ));

            if (!$dryRun) {
                $subscription->update(['device_id' => $resolution['device_id']]);
            }

            $bound++;
        }

        $this->newLine();
        $this->info($dryRun ? 'Dry run summary:' : 'Backfill complete:');
        $this->line("  Bound: {$bound}");
        $this->line("  Unresolved: {$skipped}");

        if ($unresolved !== []) {
            $this->newLine();
            $this->warn('These subscriptions could not be bound automatically.');
            $this->table(
                ['Subscription', 'User ID', 'Email', 'Type', 'Starts', 'Premium practice'],
                array_map(
                    fn (array $row) => [
                        $row['subscription_id'],
                        $row['user_id'],
                        $row['user'],
                        $row['type'],
                        $row['starts_at'] ?? '-',
                        $row['premium_practice'],
                    ],
                    $unresolved
                )
            );
            $this->comment(
                'Unresolved users must open the app on their subscribing device once; the client will call POST /subscriptions/register-device.'
            );
        }

        if ($dryRun && $bound > 0) {
            $this->newLine();
            $this->comment('Re-run without --dry-run to apply bindings.');
        }

        return self::SUCCESS;
    }

    private function shortDeviceId(string $deviceId): string
    {
        if (strlen($deviceId) <= 12) {
            return $deviceId;
        }

        return substr($deviceId, 0, 8) . '…';
    }
}
