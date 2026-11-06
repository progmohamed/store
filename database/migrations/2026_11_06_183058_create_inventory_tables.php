<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medicines', function (Blueprint $t): void {
            $t->id();
            $t->string('name')->unique();
            $t->unsignedInteger('minimum_stock')->default(0);
            $t->timestamps();
        });
        Schema::create('receipts', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('medicine_id')->constrained();
            $t->uuid('request_id')->unique();
            $t->date('received_on');
            $t->string('kind');
            $t->unsignedInteger('quantity');
            $t->unsignedInteger('remaining');
            $t->unsignedBigInteger('unit_cost');
            $t->string('supplier')->nullable();
            $t->timestamps();
        });
        Schema::create('issues', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('medicine_id')->constrained();
            $t->string('client');
            $t->uuid('request_id');
            $t->string('destination_id');
            $t->string('destination');
            $t->unsignedInteger('quantity');
            $t->unsignedBigInteger('total_cost');
            $t->timestamp('returned_at')->nullable();
            $t->timestamps();
            $t->unique(['client', 'request_id']);
        });
        Schema::create('allocations', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('issue_id')->constrained();
            $t->foreignId('receipt_id')->constrained();
            $t->unsignedInteger('quantity');
            $t->unsignedBigInteger('unit_cost');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('allocations');
        Schema::dropIfExists('issues');
        Schema::dropIfExists('receipts');
        Schema::dropIfExists('medicines');
    }
};
