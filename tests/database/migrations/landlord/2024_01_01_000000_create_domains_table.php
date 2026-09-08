<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up()
    {
        if (! Schema::connection('landlord')->hasTable('domains')) {
            Schema::connection('landlord')->create('domains', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')
                    ->constrained('tenants')
                    ->cascadeOnDelete();
                $table->string('domain')->unique();
                $table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::connection('landlord')->dropIfExists('domains');
    }
};
