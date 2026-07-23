<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Audit trail for trader-profit "ترحيل" (post) and "سحب" (withdraw) operations.
     * The money movement itself always lives in journal_entries/journal_lines
     * (double entry, source of truth for balances); this table only tracks
     * which trader/period was posted and links back to its journal entry so the
     * UI can list history and void entries without recomputing anything.
     */
    public function up(): void
    {
        if (!Schema::hasTable('trader_profit_entries')) {
            Schema::create('trader_profit_entries', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('owner_id')->index();
                $table->enum('type', ['post', 'withdraw'])->index();
                $table->unsignedBigInteger('client_id')->nullable()->index()->comment('trader — only set for type=post');
                $table->date('period_from')->nullable();
                $table->date('period_to')->nullable();
                $table->decimal('amount', 18, 2);
                $table->string('currency', 10)->default('$');
                $table->string('memo', 500)->nullable();
                $table->unsignedBigInteger('journal_entry_id')->nullable()->index();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['owner_id', 'type', 'client_id']);

                if (Schema::hasTable('journal_entries')) {
                    $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->nullOnDelete();
                }
                if (Schema::hasTable('users')) {
                    $table->foreign('client_id')->references('id')->on('users')->nullOnDelete();
                    $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('trader_profit_entries');
    }
};
