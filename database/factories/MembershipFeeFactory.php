<?php

namespace Database\Factories;

use App\Models\MembershipFee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MembershipFee>
 *
 * NOTE: MembershipFee imports HasFactory but does not use the trait in its
 * class body, so MembershipFee::factory() does NOT resolve. Instantiate this
 * factory directly: MembershipFeeFactory::new()->create().
 */
class MembershipFeeFactory extends Factory
{
    protected $model = MembershipFee::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Column is varchar(50).
            'name' => 'Fee '.fake()->unique()->lexify('????????'),
            'amount' => 1000,
            'id_card_fee' => 0,
            'validity_years' => 1,
            'for_organizations' => false,
            'is_active' => true,
        ];
    }
}
