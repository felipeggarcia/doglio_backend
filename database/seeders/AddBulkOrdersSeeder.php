<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class AddBulkOrdersSeeder extends Seeder
{
    private static array $cities = [
        ['city' => 'São Paulo',      'state' => 'SP', 'zip_code' => '01310100', 'street' => 'Av. Paulista',           'district' => 'Bela Vista'],
        ['city' => 'Rio de Janeiro', 'state' => 'RJ', 'zip_code' => '22041001', 'street' => 'Av. Atlântica',          'district' => 'Copacabana'],
        ['city' => 'Belo Horizonte', 'state' => 'MG', 'zip_code' => '30112010', 'street' => 'Av. Afonso Pena',        'district' => 'Centro'],
        ['city' => 'Curitiba',       'state' => 'PR', 'zip_code' => '80020000', 'street' => 'Rua XV de Novembro',     'district' => 'Centro'],
        ['city' => 'Porto Alegre',   'state' => 'RS', 'zip_code' => '90010080', 'street' => 'Av. Borges de Medeiros', 'district' => 'Centro'],
        ['city' => 'Salvador',       'state' => 'BA', 'zip_code' => '40020010', 'street' => 'Av. Sete de Setembro',   'district' => 'Vitória'],
        ['city' => 'Florianópolis',  'state' => 'SC', 'zip_code' => '88010020', 'street' => 'Rua Felipe Schmidt',     'district' => 'Centro'],
        ['city' => 'Fortaleza',      'state' => 'CE', 'zip_code' => '60115191', 'street' => 'Av. Beira Mar',          'district' => 'Meireles'],
        ['city' => 'Recife',         'state' => 'PE', 'zip_code' => '50010230', 'street' => 'Av. Dantas Barreto',     'district' => 'Santo Antônio'],
        ['city' => 'Manaus',         'state' => 'AM', 'zip_code' => '69010020', 'street' => 'Av. Eduardo Ribeiro',    'district' => 'Centro'],
    ];

    private static array $statusFlow = [
        'pending'          => [['status' => 'pending']],
        'confirmed'        => [['status' => 'pending'], ['status' => 'confirmed']],
        'preparing'        => [['status' => 'pending'], ['status' => 'confirmed'], ['status' => 'preparing']],
        'out_for_delivery' => [['status' => 'pending'], ['status' => 'confirmed'], ['status' => 'preparing'], ['status' => 'out_for_delivery']],
        'delivered'        => [['status' => 'pending'], ['status' => 'confirmed'], ['status' => 'preparing'], ['status' => 'out_for_delivery'], ['status' => 'delivered']],
        'cancelled'        => [['status' => 'pending'], ['status' => 'cancelled', 'notes' => 'Cancelado pelo cliente.']],
    ];

    /**
     * Calcula as datas de cada etapa de status usando intervalos em horas,
     * garantindo que todas as datas fiquem no passado.
     */
    private function buildStepDates(Carbon $createdAt, string $finalStatus): array
    {
        // Horas entre cada transição de status (valores realistas)
        $hoursPerTransition = match ($finalStatus) {
            'pending'          => [],
            'confirmed'        => [rand(6,  20)],
            'preparing'        => [rand(6,  20), rand(20, 48)],
            'out_for_delivery' => [rand(6,  20), rand(20, 48), rand(20, 72)],
            'delivered'        => [rand(6,  20), rand(20, 48), rand(20, 72), rand(12, 24)],
            'cancelled'        => [rand(4,  48)],
        };

        $dates   = [$createdAt->copy()];
        $current = $createdAt->copy();

        foreach ($hoursPerTransition as $hours) {
            $current = $current->copy()->addHours($hours);
            if ($current->isFuture()) {
                $current = now()->subMinutes(rand(15, 90));
            }
            $dates[] = $current->copy();
        }

        return $dates;
    }

    public function run(): void
    {
        $protected = ['nataliak@gmail.com', 'ana.tavares@hotmail.com', 'felipegogarcia@gmail.com'];

        $customers = User::where('role', 'customer')
            ->whereNotIn('email', $protected)
            ->get();

        $products = Product::where('is_active', true)->get();
        $methods  = PaymentMethod::where('is_active', true)->get();

        $statusPool = array_merge(
            array_fill(0, 5,  'pending'),
            array_fill(0, 8,  'confirmed'),
            array_fill(0, 7,  'preparing'),
            array_fill(0, 5,  'out_for_delivery'),
            array_fill(0, 11, 'delivered'),
            array_fill(0, 4,  'cancelled'),
        );
        shuffle($statusPool);

        foreach ($statusPool as $i => $finalStatus) {
            $customer = $customers[$i % $customers->count()];
            $method   = $methods->random();
            $isPickup = rand(0, 4) === 0;

            // daysAgo mínimo garante tempo suficiente para todas as transições
            $minDaysAgo = match ($finalStatus) {
                'delivered'        => 8,
                'out_for_delivery' => 6,
                'preparing'        => 4,
                'confirmed'        => 2,
                'cancelled'        => 2,
                'pending'          => 0,
            };
            $daysAgo   = rand($minDaysAgo, 90);
            $createdAt = now()->subDays($daysAgo)->subHours(rand(0, 12));

            $stepDates = $this->buildStepDates($createdAt, $finalStatus);
            $updatedAt = end($stepDates);

            $qty      = rand(1, min(3, $products->count()));
            $selected = $products->random($qty);
            if (!($selected instanceof \Illuminate\Support\Collection)) {
                $selected = collect([$selected]);
            }

            $shippingFields = array_fill_keys([
                'shipping_street', 'shipping_number', 'shipping_complement',
                'shipping_district', 'shipping_city', 'shipping_state', 'shipping_zip_code',
            ], null);

            if (!$isPickup) {
                $loc = self::$cities[array_rand(self::$cities)];
                $shippingFields = [
                    'shipping_street'     => $loc['street'],
                    'shipping_number'     => (string) rand(10, 3000),
                    'shipping_complement' => null,
                    'shipping_district'   => $loc['district'],
                    'shipping_city'       => $loc['city'],
                    'shipping_state'      => $loc['state'],
                    'shipping_zip_code'   => $loc['zip_code'],
                ];
            }

            $total = 0;
            $items = $selected->map(function ($product) use (&$total) {
                $qty    = rand(1, 2);
                $total += $product->price * $qty;
                return ['product_id' => $product->id, 'quantity' => $qty, 'unit_price' => $product->price];
            });

            $order = Order::create(array_merge([
                'user_id'       => $customer->id,
                'address_id'    => null,
                'status'        => $finalStatus,
                'total_amount'  => round($total, 2),
                'delivery_type' => $isPickup ? 'pickup' : 'delivery',
                'created_at'    => $createdAt,
                'updated_at'    => $updatedAt,
            ], $shippingFields));

            foreach ($items as $item) {
                OrderItem::create(array_merge(['order_id' => $order->id], $item));
            }

            foreach (self::$statusFlow[$finalStatus] as $j => $entry) {
                OrderStatusHistory::create([
                    'order_id'   => $order->id,
                    'status'     => $entry['status'],
                    'notes'      => $entry['notes'] ?? null,
                    'created_at' => $stepDates[$j],
                    'updated_at' => $stepDates[$j],
                ]);
            }

            $paymentStatus = match ($finalStatus) {
                'delivered', 'out_for_delivery', 'preparing', 'confirmed' => 'paid',
                'pending'   => (rand(0, 1) ? 'paid' : 'pending'),
                'cancelled' => (rand(0, 1) ? 'refunded' : 'pending'),
            };

            // paid_at = data da confirmação (stepDates[1]) ou criação se só 1 step
            $paidAt = null;
            if (in_array($paymentStatus, ['paid', 'refunded'])) {
                $paidAt = isset($stepDates[1])
                    ? $stepDates[1]->copy()->addMinutes(rand(5, 30))
                    : $createdAt->copy()->addMinutes(rand(30, 120));
                if ($paidAt->isFuture()) {
                    $paidAt = now()->subMinutes(rand(5, 30));
                }
            }

            Payment::create([
                'order_id'          => $order->id,
                'payment_method_id' => $method->id,
                'status'            => $paymentStatus,
                'amount'            => round($total, 2),
                'paid_at'           => $paidAt,
            ]);
        }
    }
}
