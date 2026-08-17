<?php

namespace Database\Seeders;

use App\Models\Endpoint;
use App\Models\Group;
use App\Models\Rule;
use Illuminate\Database\Seeder;

/**
 * Sample data: Customers / ACME Ltd. / Orders, with one rule attached.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $customers = Group::firstOrCreate(
            ['parent_id' => null, 'slug' => 'customers'],
            ['name' => 'Customers', 'color' => '#2563eb']
        );

        $acme = Group::firstOrCreate(
            ['parent_id' => $customers->id, 'slug' => 'acme'],
            ['name' => 'ACME Ltd.', 'description' => 'Customer code: ACME-1']
        );

        $orders = Endpoint::firstOrCreate(
            ['group_id' => $acme->id, 'slug' => 'orders'],
            [
                'name' => 'Orders',
                'response_body' => '{"ok":true}',
                'response_content_type' => 'application/json',
            ]
        );

        Endpoint::firstOrCreate(
            ['group_id' => $acme->id, 'slug' => 'invoicing'],
            ['name' => 'Invoicing']
        );

        Rule::firstOrCreate(
            ['endpoint_id' => $orders->id, 'name' => 'High-value order — notify'],
            [
                'conditions' => [
                    'type' => 'group',
                    'op' => 'and',
                    'children' => [
                        ['type' => 'cond', 'source' => 'json', 'path' => 'event', 'operator' => 'equals', 'value' => 'order.paid'],
                        ['type' => 'cond', 'source' => 'json', 'path' => 'order.total', 'operator' => 'gt', 'value' => '10000'],
                    ],
                ],
            ]
        )->actions()->firstOrCreate(
            ['type' => 'email'],
            [
                'name' => 'Notification e-mail',
                'config' => [
                    'to' => '{{ json.customer.email }}',
                    'subject' => 'New order: {{ json.order.id }}',
                    'body_html' => <<<'HTML'
                        <style>
                          .box { font-family: -apple-system, Segoe UI, Roboto, sans-serif; color:#0f172a }
                          .total { font-size: 20px; font-weight: 700; color:#2563eb }
                        </style>
                        <div class="box">
                          <h2>Thanks for your order, {{ json.customer.name|default('there') }}!</h2>
                          <p>Reference: <strong>{{ json.order.id }}</strong></p>
                          <p class="total">{{ json.order.total|money }}</p>
                          {{ json.order.items|table }}
                          <p style="color:#64748b;font-size:12px">Received: {{ meta.received_at_local }}</p>
                        </div>
                        HTML,
                    'inline_css' => true,
                ],
            ]
        );

        $this->command?->info('Sample endpoint URL: '.$orders->fresh()->url());
    }
}
