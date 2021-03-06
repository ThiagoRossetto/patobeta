<?php

namespace CloudSource\PatoBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

use CloudSource\PatoBundle\Entity\Caixas;
use CloudSource\PatoBundle\Entity\CaixasMovimentos;
use CloudSource\PatoBundle\Entity\CaixasMovimentosTipos;

/**
 * Funções de controle para movimentos de caixa
 * 
 * @author Adir Kuhn <adirkuhn@gmail.com>
 */
class CaixasMovimentosController extends Controller {

    /**
     * Função de ajuda para resposta rest json
     * 
     * @author Adir Kuhn <adirkuhn@gmail.com>
     * 
     * @param mixed $corpo Corpo da resposta
     * @param int $httpCode Código da resposta HTTP
     * 
     * @return Response Resposta a ser enviada
     **/
    public function resposta($corpo, $httpCode = 200)
    {
        $response = new Response();
        $response->headers->set('Content-Type', 'application/json');
        $response->setStatusCode($httpCode);
        $response->setContent(json_encode($corpo));

        return $response;
    }

    /**
     * Retorna os dados de um movimento
     * 
     * @author Adir Kuhn <adirkuhn@gmail.com>
     * 
     * @param int $id Identificação do movimento
     *
     * @return mixed JSON dos dados de um movimento
     **/
    public function pegarMovimentoAction($id)
    {
        //pega repositorio de dados
        $em = $this->getDoctrine()->getEntityManager();

        $movimento = $em->find('PatoBundle:CaixasMovimentos', $id);

        if ( !$movimento instanceof CaixasMovimentos) {

            return $this->resposta(
                array('erro' => 'Não foi possivel encontrar o movimento.'),
                404
            );
        }

        $respota = array();
        $resposta['id'] = $movimento->getId();
        $resposta['descricao'] = $movimento->getDescricao();
        $resposta['caixa'] = $movimento->getCaixa()->getId();
        $resposta['valor'] = $movimento->getValor();
        $resposta['data'] = $movimento->getDtMovimento()->format('d/m/Y');
        $resposta['tipoMovimento'] = $movimento->getCaixaMovimentoTipo()->getId();


        return $this->resposta($resposta);

    }

/**
     * Grava umo novo movimento no caixa
     *
     * @author Adir Kuhn <adirkuhn@gmail.com>
     * 
     * @return mixed[] Id e dados do novo movimento
     **/
    public function movimentoAction()
    {
        $post = $this->getRequest()->request->all();

        if ( array_key_exists('descricao', $post) && !empty($post['descricao']) &&
             array_key_exists('valor', $post) && !empty($post['valor']) &&
             array_key_exists('data', $post) && !empty($post['data']) &&
             array_key_exists('caixa', $post) && !empty($post['caixa']) && 
             array_key_exists('tipo', $post) && !empty($post['tipo']) 
            ) 
        {

            //pega repositorio de dados
            $em = $this->getDoctrine()->getEntityManager();

            //seta tipo de movimento
            $tipoMovimento = $em->find('PatoBundle:CaixasMovimentosTipos', $post['tipo']);
            if ( !$tipoMovimento instanceof CaixasMovimentosTipos) {

                return $this->resposta(
                    array('erro' => 'Não foi possivel encontrar o tipo de movimento.'),
                    404
                );
            }

            //parser data
            $data = explode('/', $post['data']);
            $data = $data[2] . '-' . $data[1] . '-' . $data[0];

            //parser valor
            //TODO: tem que colocar um filtro de formato no campo HTML (0.000,00) e verificar se bate aqui
            //converter , para .
            $valor = $post['valor'];

            //verifica se o movimento já existe (update) ou sera um novo
            if (!empty($post['id'])) {
                $movimento = $em->find('PatoBundle:CaixasMovimentos', $post['id']);

                //caso o id nao exista no banco
                if (! $movimento instanceof CaixasMovimentos) {

                    return $this->resposta(
                        array('erro' => 'Recurso não existe.'),
                        404
                    );
                }

                //TODO: Colocar data de atualização?
            }
            else {

                $movimento = new CaixasMovimentos();
                $movimento->setCriado(new \Datetime('now'));
            }

            $movimento->setDescricao($post['descricao']);
            $movimento->setCaixa($em->getRepository('PatoBundle:Caixas')->find($post['caixa']));
            $movimento->setCaixaMovimentoTipo($tipoMovimento);

            $movimento->setValor($valor);
            $movimento->setDtMovimento(new \Datetime($data));

            //TODO: Precisa ser feito a associacao com o usuario logado
            //para saber quem esta gravando no momento
            try {
                //salva
                $em->persist($movimento);
                $em->flush();

                //define resposta
                $post['id'] = $movimento->getId();

                return $this->resposta($post, 200);

            } catch (\Doctrine\DBAL\DBALException $e) {

                    return $this->resposta(
                        array('erro' => 'Erro ao salvar movimento em caixa.'),
                        409
                    );
            }

        } else {
            //erro na requisição, faltou campos obrigatorios
            return $this->resposta(
                array(
                    'erro' => 'Requisição inválida.'
                ), 
                400
            );
        }
    }

    /**
     * Delete um movimento do caixa
     * 
     * @author Adir Kuhn <adirkuhn@gmail.com>
     * 
     * @param int $id Identificação do caixa
     *
     * @return mixed JSON se foi possivel ou não excluir
     **/
    public function deletarMovimentoAction($id)
    {
        $em = $this->getDoctrine()->getManager();

        $movimento = $em->find('PatoBundle:CaixasMovimentos', $id);

        $return = false;
        //verifica se encontrou o movimento com a id informada
        if ($movimento instanceof CaixasMovimentos) {
                $em->remove($movimento);
                $em->flush();
                $return = true;
        }

        return $this->resposta($return);
    }
}