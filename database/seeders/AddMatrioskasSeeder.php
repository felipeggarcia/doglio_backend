<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Seeder;

class AddMatrioskasSeeder extends Seeder
{
    public function run(): void
    {
        $catUkr   = Category::where('slug', 'artesanato-ucraniano')->firstOrFail();
        $catDecor = Category::where('slug', 'casa-e-decoracao')->firstOrFail();

        $catMatrioska = Category::updateOrCreate(
            ['name' => 'Matrioskas'],
            ['slug' => 'matrioskas', 'is_highlighted' => true, 'is_active' => true]
        );

        $m1 = Product::updateOrCreate(
            ['name' => 'Conjunto de Matrioskas Clássicas — Estampa Floral de Rosas (5 Peças)'],
            [
                'description'    => 'Encanto e tradição em cada detalhe. Este clássico conjunto de Matrioskas é composto por 5 bonecas de madeira que se encaixam perfeitamente umas dentro das outras. Feitas e pintadas à mão, apresentam um fundo escuro elegante que destaca a belíssima estampa de rosas vermelhas e folhas verdes, além de delicados detalhes dourados no lenço. Uma peça cheia de significado, perfeita para colecionadores ou para trazer um toque folclórico à decoração.',
                'price'          => 145.00,
                'stock_quantity' => 7,
                'is_highlighted' => true,
                'is_active'      => true,
            ]
        );

        $m2 = Product::updateOrCreate(
            ['name' => 'Conjunto de Matrioskas Vermelhas — Detalhe Flor Azul (5 Peças)'],
            [
                'description'    => 'Vivacidade e carisma para o seu ambiente. Este lindo jogo com 5 Matrioskas artesanais destaca-se pela sua cor vermelha vibrante e pelo design expressivo com olhos marcantes e cabelos loiros. A boneca principal traz no avental uma linda flor estilizada em tons de azul e branco, enquanto as menores seguem o padrão com delicados arabescos. Ideal para presentear, decorar quartos infantis, estantes ou mesas de centro com muita personalidade.',
                'price'          => 135.00,
                'stock_quantity' => 6,
                'is_highlighted' => false,
                'is_active'      => true,
            ]
        );

        $m1->categories()->syncWithoutDetaching([$catMatrioska->id, $catDecor->id, $catUkr->id]);
        $m2->categories()->syncWithoutDetaching([$catMatrioska->id, $catDecor->id, $catUkr->id]);

        $admin = User::where('email', 'nataliak@gmail.com')->first();

        foreach ([
            [$m1, 7, 'Estoque inicial — Matrioskas Clássicas Floral de Rosas'],
            [$m2, 6, 'Estoque inicial — Matrioskas Vermelhas Flor Azul'],
        ] as [$product, $qty, $note]) {
            StockMovement::create([
                'product_id'   => $product->id,
                'type'         => 'in',
                'quantity'     => $qty,
                'stock_before' => 0,
                'reason'       => 'purchase',
                'user_id'      => $admin?->id,
                'notes'        => $note,
                'created_at'   => now(),
            ]);
        }
    }
}
