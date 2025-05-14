use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('units', function (Blueprint $table) {
            $table->unsignedBigInteger('semester_id')->nullable()->change(); // Make semester_id nullable
        });
    }

    public function down()
    {
        Schema::table('units', function (Blueprint $table) {
            $table->unsignedBigInteger('semester_id')->nullable(false)->change(); // Revert to NOT NULL
        });
    }
};
