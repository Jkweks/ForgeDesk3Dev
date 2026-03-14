<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\JobReservation;
use Carbon\Carbon;

class JobReservationSeeder extends Seeder
{
    public function run(): void
    {
        // Get products by SKU
        $products = Product::whereIn('sku', [
            'SP-2500-BL',
            'AL-1820-RAW',
            'DH-3000-SS',
            'PT-500-PW',
            'FP-250-CH',
            'BR-800-BR',
        ])->get()->keyBy('sku');

        // SP-2500-BL: Active reservations for upcoming jobs
        if ($products->has('SP-2500-BL')) {
            $product = $products->get('SP-2500-BL');

            // Active reservation - due soon
            JobReservation::create([
                'product_id' => $product->id,
                'job_number' => 'JOB-2024-001',
                'job_name' => 'ABC Manufacturing Project',
                'quantity_reserved' => 50,
                'quantity_fulfilled' => 0,
                'reserved_date' => Carbon::now(),
                'required_date' => Carbon::now()->addDays(5),
                'status' => 'active',
                'notes' => 'Priority customer - due soon',
            ]);

            // Active reservation - upcoming
            JobReservation::create([
                'product_id' => $product->id,
                'job_number' => 'JOB-2024-015',
                'job_name' => 'XYZ Industries Project',
                'quantity_reserved' => 70,
                'quantity_fulfilled' => 0,
                'reserved_date' => Carbon::now(),
                'required_date' => Carbon::now()->addDays(14),
                'status' => 'active',
                'notes' => 'Standard lead time',
            ]);
        }

        // AL-1820-RAW: Mix of statuses
        if ($products->has('AL-1820-RAW')) {
            $product = $products->get('AL-1820-RAW');

            // Partially fulfilled reservation
            JobReservation::create([
                'product_id' => $product->id,
                'job_number' => 'JOB-2024-008',
                'job_name' => 'BuildRight Construction',
                'quantity_reserved' => 50,
                'quantity_fulfilled' => 30,
                'reserved_date' => Carbon::now()->subDays(7),
                'required_date' => Carbon::now()->addDays(7),
                'status' => 'partially_fulfilled',
                'notes' => 'First shipment sent, remainder pending',
            ]);

            // Completed/fulfilled reservation
            JobReservation::create([
                'product_id' => $product->id,
                'job_number' => 'JOB-2023-095',
                'job_name' => 'Metro Builders',
                'quantity_reserved' => 25,
                'quantity_fulfilled' => 25,
                'reserved_date' => Carbon::now()->subDays(8),
                'required_date' => Carbon::now()->subDays(3),
                'status' => 'fulfilled',
                'notes' => 'Completed on time',
            ]);
        }

        // DH-3000-SS: Critical - overdue reservation
        if ($products->has('DH-3000-SS')) {
            $product = $products->get('DH-3000-SS');

            // OVERDUE active reservation
            JobReservation::create([
                'product_id' => $product->id,
                'job_number' => 'JOB-2024-003',
                'job_name' => 'Premier Doors Inc',
                'quantity_reserved' => 50,
                'quantity_fulfilled' => 0,
                'reserved_date' => Carbon::now(),
                'required_date' => Carbon::now()->subDays(2), // OVERDUE!
                'status' => 'active',
                'notes' => 'URGENT - Customer waiting, stock delayed',
            ]);
        }

        // PT-500-PW: Multiple active reservations straining low stock
        if ($products->has('PT-500-PW')) {
            $product = $products->get('PT-500-PW');

            JobReservation::create([
                'product_id' => $product->id,
                'job_number' => 'JOB-2024-012',
                'job_name' => 'Industrial Coatings Co',
                'quantity_reserved' => 10,
                'quantity_fulfilled' => 0,
                'reserved_date' => Carbon::now(),
                'required_date' => Carbon::now()->addDays(3),
                'status' => 'active',
                'notes' => 'Critical stock shortage',
            ]);
        }

        // FP-250-CH: Large projects with reservations
        if ($products->has('FP-250-CH')) {
            $product = $products->get('FP-250-CH');

            // Active large reservation
            JobReservation::create([
                'product_id' => $product->id,
                'job_number' => 'JOB-2024-020',
                'job_name' => 'MegaConstruct LLC',
                'quantity_reserved' => 150,
                'quantity_fulfilled' => 0,
                'reserved_date' => Carbon::now(),
                'required_date' => Carbon::now()->addDays(21),
                'status' => 'active',
                'notes' => 'Large commercial project - Phase 1',
            ]);

            // Partially fulfilled
            JobReservation::create([
                'product_id' => $product->id,
                'job_number' => 'JOB-2024-018',
                'job_name' => 'Urban Development Group',
                'quantity_reserved' => 100,
                'quantity_fulfilled' => 50,
                'reserved_date' => Carbon::now()->subDays(10),
                'required_date' => Carbon::now()->addDays(10),
                'status' => 'partially_fulfilled',
                'notes' => 'Delivering in batches',
            ]);

            // Cancelled reservation
            JobReservation::create([
                'product_id' => $product->id,
                'job_number' => 'JOB-2024-005',
                'job_name' => 'City Center Projects',
                'quantity_reserved' => 75,
                'quantity_fulfilled' => 0,
                'reserved_date' => Carbon::now(),
                'required_date' => Carbon::now()->addDays(15),
                'status' => 'cancelled',
                'notes' => 'Customer cancelled project - stock released',
            ]);
        }

        // BR-800-BR: Multiple small reservations
        if ($products->has('BR-800-BR')) {
            $product = $products->get('BR-800-BR');

            JobReservation::create([
                'product_id' => $product->id,
                'job_number' => 'JOB-2024-022',
                'job_name' => 'FastFrame Systems',
                'quantity_reserved' => 20,
                'quantity_fulfilled' => 0,
                'reserved_date' => Carbon::now(),
                'required_date' => Carbon::now()->addDays(8),
                'status' => 'active',
                'notes' => 'Standard order',
            ]);

            JobReservation::create([
                'product_id' => $product->id,
                'job_number' => 'JOB-2024-024',
                'job_name' => 'Assembly Masters',
                'quantity_reserved' => 20,
                'quantity_fulfilled' => 0,
                'reserved_date' => Carbon::now(),
                'required_date' => Carbon::now()->addDays(12),
                'status' => 'active',
                'notes' => 'Repeat customer',
            ]);

            // Fulfilled reservation
            JobReservation::create([
                'product_id' => $product->id,
                'job_number' => 'JOB-2023-088',
                'job_name' => 'QuickBuild LLC',
                'quantity_reserved' => 15,
                'quantity_fulfilled' => 15,
                'reserved_date' => Carbon::now()->subDays(10),
                'required_date' => Carbon::now()->subDays(5),
                'status' => 'fulfilled',
                'notes' => 'Delivered early',
            ]);
        }
    }
}
