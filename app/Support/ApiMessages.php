<?php

namespace App\Support;

/**
 * Strings de resposta centralizadas da API.
 * Todas as mensagens de sucesso e erro ficam aqui.
 */
final class ApiMessages
{
    // -------------------------------------------------------------------------
    // Auth
    // -------------------------------------------------------------------------
    const AUTH_REGISTERED          = 'Usuário registrado com sucesso.';
    const AUTH_LOGIN               = 'Login realizado com sucesso.';
    const AUTH_LOGOUT              = 'Logout realizado com sucesso.';
    const AUTH_INVALID_CREDENTIALS = 'E-mail ou senha inválidos.';
    const AUTH_ACCOUNT_INACTIVE    = 'Sua conta foi desativada. Entre em contato com o suporte.';

    // -------------------------------------------------------------------------
    // Produto
    // -------------------------------------------------------------------------
    const PRODUCT_CREATED          = 'Produto criado com sucesso.';
    const PRODUCT_UPDATED          = 'Produto atualizado com sucesso.';
    const PRODUCT_DELETED          = 'Produto removido com sucesso.';
    const PRODUCT_NOT_FOUND        = 'Produto não encontrado.';
    const PRODUCT_IMAGE_LIMIT      = 'Limite de imagens excedido. Máximo de 6 imagens por produto.';
    const PRODUCT_INSUFFICIENT_STOCK = 'Estoque insuficiente para esta saída.';

    // -------------------------------------------------------------------------
    // Categoria
    // -------------------------------------------------------------------------
    const CATEGORY_CREATED         = 'Categoria criada com sucesso.';
    const CATEGORY_UPDATED         = 'Categoria atualizada com sucesso.';
    const CATEGORY_DELETED         = 'Categoria removida com sucesso.';

    // -------------------------------------------------------------------------
    // Carrinho
    // -------------------------------------------------------------------------
    const CART_SYNCED              = 'Carrinho sincronizado com sucesso.';
    const CART_CLEARED             = 'Carrinho limpo com sucesso.';
    const CART_VALID               = 'Carrinho válido.';
    const CART_HAS_CHANGES         = 'O carrinho possui alterações.';
    const CART_EMPTY               = 'Seu carrinho está vazio.';
    const CART_INVALID_PRODUCT_IDS = 'IDs de produto inválidos.';
    const CART_PRODUCTS_NOT_FOUND  = 'Um ou mais produtos não foram encontrados.';

    // -------------------------------------------------------------------------
    // Pedido
    // -------------------------------------------------------------------------
    const ORDER_CREATED            = 'Pedido realizado com sucesso.';
    const ORDER_STATUS_UPDATED     = 'Status do pedido atualizado com sucesso.';
    const ORDER_INVALID_PAYMENT    = 'Método de pagamento inválido ou inativo.';
    const ORDER_INSUFFICIENT_STOCK = 'Estoque insuficiente para finalizar o pedido.';
    const ORDER_ADDRESS_NOT_FOUND  = 'Endereço não encontrado.';

    // -------------------------------------------------------------------------
    // Promoção
    // -------------------------------------------------------------------------
    const PROMOTION_CREATED            = 'Promoção criada com sucesso.';
    const PROMOTION_UPDATED            = 'Promoção atualizada com sucesso.';
    const PROMOTION_DELETED            = 'Promoção removida com sucesso.';
    const PROMOTION_PRODUCTS_ATTACHED  = 'Produtos vinculados à promoção com sucesso.';
    const PROMOTION_PRODUCTS_DETACHED  = 'Produtos desvinculados da promoção com sucesso.';
    const PROMOTION_PERCENTAGE_LIMIT   = 'Desconto percentual não pode exceder 100%.';
    const PROMOTION_PRODUCT_CONFLICT   = 'Um ou mais produtos já possuem promoção ativa: :products.';

