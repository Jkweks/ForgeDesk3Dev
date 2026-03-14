<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name')->nullable()->after('name');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('role')->default('viewer')->after('password')->comment('admin, manager, fabricator, viewer');
            $table->boolean('is_active')->default(true)->after('role');
            $table->timestamp('last_login_at')->nullable()->after('is_active');
            $table->softDeletes();
        });

        // Update existing users to split name into first_name/last_name
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement("
                UPDATE users
                SET
                    first_name = SPLIT_PART(name, ' ', 1),
                    last_name = REVERSE(SPLIT_PART(REVERSE(name), ' ', 1))
                WHERE first_name IS NULL
            ");
        } elseif ($driver === 'mysql') {
            DB::statement("
                UPDATE users
                SET
                    first_name = SUBSTRING_INDEX(name, ' ', 1),
                    last_name = SUBSTRING_INDEX(name, ' ', -1)
                WHERE first_name IS NULL
            ");
        } else {
            // SQLite - use simple substr and instr
            DB::statement("
                UPDATE users
                SET
                    first_name = CASE
                        WHEN INSTR(name, ' ') > 0 THEN SUBSTR(name, 1, INSTR(name, ' ') - 1)
                        ELSE name
                    END,
                    last_name = CASE
                        WHEN INSTR(name, ' ') > 0 THEN SUBSTR(name, INSTR(name, ' ') + 1)
                        ELSE NULL
                    END
                WHERE first_name IS NULL
            ");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'last_name', 'role', 'is_active', 'last_login_at', 'deleted_at']);
        });
    }
};
