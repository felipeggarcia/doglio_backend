<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FixOrderDatesSeeder extends Seeder
{
    public function run(): void
    {
        $orders = DB::table('orders')->orderBy('id')->get();

        foreach ($orders as $order) {
            $histories = DB::table('order_status_history')
                ->where('order_id', $order->id)
                ->orderBy('id')
                ->get();

            if ($histories->isEmpty()) continue;

            // Randomiza o horário do created_at (mantém a data, varia hora/minuto)
            // para que pedidos do mesmo dia não tenham exatamente o mesmo timestamp
            $createdAt = Carbon::parse($order->created_at)
                ->setTime(rand(7, 22), rand(0, 59), rand(0, 59));

            DB::table('orders')
                ->where('id', $order->id)
                ->update(['created_at' => $createdAt]);

            $numSteps = $histories->count();

            // Duração total esperada do fluxo de acordo com o status final
            $flowHours = match ($order->status) {
                'delivered'        => rand(120, 168), // 5–7 dias
                'out_for_delivery' => rand(72,  120), // 3–5 dias
                'preparing'        => rand(48,  72),  // 2–3 dias
                'confirmed'        => rand(12,  36),  // 12–36 horas
                'cancelled'        => rand(6,   48),  // 6–48 horas
                default            => rand(1,   4),   // pending: poucos minutos/horas
            };

            $endDate = $createdAt->copy()->addHours($flowHours);
            if ($endDate->isFuture()) {
                $endDate = now()->subMinutes(rand(10, 60));
            }
            // garante que endDate > createdAt
            if (!$endDate->gt($createdAt)) {
                $endDate = $createdAt->copy()->addHours(1);
                if ($endDate->isFuture()) {
                    $endDate = now()->subMinutes(10);
                }
            }

            $totalMinutes = max(1, $createdAt->diffInMinutes($endDate));

            $stepDates = [];
            foreach ($histories as $j => $_) {
                if ($numSteps === 1) {
                    $stepDate = $createdAt->copy();
                } else {
                    $progress = $j / ($numSteps - 1);
                    $stepDate = $createdAt->copy()->addMinutes((int) ($progress * $totalMinutes));
                }
                if ($stepDate->isFuture()) {
                    $stepDate = now()->subMinutes(rand(5, 30));
                }
                $stepDates[] = $stepDate;
            }

            foreach ($histories as $j => $history) {
                DB::table('order_status_history')
                    ->where('id', $history->id)
                    ->update(['created_at' => $stepDates[$j]]);
            }

            // updated_at do pedido = data da última transição de status
            $lastStepDate = end($stepDates);
            DB::table('orders')
                ->where('id', $order->id)
                ->update(['updated_at' => $lastStepDate]);

            // paid_at = data da etapa "confirmed" + alguns minutos, ou etapa 0 se só 1 step
            $confirmedStep = $histories->firstWhere('status', 'confirmed');
            $paidBase = $confirmedStep
                ? Carbon::parse($confirmedStep->created_at)
                : $createdAt->copy();

            $paidAt = $paidBase->copy()->addMinutes(rand(5, 45));
            if ($paidAt->isFuture()) {
                $paidAt = now()->subMinutes(rand(5, 30));
            }

            DB::table('payments')
                ->where('order_id', $order->id)
                ->whereNotNull('paid_at')
                ->update(['paid_at' => $paidAt]);
        }
    }
}
