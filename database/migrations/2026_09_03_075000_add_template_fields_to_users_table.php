<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'iam_id')) {
                $table->string('iam_id')->nullable()->unique()->after('id');
            }

            if (! Schema::hasColumn('users', 'nip')) {
                $table->string('nip', 50)->nullable()->unique()->after('name');
            }

            if (! Schema::hasColumn('users', 'avatar')) {
                $table->string('avatar')->nullable()->after('password');
            }

            if (! Schema::hasColumn('users', 'status')) {
                $table->enum('status', ['active', 'inactive', 'suspended'])
                    ->default('active')
                    ->after('avatar');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'status')) {
                $table->dropColumn('status');
            }

            if (Schema::hasColumn('users', 'avatar')) {
                $table->dropColumn('avatar');
            }

            if (Schema::hasColumn('users', 'nip')) {
                $table->dropColumn('nip');
            }

            if (Schema::hasColumn('users', 'iam_id')) {
                $table->dropColumn('iam_id');
            }
        });
    }
};
