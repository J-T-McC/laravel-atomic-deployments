<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAtomicDeploymentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('atomic_deployments', function (Blueprint $table) {
            $table->id();
            $table->string('commit_hash')->index();
            $table->string('build_path');
            $table->string('deployment_path');
            $table->string('web_root');
            $table->unsignedTinyInteger('deployment_status')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('atomic_deployments');
    }
}
