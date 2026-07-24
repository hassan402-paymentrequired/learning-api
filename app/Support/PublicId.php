<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

final class PublicId
{
    /**
     * Serialize a model for public API responses (no internal numeric id).
     */
    public static function model(Model $model, array $extra = []): array
    {
        return array_merge(self::stripInternalId($model->toArray()), $extra);
    }

    /**
     * @param  iterable<int, Model>|Collection<int, Model>  $models
     * @return array<int, array<string, mixed>>
     */
    public static function collection(iterable $models): array
    {
        $items = [];

        foreach ($models as $model) {
            $items[] = self::model($model);
        }

        return $items;
    }

    /**
     * Public user payload for auth/profile endpoints.
     */
    public static function user(Model $user): array
    {
        return [
            'uuid' => $user->uuid,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'email_verified_at' => $user->email_verified_at,
            'subscription_status' => $user->subscription_status ?? null,
            'subscription_expires_at' => $user->subscription_expires_at ?? null,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];
    }

    /**
     * Public exam attempt summary for list/analytics endpoints.
     */
    public static function attemptSummary(Model $attempt, ?Model $exam = null): array
    {
        $exam = $exam ?? ($attempt->relationLoaded('exam') ? $attempt->exam : null);

        return [
            'uuid' => $attempt->uuid,
            'exam_uuid' => $exam?->uuid,
            'exam_title' => $exam ? $exam->title : 'Practice Session',
            'status' => $attempt->status,
            'score' => $attempt->score,
            'correct_answers' => $attempt->correct_answers,
            'total_questions' => $attempt->total_questions,
            'percentage' => $attempt->percentage ?? null,
            'started_at' => $attempt->started_at,
            'completed_at' => $attempt->completed_at,
        ];
    }

    /**
     * Public subscription plan payload.
     */
    public static function subscriptionPlan(Model $plan): array
    {
        return [
            'uuid' => $plan->uuid,
            'name' => $plan->name,
            'description' => $plan->description,
            'price' => (float) $plan->price,
            'currency' => $plan->currency,
            'interval' => $plan->interval,
            'interval_count' => $plan->interval_count,
        ];
    }

    /**
     * Public subscription payload.
     */
    public static function subscription(Model $subscription): array
    {
        return [
            'uuid' => $subscription->uuid,
            'type' => $subscription->type,
            'status' => $subscription->status,
            'expires_at' => $subscription->expires_at?->toIso8601String(),
            'plan' => $subscription->relationLoaded('plan') && $subscription->plan
                ? [
                    'uuid' => $subscription->plan->uuid,
                    'name' => $subscription->plan->name,
                    'price' => (float) $subscription->plan->price,
                ]
                : null,
        ];
    }

    /**
     * Standard question payload for exam/practice endpoints.
     */
    public static function question(Model $question): array
    {
        $answers = $question->relationLoaded('answers')
            ? $question->answers->map(fn (Model $answer) => [
                'uuid' => $answer->uuid,
                'answer_text' => $answer->answer_text,
                'order' => $answer->order,
            ])->values()->all()
            : [];

        return [
            'uuid' => $question->uuid,
            'question_text' => $question->question_text,
            'question_type' => $question->question_type,
            'image' => $question->image,
            'image_url' => $question->image
                ? asset('storage/' . ltrim($question->image, '/'))
                : null,
            'explanation' => $question->explanation ?? null,
            'expected_answer' => $question->expected_answer ?? null,
            'answers' => $answers,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function stripInternalId(array $data): array
    {
        unset($data['id']);

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = self::stripInternalId($value);
            }
        }

        return $data;
    }
}
