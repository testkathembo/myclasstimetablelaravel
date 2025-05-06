use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('class_time_slots', function (Blueprint $table) {
            $table->integer('no')->nullable()->after('status'); // Add the 'no' column
        });
    }

    public function down(): void
    {
        Schema::table('class_time_slots', function (Blueprint $table) {
            $table->dropColumn('no'); // Remove the 'no' column
        });
    }
};
