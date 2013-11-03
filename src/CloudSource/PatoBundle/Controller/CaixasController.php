<?php

namespace CloudSource\PatoBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;

use CloudSource\PatoBundle\Entity\Caixas;
use CloudSource\PatoBundle\Entity\CaixasMovimentos;
use CloudSource\PatoBundle\Entity\CaixasMovimentosTipos;

/**
 *
 * Classe gerenciar caixa
 **/
class CaixasController extends Controller
{
    /**
     * Retorna página inicial do caixa
     *
     * @author Adir Kuhn <adirkuhn@gmail.com>
     * 
     * @return Response Página inicial do caixa
     **/
    public function indexAction($caixa, $mes)
    {

        $em = $this->getDoctrine()->getRepository('PatoBundle:Caixas');
        $caixas = $em->findAll();

        $caixa = ($caixa == 0)? $caixas[$caixa]->getId(): $caixa;

        $caixaSelecionado = $em->find($caixa);

        //se mes = 0 mes = mes atual
        $mes = ($mes == 0)? date('m') : $mes;

        if (!empty($caixas)) {

            $em = $this->getDoctrine()->getRepository('PatoBundle:CaixasMovimentos');

            $movimentos = $em->pegarMovimentosMes($caixa, $mes);

            return $this->render('PatoBundle:Caixas:index.html.twig', array(
                'caixas' => $caixas,
                'caixaSelecionado' => $caixaSelecionado,
                'mes' => $mes
            ));
        }
        else {
            //TODO: Nenhum caixa foi encontrado, montar tela tuto
            //para configuração do caixa

            return new Response('Conf novo caixa', 404);
        }

    }

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
     * Grava uma nova entrada no caixa
     *
     * @author Adir Kuhn <adirkuhn@gmail.com
     * 
     * @return mixed[] Id e dados da nova entrada
     **/
    public function entradaAction()
    {
        //pega dados do post e sanitiza
        $post = array_map('addslashes', $this->getRequest()->request->all());

        if ( array_key_exists('descricao', $post) && !empty($post['descricao']) &&
             array_key_exists('valor', $post) && !empty($post['valor']) &&
             array_key_exists('data', $post) && !empty($post['data']) &&
             array_key_exists('caixa', $post) && !empty($post['caixa'])) 
        {

            //pega repositorio de dados
            $em = $this->getDoctrine()->getEntityManager();

            //verifica se esta sendo passado tipo de movimento, caso não seta movimento padrão
            //e busca o tipo de movimento
            $tipoMovimento = 1; //TIPO 1 == entrada
            if ( array_key_exists('tipoMovimento', $post) && !empty($post['tipoMovimento']) ) {
                $tipoMovimento = $post['tipoMovimento'];
            }
            $tipoMovimento = $em->find('PatoBundle:CaixasMovimentosTipos', $tipoMovimento);
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

            $entrada = new CaixasMovimentos();
            $entrada->setCriado(new \Datetime('now'));

            $entrada->setDescricao($post['descricao']);
            $entrada->setCaixa($em->getRepository('PatoBundle:Caixas')->find($post['caixa']));
            $entrada->setCaixaMovimentoTipo($tipoMovimento);

            $entrada->setValor($valor);
            $entrada->setDtMovimento(new \Datetime($data));

            //TODO: Precisa ser feito a associacao com o usuario logado
            //para saber quem esta gravando no momento
            try {
                //salva
                $em->persist($entrada);
                $em->flush();

                //define resposta
                $post['id'] = $entrada->getId();

                return $this->resposta($post, 200);

            } catch (\Doctrine\DBAL\DBALException $e) {

                    return $this->resposta(
                        array('erro' => 'Erro ao salvar nova entrada em caixa.'),
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
}