    // -------------------------------------------------------------------------
    // Favorito
    // -------------------------------------------------------------------------
    const FAVORITE_ADDED           = 'Produto adicionado aos favoritos.';
    const FAVORITE_REMOVED         = 'Produto removido dos favoritos.';
    const FAVORITE_ALREADY_EXISTS  = 'Produto já está nos seus favoritos.';
    const FAVORITE_NOTIFY_UPDATED  = 'Preferência de notificação atualizada.';

    // -------------------------------------------------------------------------
    // Endereço
    // -------------------------------------------------------------------------
    const ADDRESS_CREATED          = 'Endereço criado com sucesso.';
    const ADDRESS_UPDATED          = 'Endereço atualizado com sucesso.';
    const ADDRESS_DELETED          = 'Endereço removido com sucesso.';
    const ADDRESS_PRIMARY_SET      = 'Endereço principal definido com sucesso.';

    // -------------------------------------------------------------------------
    // Push Token
    // -------------------------------------------------------------------------
    const PUSH_TOKEN_REGISTERED    = 'Token de push registrado com sucesso.';
    const PUSH_TOKEN_REMOVED       = 'Token de push removido com sucesso.';

    // -------------------------------------------------------------------------
    // Avaliação
    // -------------------------------------------------------------------------
    const REVIEW_SUBMITTED         = 'Avaliação enviada com sucesso.';
    const REVIEW_DELETED           = 'Avaliação removida com sucesso.';
    const REVIEW_PURCHASE_REQUIRED = 'Você só pode avaliar um produto após recebê-lo.';
    const REVIEW_ALREADY_SUBMITTED = 'Você já avaliou este produto.';

    // -------------------------------------------------------------------------
    // Estoque
    // -------------------------------------------------------------------------
    const STOCK_MOVEMENT_CREATED   = 'Movimentação de estoque registrada com sucesso.';
    const STOCK_NO_CHANGE          = 'Estoque já está no valor indicado, nenhuma alteração necessária.';

    // -------------------------------------------------------------------------
    // Usuário
    // -------------------------------------------------------------------------
    const USER_CREATED             = 'Usuário criado com sucesso.';
    const USER_UPDATED             = 'Usuário atualizado com sucesso.';
    const USER_DELETED             = 'Usuário removido com sucesso.';

    // -------------------------------------------------------------------------
    // Genérico
    // -------------------------------------------------------------------------
    const VALIDATION_FAILED        = 'Erro de validação.';

    // -------------------------------------------------------------------------
    // Erros HTTP (bootstrap/app.php)
    // -------------------------------------------------------------------------
    const HTTP_RESOURCE_NOT_FOUND          = '%s não encontrado.';
    const HTTP_RESOURCE_NOT_FOUND_DETAILS   = 'O %s solicitado não existe ou foi excluído.';
    const HTTP_UNAUTHENTICATED           = 'Autenticação necessária.';
    const HTTP_UNAUTHENTICATED_DETAILS   = 'Você precisa estar autenticado para acessar este recurso.';
    const HTTP_FORBIDDEN                 = 'Acesso negado.';
    const HTTP_FORBIDDEN_DETAILS         = 'Você não tem permissão para acessar este recurso.';
    const HTTP_ENDPOINT_NOT_FOUND        = 'Endpoint não encontrado.';
    const HTTP_ENDPOINT_NOT_FOUND_DETAILS = 'O endpoint solicitado não existe.';
    const HTTP_VALIDATION_ERROR          = 'Falha na validação.';
    const HTTP_METHOD_NOT_ALLOWED        = 'Método não permitido.';
    const HTTP_METHOD_NOT_ALLOWED_DETAILS = 'O método HTTP utilizado não é suportado para este endpoint.';
    const HTTP_TOO_MANY_REQUESTS         = 'Muitas requisições.';
    const HTTP_TOO_MANY_REQUESTS_DETAILS  = 'Limite de requisições excedido. Tente novamente em %s segundos.';
    const HTTP_SERVER_ERROR              = 'Erro interno do servidor.';
    const HTTP_SERVER_ERROR_DETAILS      = 'Ocorreu um erro inesperado. Por favor, tente novamente mais tarde.';
}
