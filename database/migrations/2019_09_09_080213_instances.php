<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class Instances extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('MoodleDatabases', function (Blueprint $table) {
            $table->bigIncrements('databasesID');
	    $table->string("CN")->unique();
	    $table->string("rpIdmOrgShortName");
	    $table->string("shortname")->index();
	    $table->string("dbname")->unique();
	    $table->string("dbuser");
	    $table->string("dbpass");
            $table->timestamps();
        });

        Schema::create('moodleApi_log', function (Blueprint $table) {
            $table->bigIncrements('id');
	    $table->string("datum");
	    $table->string("funktion");
	    $table->string("ip");
	    $table->text("statusInfo");
	    $table->text("statusError");
	    $table->text("statusSuccess");
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
        Schema::dropIfExists('MoodleDatabases');
        Schema::dropIfExists('moodleApi_log');
    }
}
