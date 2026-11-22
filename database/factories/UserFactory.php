<?php

namespace Database\Factories;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Cashier\Subscription;
use Laravel\Cashier\SubscriptionItem;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Place the account on the Free plan.
     */
    public function freePlan(): static
    {
        return $this->state(fn (array $attributes) => [
            'plan_id' => Plan::query()->where('slug', 'free')->value('id'),
        ]);
    }

    /**
     * Place the account on the Pro plan.
     *
     * Pro access is decided by a live subscription, so the account is given
     * an active subscription for the Pro price as well as the stored plan id.
     * Both are recorded so the stored plan and the entitlement agree. A price
     * may be given to subscribe to something other than the configured Pro
     * price, which is how a mismatched subscription is set up.
     */
    public function proPlan(?string $price = null): static
    {
        return $this->state(fn (array $attributes) => [
            'plan_id' => Plan::query()->where('slug', 'pro')->value('id'),
        ])->proSubscription($price);
    }

    /**
     * Give the account an active subscription for the Pro price.
     *
     * The subscription is written straight to the database using Cashier's
     * own factories, so no request is ever made to Stripe. The price is read
     * from configuration; when no Pro price is configured no subscription is
     * created and the account keeps Free limits.
     */
    public function proSubscription(?string $price = null): static
    {
        return $this->afterCreating(function (User $user) use ($price): void {
            $priceId = $price ?? config('services.stripe.price_pro');

            if (! is_string($priceId) || $priceId === '') {
                return;
            }

            $subscription = Subscription::factory()
                ->active()
                ->withPrice($priceId)
                ->create(['user_id' => $user->id]);

            SubscriptionItem::factory()->create([
                'subscription_id' => $subscription->id,
                'stripe_price' => $priceId,
            ]);
        });
    }
}
