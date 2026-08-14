<?php

namespace Database\Seeders;

use App\Models\Endpoint;
use App\Models\Group;
use App\Models\Rule;
use Illuminate\Database\Seeder;

/**
 * Példaadat: Ügyfelek / ABC Kft. / Rendelések, egy szabállyal.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $customers = Group::firstOrCreate(
            ['parent_id' => null, 'slug' => 'ugyfelek'],
            ['name' => 'Ügyfelek', 'color' => '#2563eb']
        );

        $abc = Group::firstOrCreate(
            ['parent_id' => $customers->id, 'slug' => 'abc-kft'],
            ['name' => 'ABC Kft.', 'description' => 'Ügyfélkód: ABC123']
        );

        $orders = Endpoint::firstOrCreate(
            ['group_id' => $abc->id, 'slug' => 'rendelesek'],
            [
                'name' => 'Rendelések',
                'response_body' => '{"ok":true}',
                'response_content_type' => 'application/json',
            ]
        );

        Endpoint::firstOrCreate(
            ['group_id' => $abc->id, 'slug' => 'szamlazas'],
            ['name' => 'Számlázás']
        );

        Rule::firstOrCreate(
            ['endpoint_id' => $orders->id, 'name' => 'Nagy értékű rendelés – értesítés'],
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
                'name' => 'Értesítő levél',
                'config' => [
                    'to' => '{{ json.customer.email }}',
                    'subject' => 'Új rendelés: {{ json.order.id }}',
                    'body_html' => <<<'HTML'
                        <style>
                          .box { font-family: -apple-system, Segoe UI, Roboto, sans-serif; color:#0f172a }
                          .total { font-size: 20px; font-weight: 700; color:#2563eb }
                        </style>
                        <div class="box">
                          <h2>Köszönjük a rendelést, {{ json.customer.name|default('Kedves Ügyfél') }}!</h2>
                          <p>Azonosító: <strong>{{ json.order.id }}</strong></p>
                          <p class="total">{{ json.order.total|huf }}</p>
                          {{ json.order.items|table }}
                          <p style="color:#64748b;font-size:12px">Beérkezett: {{ meta.received_at_hu }}</p>
                        </div>
                        HTML,
                    'inline_css' => true,
                ],
            ]
        );

        $this->command?->info('Példa endpoint URL: '.$orders->fresh()->url());
    }
}
