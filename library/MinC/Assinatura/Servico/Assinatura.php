<?php

class MinC_Assinatura_Servico_Assinatura implements MinC_Assinatura_Servico_IServico
{
    /**
     * @var MinC_Assinatura_Autenticacao_IAutenticacaoAdapter $servicoAutenticacao
     */
    private $servicoAutenticacao;

    /**
     * @var MinC_Assinatura_Servico_DocumentoAssinatura $servicoDocumentoAssinatura
     */
    private $servicoDocumentoAssinatura;

    public $post;

    public $identidadeUsuarioLogado;

    public $isMovimentarProjetoPorOrdemAssinatura = true;

    function __construct($post, $identidadeUsuarioLogado)
    {
        $this->post = $post;
        $this->identidadeUsuarioLogado = $identidadeUsuarioLogado;
    }

    /**
     * @return MinC_Assinatura_Servico_Autenticacao
     */
    public function obterServicoAutenticacao() {
        if(!isset($this->servicoAutenticacao)) {
            $this->servicoAutenticacao = new MinC_Assinatura_Servico_Autenticacao($this->post, $this->identidadeUsuarioLogado);
        }
        return $this->servicoAutenticacao;
    }

    /**
     * @return MinC_Assinatura_Servico_DocumentoAssinatura
     */
    public function obterServicoDocumento() {
        if(!isset($this->servicoDocumentoAssinatura)) {
            $this->servicoDocumentoAssinatura = new MinC_Assinatura_Servico_DocumentoAssinatura();
        }
        return $this->servicoDocumentoAssinatura;
    }

    public function assinarProjeto(MinC_Assinatura_Model_Assinatura $modelAssinatura)
    {

        if (empty(trim($modelAssinatura->getDsManifestacao()))) {
            throw new Exception ("Campo \"De acordo do Assinante\" &eacute; de preenchimento obrigat&oacute;rio.");
        }

        if (empty(trim($modelAssinatura->getIdPronac()))) {
            throw new Exception ("O n&uacute;mero do projeto &eacute; obrigat&oacute;rio.");
        }

        if (empty(trim($modelAssinatura->getIdTipoDoAtoAdministrativo()))) {
            throw new Exception ("O Tipo do Ato Administrativo &eacute; obrigat&oacute;rio.");
        }

        $servicoAutenticacao = $this->obterServicoAutenticacao();
        $metodoAutenticacao = $servicoAutenticacao->obterMetodoAutenticacao();

        if(!$metodoAutenticacao->autenticar()) {
            throw new Exception ("Os dados utilizados para autentica&ccedil;&atilde;o s&atilde;o inv&aacute;lidos.");
        }

        $usuario = $metodoAutenticacao->obterInformacoesAssinante();
        $modelAssinatura->setIdAssinante($usuario['usu_codigo']);

        $objTbAtoAdministrativo = new Assinatura_Model_DbTable_TbAtoAdministrativo();
        $dadosAtoAdministrativoAtual = $objTbAtoAdministrativo->obterAtoAdministrativoAtual(
            $modelAssinatura->getIdTipoDoAtoAdministrativo(),
            $modelAssinatura->getCodGrupo(),
            $modelAssinatura->getCodOrgao()
        );
        $modelAssinatura->setIdOrdemDaAssinatura($dadosAtoAdministrativoAtual['idOrdemDaAssinatura']);
        $modelAssinatura->setIdAtoAdministrativo($dadosAtoAdministrativoAtual['idAtoAdministrativo']);

        if (!$dadosAtoAdministrativoAtual) {
            throw new Exception ("Usu&aacute;rio sem autoriza&ccedil;&atilde;o para assinar o documento.");
        }

        if($this->isProjetoAssinado($modelAssinatura)) {
            throw new Exception ("O documento j&aacute; foi assinado pelo usu&aacute;rio logado nesta fase atual.");
        }

        $dadosInclusaoAssinatura = array(
            'idPronac' => $modelAssinatura->getIdPronac(),
            'idAtoAdministrativo' => $modelAssinatura->getIdAtoAdministrativo(),
            'dtAssinatura' => $objTbAtoAdministrativo->getExpressionDate(),
            'idAssinante' => $modelAssinatura->getIdAssinante(),
            'dsManifestacao' => $modelAssinatura->getDsManifestacao(),
            'idDocumentoAssinatura' => $modelAssinatura->getIdDocumentoAssinatura()
        );

        $objTbAssinatura = new Assinatura_Model_DbTable_TbAssinatura();
        $objTbAssinatura->inserir($dadosInclusaoAssinatura);
        $codigoOrgaoDestino = $objTbAtoAdministrativo->obterProximoOrgaoDeDestino(
            $modelAssinatura->getIdTipoDoAtoAdministrativo(),
            $modelAssinatura->getIdOrdemDaAssinatura(),
            $modelAssinatura->getIdOrgaoSuperiorDoAssinante()
        );

        if($this->isMovimentarProjetoPorOrdemAssinatura && $codigoOrgaoDestino) {
            $this->movimentarProjetoAssinadoPorOrdemDeAssinatura($modelAssinatura);
        }
    }

    public function movimentarProjetoAssinadoPorOrdemDeAssinatura(MinC_Assinatura_Model_Assinatura $modelAssinatura)
    {
        if (!$modelAssinatura->getIdOrdemDaAssinatura()) {
            throw new Exception("A fase atual do projeto n&atilde;o permite movimentar o projeto.");
        }

        if(!$this->isProjetoAssinado($modelAssinatura)) {
            throw new Exception ("O documento precisa ser assinado para que consiga ser movimentado.");
        }

        $objTbAtoAdministrativo = new Assinatura_Model_DbTable_TbAtoAdministrativo();
        $codigoOrgaoDestino = $objTbAtoAdministrativo->obterProximoOrgaoDeDestino(
            $modelAssinatura->getIdTipoDoAtoAdministrativo(),
            $modelAssinatura->getIdOrdemDaAssinatura(),
            $modelAssinatura->getIdOrgaoSuperiorDoAssinante()
        );
        if (!$codigoOrgaoDestino) {
            throw new Exception("A fase atual do projeto n&atilde;o permite movimentar o projeto.");
        }

        $objTbProjetos = new Projeto_Model_DbTable_Projetos();
        $objTbProjetos->alterarOrgao($codigoOrgaoDestino, $modelAssinatura->getIdPronac());
    }

    public function isProjetoAssinado(MinC_Assinatura_Model_Assinatura $modelAssinatura) {

        $objTbAssinatura = new Assinatura_Model_DbTable_TbAssinatura();
        $assinaturaExistente = $objTbAssinatura->buscar(array(
            'idPronac = ?' => $modelAssinatura->getIdPronac(),
            'idAtoAdministrativo = ?' => $modelAssinatura->getIdAtoAdministrativo(),
            'idAssinante = ?' => $modelAssinatura->getIdAssinante(),
            'idDocumentoAssinatura = ?' => $modelAssinatura->getIdDocumentoAssinatura()
        ));

        if($assinaturaExistente->current()) {
            return true;
        }
        return false;
    }

}