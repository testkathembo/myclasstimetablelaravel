use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateCreditHoursInUnitsTable extends Migration
{
    public function up()
    {
        Schema::table('units', function (Blueprint $table) {
            $table->integer('credit_hours')->default(1)->change(); // Ensure credit_hours is an integer
        });
    }

    public function down()
    {
        Schema::table('units', function (Blueprint $table) {
            $table->string('credit_hours')->change(); // Revert changes if needed
        });
    }
}
