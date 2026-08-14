<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ai_graduation_reviews')) {
            return;
        }

        Schema::table('ai_graduation_reviews', function (Blueprint $table) {
            if (! Schema::hasColumn('ai_graduation_reviews', 'behavior_trend')) {
                $table->string('behavior_trend')->nullable()->after('review_status');
            }

            if (! Schema::hasColumn('ai_graduation_reviews', 'trend_explanation')) {
                $table->text('trend_explanation')->nullable()->after('behavior_trend');
            }

            if (! Schema::hasColumn('ai_graduation_reviews', 'pattern_type')) {
                $table->string('pattern_type')->nullable()->after('trend_explanation');
            }

            if (! Schema::hasColumn('ai_graduation_reviews', 'supporting_evidence')) {
                $table->json('supporting_evidence')->nullable()->after('outstanding_sanctions');
            }

            if (! Schema::hasColumn('ai_graduation_reviews', 'data_contradictions')) {
                $table->json('data_contradictions')->nullable()->after('supporting_evidence');
            }

            if (! Schema::hasColumn('ai_graduation_reviews', 'data_fingerprint')) {
                $table->string('data_fingerprint', 64)->nullable()->after('data_contradictions');
            }

            if (! Schema::hasColumn('ai_graduation_reviews', 'is_outdated')) {
                $table->boolean('is_outdated')->default(false)->after('data_fingerprint');
            }

            if (! Schema::hasColumn('ai_graduation_reviews', 'decision_status')) {
                $table->string('decision_status')->nullable()->after('is_outdated');
            }

            if (! Schema::hasColumn('ai_graduation_reviews', 'decision_remarks')) {
                $table->text('decision_remarks')->nullable()->after('decision_status');
            }

            if (! Schema::hasColumn('ai_graduation_reviews', 'decision_by_admin_id')) {
                $table->unsignedBigInteger('decision_by_admin_id')->nullable()->after('decision_remarks');
            }

            if (! Schema::hasColumn('ai_graduation_reviews', 'decision_at')) {
                $table->timestamp('decision_at')->nullable()->after('decision_by_admin_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('ai_graduation_reviews')) {
            return;
        }

        Schema::table('ai_graduation_reviews', function (Blueprint $table) {
            foreach ([
                'behavior_trend',
                'trend_explanation',
                'pattern_type',
                'supporting_evidence',
                'data_contradictions',
                'data_fingerprint',
                'is_outdated',
                'decision_status',
                'decision_remarks',
                'decision_by_admin_id',
                'decision_at',
            ] as $column) {
                if (Schema::hasColumn('ai_graduation_reviews', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
