<?php

namespace NMKD\Bundle\PdfBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller,
    Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

class PdfController extends Controller
{

    public function outPdfAction(Request $request)
    {
        //return $response = $this->forward('NMKDPdfBundle:Pdf:outPdf',array('tplname'=>'pdf1'));

        $tplname = $request->get('tplname');
        $data = $request->get('data');
        $filename = $request->get('filename');

        $view = 'NMKDPdfBundle:PdfTemplates:'.$tplname.'.html.twig';
        $html = $this->renderView($view, array('data' => $data));

        return new Response(
            $this->container->get('knp_snappy.pdf')->getOutputFromHtml($html),
            200,
            array(
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$filename.'.pdf"'
            )
        );
    }


}
