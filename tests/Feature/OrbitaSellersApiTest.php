<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * GET /api/orbita/sellers: contatos do "Finalizar pelo WhatsApp" do carrinho
 * e do botão flutuante do Orbita. Sem cadastro de vendedores ainda, é um
 * contato único com o WhatsApp de vendas.
 */
class OrbitaSellersApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['services.orbita.token' => 'token-de-teste']);
    }

    private function sellers()
    {
        return $this->withHeader('X-Orbita-Token', 'token-de-teste')->getJson('/api/orbita/sellers');
    }

    public function test_responde_o_whatsapp_de_vendas_so_com_digitos(): void
    {
        config(['services.whatsapp.catalogo' => '+595 976 883 793']);

        $this->sellers()
            ->assertOk()
            ->assertExactJson(['data' => [[
                'id' => 'vendas',
                'section' => 'Vendas',
                'name' => null,
                'phone' => '595976883793',
                'photo' => null,
                'order' => 0,
            ]]]);
    }

    public function test_sem_numero_configurado_responde_lista_vazia(): void
    {
        config(['services.whatsapp.catalogo' => '']);

        $this->sellers()->assertOk()->assertExactJson(['data' => []]);
    }
}
