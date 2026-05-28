<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('clinics')) {
            Schema::table('clinics', function (Blueprint $table): void {
                if (! Schema::hasColumn('clinics', 'slug')) {
                    $table->string('slug', 120)->nullable()->unique()->after('name');
                }

                if (! Schema::hasColumn('clinics', 'plan')) {
                    $table->string('plan', 50)->default('basic')->after('subscription_status');
                }

                if (! Schema::hasColumn('clinics', 'status')) {
                    $table->string('status', 50)->default('active')->after('plan');
                }

                if (! Schema::hasColumn('clinics', 'stripe_customer_id')) {
                    $table->string('stripe_customer_id')->nullable()->after('status');
                }

                if (! Schema::hasColumn('clinics', 'suspended_at')) {
                    $table->timestamp('suspended_at')->nullable()->after('stripe_customer_id');
                }
            });
        }

        if (! Schema::hasTable('backoffice_clinic_activities')) {
            Schema::create('backoffice_clinic_activities', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
                $table->foreignId('admin_user_id')->nullable()->constrained('admin_users')->nullOnDelete();
                $table->foreignId('target_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('event', 100);
                $table->string('result', 30)->default('success');
                $table->json('context')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['clinic_id', 'created_at']);
                $table->index(['event']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('backoffice_clinic_activities')) {
            Schema::drop('backoffice_clinic_activities');
        }

        if (! Schema::hasTable('clinics')) {
            return;
        }

        Schema::table('clinics', function (Blueprint $table): void {
            $dropColumns = [];

            foreach (['slug', 'plan', 'status', 'stripe_customer_id', 'suspended_at'] as $column) {
                if (Schema::hasColumn('clinics', $column)) {
                    $dropColumns[] = $column;
                }
            }

            if (! empty($dropColumns)) {
                $table->dropColumn($dropColumns);
            }
        });
    }
};
