<?php

namespace Tests\Feature;

use App\Models\DiaryDay;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AiFullDayImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_sidebar_full_day_link_opens_the_users_today(): void
    {
        CarbonImmutable::setTestNow(
            CarbonImmutable::create(
                2026,
                7,
                28,
                23,
                30,
                timezone: 'UTC'
            )
        );

        try {
            $this->actingAs($this->onboardedUser())
                ->get('/ai-day')
                ->assertRedirect('/diary/2026-07-29/ai-day');
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_the_full_day_page_is_available_for_a_diary_date(): void
    {
        $this->actingAs($this->onboardedUser())
            ->get('/diary/2026-07-29/ai-day')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('diary/ai-day')
                ->where('date', '2026-07-29')
            );
    }

    public function test_a_full_day_can_use_text_and_ten_images(): void
    {
        $this->fakeGeminiResponse();
        $images = array_map(
            fn (int $number): UploadedFile => UploadedFile::fake()->image(
                "meal-{$number}.jpg",
                100,
                100
            ),
            range(1, 10)
        );

        $this->actingAs($this->onboardedUser())
            ->post('/diary-entries/ai/day/estimate', [
                'description' => 'Photos 1-2 breakfast, 3-6 lunch, rest dinner.',
                'images' => $images,
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonCount(2, 'estimate.entries')
            ->assertJsonPath('estimate.entries.0.meal', 'breakfast')
            ->assertJsonPath('estimate.entries.0.name', 'Oatmeal with berries')
            ->assertJsonPath('estimate.entries.1.meal', 'lunch');

        Http::assertSent(function (Request $request): bool {
            $payload = $request->data();
            $parts = $payload['contents'][0]['parts'];
            $imageParts = array_filter(
                $parts,
                fn (array $part): bool => isset($part['inline_data'])
            );
            $schema = $payload['generationConfig']['responseJsonSchema'];

            return count($imageParts) === 10
                && $payload['generationConfig']['maxOutputTokens'] === 8192
                && $schema['properties']['entries']['type'] === 'array'
                && ! isset($schema['properties']['entries']['maxItems'])
                && str_contains(
                    $parts[0]['text'],
                    'Photos 1-2 breakfast, 3-6 lunch, rest dinner.'
                );
        });
    }

    public function test_full_day_estimation_accepts_text_without_images(): void
    {
        $this->fakeGeminiResponse();

        $this->actingAs($this->onboardedUser())
            ->postJson('/diary-entries/ai/day/estimate', [
                'description' => 'Breakfast was oatmeal and lunch was soup.',
            ])
            ->assertOk()
            ->assertJsonCount(2, 'estimate.entries');
    }

    public function test_full_day_estimation_rejects_more_than_ten_images(): void
    {
        Http::fake();
        $images = array_map(
            fn (int $number): UploadedFile => UploadedFile::fake()->image(
                "meal-{$number}.jpg"
            ),
            range(1, 11)
        );

        $this->actingAs($this->onboardedUser())
            ->post('/diary-entries/ai/day/estimate', [
                'images' => $images,
            ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('images');

        Http::assertNothingSent();
    }

    public function test_reviewed_full_day_entries_are_stored_separately(): void
    {
        $user = $this->onboardedUser();
        $day = DiaryDay::create([
            'user_id' => $user->id,
            'date' => '2026-07-29',
        ]);
        $day->entries()->create([
            'food_id' => null,
            'meal' => 'lunch',
            'food_name' => 'Existing lunch',
            'brand' => null,
            'unit' => 'g',
            'quantity' => 1,
            'amount' => 100,
            'total_grams' => 100,
            'total_milliliters' => null,
            'calories' => 100,
            'protein' => 5,
            'carbohydrates' => 10,
            'fat' => 2,
            'fibre' => 1,
            'position' => 2,
        ]);

        $this->actingAs($user)
            ->post('/diary-entries/ai/day', [
                'date' => '2026-07-29',
                'entries' => [
                    $this->entryPayload(
                        'breakfast',
                        'Oatmeal with berries',
                        300,
                        120
                    ),
                    $this->entryPayload(
                        'lunch',
                        'Chicken with rice',
                        400,
                        165
                    ),
                ],
            ])
            ->assertRedirect('/diary/2026-07-29');

        $entries = $day->fresh()->entries()
            ->orderBy('id')
            ->get();

        $this->assertCount(3, $entries);
        $this->assertSame('Oatmeal with berries', $entries[1]->food_name);
        $this->assertSame('breakfast', $entries[1]->meal);
        $this->assertSame(1, $entries[1]->position);
        $this->assertSame(360.0, $entries[1]->calories);
        $this->assertSame(24.0, $entries[1]->protein);
        $this->assertSame('Chicken with rice', $entries[2]->food_name);
        $this->assertSame('lunch', $entries[2]->meal);
        $this->assertSame(3, $entries[2]->position);
        $this->assertSame(660.0, $entries[2]->calories);
    }

    public function test_full_day_estimates_are_limited_to_four_per_minute(): void
    {
        $this->fakeGeminiResponse();
        $this->actingAs($this->onboardedUser());

        foreach (range(1, 4) as $requestNumber) {
            $this->postJson('/diary-entries/ai/day/estimate', [
                'description' => "Full day {$requestNumber}",
            ])->assertOk();
        }

        $this->postJson('/diary-entries/ai/day/estimate', [
            'description' => 'One full-day request too many',
        ])->assertStatus(429);

        Http::assertSentCount(4);
    }

    public function test_openai_can_produce_the_full_day_shape(): void
    {
        config([
            'nutrition-ai.provider' => 'openai',
            'services.openai.api_key' => 'test-key',
            'services.openai.base_url' => 'https://api.openai.test/v1',
            'services.openai.nutrition_model' => 'gpt-5.6-luna',
        ]);
        Http::fake([
            'api.openai.test/*' => Http::response([
                'output' => [[
                    'type' => 'message',
                    'content' => [[
                        'type' => 'output_text',
                        'text' => json_encode($this->dayEstimatePayload()),
                    ]],
                ]],
            ]),
        ]);

        $this->actingAs($this->onboardedUser())
            ->postJson('/diary-entries/ai/day/estimate', [
                'description' => 'A complete day of food.',
            ])
            ->assertOk()
            ->assertJsonCount(2, 'estimate.entries');

        Http::assertSent(function (Request $request): bool {
            $payload = $request->data();

            return $request->url() === 'https://api.openai.test/v1/responses'
                && $payload['max_output_tokens'] === 8192
                && $payload['text']['format']['name']
                    === 'day_nutrition_estimate'
                && $payload['text']['format']['schema']['properties']['entries']['type']
                    === 'array';
        });
    }

    private function fakeGeminiResponse(): void
    {
        config([
            'nutrition-ai.provider' => 'gemini',
            'services.gemini.api_key' => 'test-key',
            'services.gemini.api_key_2' => null,
            'services.gemini.api_key_3' => null,
            'services.gemini.base_url' => 'https://gemini.test/v1beta',
            'services.gemini.nutrition_model' => 'gemini-3.6-flash',
        ]);
        Cache::forget('nutrition-ai:gemini:api-key-cursor');
        Http::fake([
            'gemini.test/*' => Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [[
                            'text' => json_encode(
                                $this->dayEstimatePayload()
                            ),
                        ]],
                    ],
                ]],
            ]),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function dayEstimatePayload(): array
    {
        return [
            'entries' => [
                [
                    'meal' => 'breakfast',
                    ...$this->entryPayload(
                        'breakfast',
                        'Oatmeal with berries',
                        300,
                        120
                    ),
                    'confidence' => 'high',
                    'assumptions' => 'The bowl contains cooked oatmeal.',
                ],
                [
                    'meal' => 'lunch',
                    ...$this->entryPayload(
                        'lunch',
                        'Chicken with rice',
                        400,
                        165
                    ),
                    'confidence' => 'medium',
                    'assumptions' => 'The chicken is grilled.',
                ],
            ],
        ];
    }

    /**
     * @return array<string, int|string>
     */
    private function entryPayload(
        string $meal,
        string $name,
        int $weight,
        int $calories
    ): array {
        return [
            'meal' => $meal,
            'name' => $name,
            'weight_grams' => $weight,
            'calories_per_100g' => $calories,
            'protein_per_100g' => 8,
            'carbohydrates_per_100g' => 18,
            'fat_per_100g' => 4,
            'fibre_per_100g' => 2,
        ];
    }

    private function onboardedUser(): User
    {
        $user = User::factory()->create();
        $user->profile()->create([
            'timezone' => 'Europe/Bucharest',
            'unit_system' => 'metric',
            'onboarding_completed_at' => now(),
        ]);
        $user->nutritionTarget()->create([
            'calories' => 2000,
            'protein' => 120,
            'carbohydrates' => 220,
            'fat' => 65,
        ]);

        return $user;
    }
}
