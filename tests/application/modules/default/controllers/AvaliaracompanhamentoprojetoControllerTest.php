<?php
/**
 * Projeto_AlterarprojetoControllerTest
 *
 * @package
 * @author isaiassm <isaias1113@outlook.com>
 */
class AvaliaracompanhamentoprojetoControllerTest extends MinC_Test_ControllerActionTestCase
{
    public function setUp()
    {
        parent::setUp();
        $this->idPronac = '209649';

        $this->hashIdPronac = "501eac548e7d4fa987034573abc6e179MjAxNzEzZUA3NWVmUiEzNDUwb3RT";

        $this->autenticar();

        $this->resetRequest()
            ->resetResponse();

        $this->alterarPerfil(Autenticacao_Model_Grupos::COORDENADOR_AVALIACAO, Orgaos::ORGAO_GEAR_SACAV);

        $this->resetRequest()
            ->resetResponse();
    }
    public function testindexAction()
    {
        $this->dispatch('/avaliaracompanhamentoprojeto?idPronac=' . $this->idPronac);
        $this->assertUrl('default', 'avaliaracompanhamentoprojeto', 'index');
    }
}