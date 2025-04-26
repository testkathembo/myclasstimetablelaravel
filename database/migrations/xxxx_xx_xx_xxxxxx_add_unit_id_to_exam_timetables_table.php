use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddUnitIdToExamTimetablesTable extends Migration
{
    public function up()
    {
        Schema::table('exam_timetables', function (Blueprint $table) {
            $table->unsignedBigInteger('unit_id')->nullable()->after('semester_id');
            $table->foreign('unit_id')->references('id')->on('units')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::table('exam_timetables', function (Blueprint $table) {
            $table->dropForeign(['unit_id']);
            $table->dropColumn('unit_id');
        });
    }
}
