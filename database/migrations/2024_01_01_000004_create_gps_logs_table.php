<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Enable pg_partman extension for automatic partition management
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_partman');
        
        // Create parent table with partitioning
        DB::statement("
            CREATE TABLE gps_logs (
                id BIGSERIAL,
                asset_id BIGINT NOT NULL,
                latitude DECIMAL(10,8) NOT NULL,
                longitude DECIMAL(11,8) NOT NULL,
                speed DECIMAL(6,2) DEFAULT 0,
                ignition BOOLEAN DEFAULT FALSE,
                heading DECIMAL(5,2),
                altitude DECIMAL(8,2),
                satellites INTEGER,
                hdop DECIMAL(4,2),
                timestamp TIMESTAMP NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id, timestamp),
                FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE
            ) PARTITION BY RANGE (timestamp)
        ");

        // Create initial partitions (current month + next 2 months)
        $currentMonth = now()->startOfMonth();
        for ($i = 0; $i < 3; $i++) {
            $month = $currentMonth->copy()->addMonths($i);
            $tableName = 'gps_logs_' . $month->format('Y_m');
            $startDate = $month->format('Y-m-d');
            $endDate = $month->copy()->addMonth()->format('Y-m-d');
            
            DB::statement("
                CREATE TABLE {$tableName} PARTITION OF gps_logs
                FOR VALUES FROM ('{$startDate}') TO ('{$endDate}')
            ");
        }

        // Create indexes
        DB::statement('CREATE INDEX idx_gps_logs_asset_id ON gps_logs(asset_id)');
        DB::statement('CREATE INDEX idx_gps_logs_timestamp ON gps_logs(timestamp)');
        DB::statement('CREATE INDEX idx_gps_logs_asset_timestamp ON gps_logs(asset_id, timestamp)');
    }

    public function down(): void
    {
        Schema::dropIfExists('gps_logs');
    }
};