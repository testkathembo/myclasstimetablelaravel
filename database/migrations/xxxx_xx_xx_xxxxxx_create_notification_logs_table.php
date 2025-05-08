use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateNotificationLogsTable extends Migration
{
    public function up(): void
    {
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->string('notification_type');
            $table->string('notifiable_type');
            $table->unsignedBigInteger('notifiable_id');
            $table->string('channel');
            $table->boolean('success')->default(false);
            $table->text('error_message')->nullable();
            $table->json('data')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
}
