<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_pdf_report_renders(): void
    {
        $this->withSession($this->authSession())->get(route('inventario.export.pdf'))->assertOk();
    }

    public function test_movements_pdf_report_renders(): void
    {
        $this->withSession($this->authSession())->get(route('movimientos.export.pdf'))->assertOk();
    }

    public function test_sales_pdf_report_renders(): void
    {
        $this->withSession($this->authSession())->get(route('ventas.export.pdf'))->assertOk();
    }

    public function test_apartados_pdf_report_renders(): void
    {
        $this->withSession($this->authSession())->get(route('apartados.export.pdf'))->assertOk();
    }

    public function test_shipments_pdf_report_renders(): void
    {
        $this->withSession($this->authSession())->get(route('envios.export.pdf'))->assertOk();
    }

    private function authSession(): array
    {
        return [
            'codCongregante' => 'admin-test', 'username' => 'Admin',
            'roles' => [['ROL' => 'ADMIN LIBRERIA', 'ID' => 19]],
        ];
    }
}
