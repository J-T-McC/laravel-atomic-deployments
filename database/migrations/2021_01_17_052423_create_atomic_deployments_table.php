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
            $table->string('deployment_path')->index();
            $table->string('deployment_link');
            $table->unsignedTinyInteger('deployment_status');
            $table->timestamps();
            $table->softDeletes();
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
