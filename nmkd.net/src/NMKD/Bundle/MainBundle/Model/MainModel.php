<?php

namespace NMKD\Bundle\MainBundle\Model;

use PDO;

class MainModel
{
    protected $db;
    private $questions;
    private $questionIdNames;
    protected $container;

    public function __construct($container)
    {
        $dbname = $container->getParameter('database_name');
        $host = $container->getParameter('database_host');
        $username = $container->getParameter('database_user');
        $password = $container->getParameter('database_password');
        $this->container = $container;

        $this->db = new PDO("pgsql:dbname=$dbname;host=$host", $username, $password );
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function setAllQuestions($questions)
    {
        $this->questions = $questions;
        $tqQuery = $this->db->prepare("INSERT INTO themes_questions(name,id_discipline)
                                      VALUES (:name,1) ");
        $this->db->beginTransaction();
        foreach($questions as $question){
            $tqQuery->bindValue(':name',$question);
            $tqQuery->execute();
        }
        $this->db->commit();
    }

    public function getLastLoadedQuestions($questions)
    {
        $lastTqQuery = $this->db->prepare("SELECT id_tq, name
                                         FROM themes_questions
                                         ORDER BY id_tq DESC
                                         LIMIT :qCount");
        $this->db->beginTransaction();    //get id of last uploaded questions
        $lastTqQuery->bindValue(':qCount',count($questions));
        $lastTqQuery->execute();
        $this->db->commit();

        $res = $lastTqQuery->fetchAll(PDO::FETCH_NUM);
        foreach ($res as $key=>$val) {
            $questionIdNames[$res[$key][0]] = $res[$key][1];
        }
        $this->questionIdNames = $questionIdNames;

        return $questionIdNames;
    }

    public function setTypeQuestions($type, $session)
    {
        $tqQuery = $this->db->prepare("UPDATE themes_questions
                                          SET types_id = :type_id,
                                              id_parent = :id_parent
                                          WHERE id_tq = :id_tq");
        $typeQuery = $this->db->prepare("INSERT INTO ".$type."(theme)
                                          VALUES (:theme_name)");
        $typeThemes = $session->get($type.'_theme');
        foreach ($typeThemes as $key=>$val) {
            if(!isset($this->questions[$val])) {
                return;
            }
            $typeParentNames[$key] = $this->questions[$val]; //theme name
        }
        $typeNums = $session->get($type);
        foreach ($typeNums as $question) {
            $typeNames[] = $this->questions[$question];
        }
        $this->db->beginTransaction();
            $this->container->getParameter('question_types');
            //$num - child number in generally question list
            //$name - parent name
            foreach ($typeParentNames as $num=>$name) {
                if(!isset($this->questions[$num])) {
                    $this->db->commit();
                    return;
                }
                $tqQuery->bindValue(':id_tq', array_search($this->questions[$num],
                                                           $this->questionIdNames));
                $tqQuery->bindValue(':id_parent', array_search($name, $this->questionIdNames));
                $tqQuery->bindValue(':type_id', '{'.(string)array_search($type,
                                                    $this->container->getParameter('question_types')).'}');
                $typeQuery->bindValue(':theme_name', $name);
                $tqQuery->execute();
                $typeQuery->execute();
            }

        $this->db->commit();
    }
}