<?php

declare(strict_types=1);

use App\Modules\Members\Support\AddressSplitter;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->unsignedInteger('nuliga_id')->nullable()->unique()->after('member_number');
            $table->string('gender', 1)->nullable()->after('birthdate');
            $table->string('street', 255)->nullable()->after('phone');
            $table->string('zip', 10)->nullable()->after('street');
            $table->string('city', 128)->nullable()->after('zip');
            $table->foreignId('fee_category_id')->nullable()->after('city')->constrained('fee_categories')->nullOnDelete();
        });

        foreach (DB::table('members')->whereNotNull('address')->get(['id', 'address']) as $member) {
            DB::table('members')->where('id', $member->id)->update(AddressSplitter::split($member->address));
        }

        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn('address');
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->string('address', 255)->nullable()->after('phone');
        });

        foreach (DB::table('members')->get(['id', 'street', 'zip', 'city']) as $member) {
            $place = trim("{$member->zip} {$member->city}");
            $address = trim(implode(', ', array_filter([$member->street, $place === '' ? null : $place])));
            DB::table('members')->where('id', $member->id)->update(['address' => $address === '' ? null : $address]);
        }

        Schema::table('members', function (Blueprint $table) {
            $table->dropUnique(['nuliga_id']);
        });

        Schema::table('members', function (Blueprint $table) {
            $table->dropConstrainedForeignId('fee_category_id');
            $table->dropColumn(['street', 'zip', 'city', 'gender', 'nuliga_id']);
        });
    }
};
