<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Gravação de preço no ERP
    |--------------------------------------------------------------------------
    |
    | Liga a gravação do preço de ATACADO direto no ERP (PRODUTO_PRO, conexão
    | `erp_update`) quando a célula é salva na grade Produtos × Preços.
    |
    | Desligado, a grade grava só na base do site — é o estado dos testes e de
    | qualquer ambiente sem as credenciais de escrita do ERP.
    |
    */

    'price_write' => (bool) env('ERP_PRICE_WRITE', true),

];
