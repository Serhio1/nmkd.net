<?php

namespace NMKD\Bundle\MainBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use \Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Driver\PDOPgSql;
use Doctrine\DBAL\Driver\PDOConnection;
use PDO;
use NMKD\Bundle\MainBundle\Model\MainModel;

class DefaultController extends Controller
{

    public function indexAction(Request $request)
    {
        $session = $this->get('session');


        $form = $this->createFormBuilder()->getForm();
        $form->add('questions','textarea')
             ->add('next','submit');

        $form->handleRequest($request);
        if($form->isValid()){
            $questionStr = $form->get('questions')->getData();
            $questionArr = explode('<br />', nl2br($questionStr));
            $questionArr = array_map('trim',$questionArr);
            $questionArr = array_values(array_filter($questionArr));

            $session->set('questions',$questionArr);

            return $this->redirect($this->generateUrl('set_theme_questions'));
        }

        return $this->render('NMKDMainBundle:Default:index.html.twig',array(
            'form'=>$form->createView()
        ));
    }

    public function setThemesAction(Request $request)
    {
        $session = $this->get('session');
        $questions = $session->get('questions');
        $themes = array();

        $form = $this->createFormBuilder()->getForm();
        foreach($questions as $num=>$question){
            $form->add($num,'checkbox',array('required'=>false));
        }
        $form->add('next','submit');

        $form->handleRequest($request);
        if($form->isValid()){
            foreach($questions as $num=>$question){
                $themes[]+=$form->get($num)->getData();
            }
            $session->set('themes',array_filter($themes));

            return $this->redirect($this->generateUrl('set_question_types'));
        }

        return $this->render('NMKDMainBundle:Default:themes.html.twig',array(
            'form'=>$form->createView(),
            'questions'=>$questions
        ));
    }

    public function setTypesAction(Request $request)
    {
        $session = $this->get('session');
        $questions = $session->get('questions');
        $themes = $session->get('themes');
        $questionProperties = array();
        $types = $this->container->getParameter('question_types');

        $lection = array();
        $practical = array();
        $seminary = array();
        $laboratory = array();
        $individual = array();
        $self = array();

        $form = $this->createFormBuilder()->getForm();
        foreach($questions as $num=>$question){
            foreach ($types as $type) {
                $form->add($type.$num,'checkbox',array('required'=>false));
            }
        }
        $form->add('next','submit');

        $form->handleRequest($request);
        if($form->isValid()){
            foreach($questions as $num=>$question){
                foreach ($types as $type) {

                    if($form->get($type.$num)->getData()){
                        if($type=='lection'){
                            $lection[]+=$num;
                        }
                        if($type=='practical'){
                            $practical[]+=$num;
                        }
                        if($type=='seminary'){
                            $seminary[]+=$num;
                        }
                        if($type=='laboratory'){
                            $laboratory[]+=$num;
                        }
                        if($type=='individual'){
                            $individual[]+=$num;
                        }
                        if($type=='self'){
                            $self[]+=$num;
                        }
                        //$questionProperties[$type][]+=$num;
                    }//add($type.$num,'checkbox',array('required'=>false));
                }
            }

            $session->set('lection',$lection);
            $session->set('practical',$practical);
            $session->set('seminary',$seminary);
            $session->set('laboratory',$laboratory);
            $session->set('individual',$individual);
            $session->set('self',$self);

            return $this->redirect('question-theme');
        }

        return $this->render('NMKDMainBundle:Default:types.html.twig',array(
            'form'=>$form->createView(),
            'questions'=>$questions
        ));
    }

    public function questionToThemeAction(Request $request, $type = 'lection')
    {
        $session = $this->get('session');
        $themes = $session->get('themes');
        $questions = $session->get('questions');
        $themesList = array_intersect_key($questions, $themes);
        $types = $this->container->getParameter('question_types');


        $typeNum = array_search($type, $types);
        if(isset($types[$typeNum+1])){
            $nextType = $types[$typeNum+1];
        } else {
            $nextType = 'save';
        }

        if ($type != 'save') {
            foreach($session->get($type) as $val) {
                $questionList[] = $questions[$val];
            }
        } else {
            foreach($session->get($types[count($types)-1]) as $val) {
                $questionList[] = $questions[$val];
            }
        }
        $form = $this->createFormBuilder()
            ->setAction($this->generateUrl('question_to_theme',array('type'=>$nextType)))
            ->getForm();
        $form->add('data', 'hidden')
            ->add('next','submit');
        $form->handleRequest($request);
        if($form->isValid()){
            if ($type != 'save') {
                $typeNum = array_search($type,$types);
                $prevType = $types[$typeNum-1];
            } else {
                $prevType = $types[count($types)-1];
            }

            $data = $form->get('data')->getData();
            $questionTheme = $this->getQTArray($data, $prevType);
            $session->set($prevType.'_theme',$questionTheme);
        }
        if($type == 'save'){
            $this->saveToDB();
            return $this->redirect($this->generateUrl('nmkd_main_homepage'));

        }
        if(!isset($questionList)) {
            return $this->redirect($this->generateUrl('question_to_theme').'/'.$nextType);
        }

        return $this->render('NMKDMainBundle:Default:question_to_theme.html.twig',array(
            'questionList'=>$questionList,
            'themes'=>$themesList,
            'type'=>$type,
            'form'=>$form->createView()
        ));
    }


    private function getQTArray($data, $type)
    {
        $session = $this->get('session');
        $type = $session->get($type);
        $data=rtrim(trim($data),',');
        $dataArr = explode(',',$data);
        $str = '';
        foreach($dataArr as $key=>$val){
            $buffer = explode(':',$val);
            foreach($buffer as $inkey=>$inval){
                $str .= $inval.'|';
            }
        }
        $testArr = explode('|',rtrim(trim($str),'|'));
        $cnt = count($testArr);
        for($i = 0; $i < $cnt; $i += 2)
        {
            $qArr[]=$type[$testArr[$i]];
            $tArr[]=$testArr[$i+1];
        }
        $questionTheme = array_combine($qArr,$tArr);

        return $questionTheme;
    }

    public function saveToDB()
    {
        $session = $this->get('session');
        $questions = $session->get('questions');

        try
        {
            $model = new MainModel($this->container);
            $model->setAllQuestions($questions);
            $model->getLastLoadedQuestions($questions);
            foreach($this->container->getParameter('question_types') as $type) {
                $model->setTypeQuestions($type, $session);
            }
        }
        catch(PDOException $e)
        {
            die("Error: ".$e->getMessage());
        }

    }


}