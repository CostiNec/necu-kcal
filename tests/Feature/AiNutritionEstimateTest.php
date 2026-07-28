<?php

namespace Tests\Feature;

use App\Models\DiaryDay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AiNutritionEstimateTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_authenticated_user_can_estimate_a_meal(): void
    {
        $this->fakeGeminiResponse();

        $this->actingAs($this->onboardedUser())
            ->postJson('/diary-entries/ai/estimate', [
                'description' => '2 eggs, toast with butter and a small latte',
            ])
            ->assertOk()
            ->assertJsonPath('estimate.name', 'Eggs, toast and latte')
            ->assertJsonPath('estimate.weight_grams', 420)
            ->assertJsonPath('estimate.calories_per_100g', 145)
            ->assertJsonPath('estimate.confidence', 'medium');

        Http::assertSent(function (Request $request): bool {
            $payload = $request->data();

            return $request->url()
                    === 'https://gemini.test/v1beta/models/gemini-3.6-flash:generateContent'
                && $request->hasHeader('x-goog-api-key', 'test-key')
                && $payload['generationConfig']['responseMimeType']
                    === 'application/json'
                && $payload['generationConfig']['responseJsonSchema']['type']
                    === 'object'
                && $payload['generationConfig']['maxOutputTokens'] === 2048
                && str_contains(
                    $payload['contents'][0]['parts'][0]['text'],
                    '2 eggs, toast with butter and a small latte'
                );
        });
    }

    public function test_an_estimate_can_use_two_images_without_text(): void
    {
        $this->fakeGeminiResponse();
        $images = [
            UploadedFile::fake()->image('meal.jpg', 200, 200),
            UploadedFile::fake()->image('label.png', 200, 200),
        ];

        $this->actingAs($this->onboardedUser())
            ->post('/diary-entries/ai/estimate', [
                'description' => '',
                'images' => $images,
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('estimate.weight_grams', 420);

        Http::assertSent(function (Request $request): bool {
            $content = $request->data()['contents'][0]['parts'];
            $imageInputs = array_values(array_filter(
                $content,
                fn (array $item): bool => isset($item['inline_data'])
            ));

            return count($imageInputs) === 2
                && $imageInputs[0]['inline_data']['mime_type']
                    === 'image/jpeg'
                && base64_decode(
                    $imageInputs[0]['inline_data']['data'],
                    true
                ) !== false
                && $imageInputs[1]['inline_data']['mime_type']
                    === 'image/png';
        });
    }

    public function test_the_provider_can_be_switched_to_openai(): void
    {
        $this->fakeOpenAiResponse();

        $this->actingAs($this->onboardedUser())
            ->postJson('/diary-entries/ai/estimate', [
                'description' => 'A bowl of oatmeal',
            ])
            ->assertOk()
            ->assertJsonPath('estimate.name', 'Eggs, toast and latte');

        Http::assertSent(function (Request $request): bool {
            $payload = $request->data();

            return $request->url() === 'https://api.openai.test/v1/responses'
                && $request->hasHeader('Authorization', 'Bearer test-key')
                && $payload['model'] === 'gpt-5.6-luna'
                && $payload['store'] === false
                && $payload['text']['format']['type'] === 'json_schema'
                && $payload['text']['format']['strict'] === true;
        });
    }

    public function test_gemini_keys_are_used_in_round_robin_order(): void
    {
        config([
            'nutrition-ai.provider' => 'gemini',
            'services.gemini.api_key' => 'first-key',
            'services.gemini.api_key_2' => 'second-key',
            'services.gemini.api_key_3' => 'third-key',
            'services.gemini.base_url' => 'https://gemini.test/v1beta',
            'services.gemini.nutrition_model' => 'gemini-3.6-flash',
        ]);
        Cache::forget('nutrition-ai:gemini:api-key-cursor');
        Http::fake([
            'gemini.test/*' => Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [[
                            'text' => json_encode($this->estimatePayload()),
                        ]],
                    ],
                ]],
            ]),
        ]);
        $this->actingAs($this->onboardedUser());

        $this->postJson('/diary-entries/ai/estimate', [
            'description' => 'First meal',
        ])->assertOk();
        $this->postJson('/diary-entries/ai/estimate', [
            'description' => 'Second meal',
        ])->assertOk();
        $this->postJson('/diary-entries/ai/estimate', [
            'description' => 'Third meal',
        ])->assertOk();

        $keys = Http::recorded()->map(
            fn (array $pair): string => $pair[0]
                ->header('x-goog-api-key')[0]
        )->all();

        $this->assertSame(['first-key', 'second-key', 'third-key'], $keys);
    }

    public function test_remaining_gemini_keys_are_tried_until_one_succeeds(): void
    {
        config([
            'nutrition-ai.provider' => 'gemini',
            'services.gemini.api_key' => 'rate-limited-key',
            'services.gemini.api_key_2' => 'unavailable-key',
            'services.gemini.api_key_3' => 'working-key',
            'services.gemini.base_url' => 'https://gemini.test/v1beta',
            'services.gemini.nutrition_model' => 'gemini-3.6-flash',
        ]);
        Cache::forget('nutrition-ai:gemini:api-key-cursor');
        Http::fake(function (Request $request) {
            if ($request->hasHeader('x-goog-api-key', 'rate-limited-key')) {
                return Http::response([
                    'error' => [
                        'status' => 'RESOURCE_EXHAUSTED',
                        'message' => 'Please retry in 36 seconds.',
                    ],
                ], 429);
            }

            if ($request->hasHeader('x-goog-api-key', 'unavailable-key')) {
                return Http::response([
                    'error' => [
                        'status' => 'UNAVAILABLE',
                        'message' => 'Please retry later.',
                    ],
                ], 503);
            }

            return Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [[
                            'text' => json_encode($this->estimatePayload()),
                        ]],
                    ],
                ]],
            ]);
        });

        $this->actingAs($this->onboardedUser())
            ->postJson('/diary-entries/ai/estimate', [
                'description' => 'A bowl of oatmeal',
            ])
            ->assertOk()
            ->assertJsonPath('estimate.name', 'Eggs, toast and latte');

        $keys = Http::recorded()->map(
            fn (array $pair): string => $pair[0]
                ->header('x-goog-api-key')[0]
        )->all();

        $this->assertSame([
            'rate-limited-key',
            'unavailable-key',
            'working-key',
        ], $keys);
    }

    public function test_the_estimate_requires_text_or_an_image(): void
    {
        Http::fake();

        $this->actingAs($this->onboardedUser())
            ->postJson('/diary-entries/ai/estimate', [
                'description' => '',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('description');
    }

    public function test_the_estimate_rejects_more_than_two_images(): void
    {
        Http::fake();

        $this->actingAs($this->onboardedUser())
            ->post('/diary-entries/ai/estimate', [
                'images' => [
                    UploadedFile::fake()->image('one.jpg'),
                    UploadedFile::fake()->image('two.jpg'),
                    UploadedFile::fake()->image('three.jpg'),
                ],
            ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('images');
    }

    public function test_the_dedicated_ai_page_is_scoped_to_a_meal(): void
    {
        $this->actingAs($this->onboardedUser())
            ->get('/diary/2026-07-28/ai-entry?meal=lunch')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('diary/ai-add')
                ->where('date', '2026-07-28')
                ->where('meal', 'lunch')
            );
    }

    public function test_user_can_store_reviewed_per_100g_ai_values(): void
    {
        $this->actingAs($this->onboardedUser())
            ->post('/diary-entries/ai', [
                'date' => '2026-07-28',
                'meal' => 'dinner',
                'name' => 'Chicken and rice',
                'weight_grams' => 350,
                'calories_per_100g' => 160,
                'protein_per_100g' => 12,
                'carbohydrates_per_100g' => 18,
                'fat_per_100g' => 4,
                'fibre_per_100g' => 2,
            ])
            ->assertRedirect('/diary/2026-07-28?focus_meal=dinner');

        $entry = DiaryDay::firstOrFail()->entries()->firstOrFail();

        $this->assertSame('Chicken and rice', $entry->food_name);
        $this->assertSame(350.0, $entry->total_grams);
        $this->assertSame(560.0, $entry->calories);
        $this->assertSame(42.0, $entry->protein);
        $this->assertSame(63.0, $entry->carbohydrates);
        $this->assertSame(14.0, $entry->fat);
        $this->assertSame(7.0, $entry->fibre);
    }

    public function test_the_estimate_requires_authentication(): void
    {
        $this->postJson('/diary-entries/ai/estimate', [
            'description' => 'A bowl of oatmeal',
        ])->assertRedirect('/login');
    }

    public function test_estimates_are_limited_to_four_requests_per_minute(): void
    {
        $this->fakeGeminiResponse();
        $this->actingAs($this->onboardedUser());

        foreach (range(1, 4) as $requestNumber) {
            $this->postJson('/diary-entries/ai/estimate', [
                'description' => "Meal estimate {$requestNumber}",
            ])->assertOk();
        }

        $this->postJson('/diary-entries/ai/estimate', [
            'description' => 'One request too many',
        ])->assertStatus(429);

        Http::assertSentCount(4);
    }

    public function test_an_unavailable_provider_returns_a_safe_error(): void
    {
        config([
            'nutrition-ai.provider' => 'gemini',
            'services.gemini.api_key' => null,
            'services.gemini.api_key_2' => null,
            'services.gemini.api_key_3' => null,
        ]);
        Cache::forget('nutrition-ai:gemini:api-key-cursor');
        Http::fake();

        $this->actingAs($this->onboardedUser())
            ->postJson('/diary-entries/ai/estimate', [
                'description' => 'A bowl of oatmeal',
            ])
            ->assertStatus(503)
            ->assertJsonPath(
                'message',
                'The nutrition estimate is unavailable right now. Try again in a moment.'
            );
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
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => json_encode(
                                        $this->estimatePayload()
                                    ),
                                ],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);
    }

    private function fakeOpenAiResponse(): void
    {
        config([
            'nutrition-ai.provider' => 'openai',
            'services.openai.api_key' => 'test-key',
            'services.openai.base_url' => 'https://api.openai.test/v1',
            'services.openai.nutrition_model' => 'gpt-5.6-luna',
        ]);
        Http::fake([
            'api.openai.test/*' => Http::response([
                'output' => [
                    [
                        'type' => 'message',
                        'content' => [
                            [
                                'type' => 'output_text',
                                'text' => json_encode(
                                    $this->estimatePayload()
                                ),
                            ],
                        ],
                    ],
                ],
            ]),
        ]);
    }

    /**
     * @return array<string, int|float|string>
     */
    private function estimatePayload(): array
    {
        return [
            'name' => 'Eggs, toast and latte',
            'weight_grams' => 420,
            'calories_per_100g' => 145,
            'protein_per_100g' => 6.7,
            'carbohydrates_per_100g' => 12.9,
            'fat_per_100g' => 7.4,
            'fibre_per_100g' => 1.2,
            'confidence' => 'medium',
            'assumptions' => 'Two eggs and a small latte.',
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
