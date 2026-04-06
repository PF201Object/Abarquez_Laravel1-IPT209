<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('applicants', function (Blueprint $table) {
            $table->unsignedBigInteger('scholarship_id')->nullable()->after('year_level');
            $table->date('date_applied')->nullable()->after('scholarship_id');
            
            // Add foreign key constraint
            $table->foreign('scholarship_id')
                  ->references('id')
                  ->on('scholarships')
                  ->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('applicants', function (Blueprint $table) {
            $table->dropForeign(['scholarship_id']);
            $table->dropColumn(['scholarship_id', 'date_applied']);
        });
    }
};