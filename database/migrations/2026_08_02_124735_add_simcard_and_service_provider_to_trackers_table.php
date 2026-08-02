<?PHP

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trackers', function (Blueprint $table) {

            $table->string('simcard_number')->nullable();
            $table->string('service_provider')->nullable();

        });
    }

    public function down(): void
    {
        Schema::table('trackers', function (Blueprint $table) {

            $table->dropColumn([
                'simcard_number',
                'service_provider',
            ]);

        });
    }
};
