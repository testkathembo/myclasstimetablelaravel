use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ReplaceFacultyWithSchools extends Migration
{
    public function up()
    {
        if (Schema::hasColumn('users', 'faculty')) {
            Schema::table('users', function (Blueprint $table) {
                $table->renameColumn('faculty', 'schools');
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('users', 'schools')) {
            Schema::table('users', function (Blueprint $table) {
                $table->renameColumn('schools', 'faculty');
            });
        }
    }
}
