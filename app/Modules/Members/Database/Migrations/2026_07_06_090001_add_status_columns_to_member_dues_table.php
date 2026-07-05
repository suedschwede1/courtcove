<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_dues', function (Blueprint $table) {
            $table->string('status')->default('ausstehend')->after('amount');
            $table->decimal('teilbetrag', 8, 2)->nullable()->after('status');
            $table->text('kommentar')->nullable()->after('teilbetrag');
            $table->foreignId('bezahlt_bis_fiscal_year_id')->nullable()->after('kommentar')
                ->constrained('fiscal_years')->nullOnDelete();
            $table->date('faellig_am')->nullable()->after('bezahlt_bis_fiscal_year_id');
        });
    }

    public function down(): void
    {
        Schema::table('member_dues', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bezahlt_bis_fiscal_year_id');
            $table->dropColumn(['status', 'teilbetrag', 'kommentar', 'faellig_am']);
        });
    }
};
