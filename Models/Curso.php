<?php

namespace EdukInfo\Models;

use JetBrains\PhpStorm\ArrayShape;
use mysqli;

class Curso
{
    /**
     * @param string[] $gradesCurso
     */
    public function __construct(
        public readonly int $id,
        public readonly string $nome,
        public readonly string $descricao,
        public readonly float $preco,
        public ImagensSlidesCurso $imagensSlidesCurso = new ImagensSlidesCurso(),
        public array $gradesCurso = [],
        public readonly bool $descontinuado = false
    ){ }



    #[ArrayShape([
        "id" => "int",
        "nome" => "string",
        "descricao" => "string",
        "preco" => "float",
        "descontinuado" => "bool",
        "gradeMaterias" => "string[]|null",
        "imagensSlideCurso" => [[
            "url"=>"string",
            "altTxt"=>"string",
            "descricao"=>"string"
        ]]
    ])]
    public function toJsonArray(bool $incluirGrade): array {
        return [
            "id"=>$this->id,
            "nome"=>$this->nome,
            "descricao"=>$this->descricao,
            "preco"=>$this->preco,
            "descontinuado"=>$this->descontinuado,
            "gradeMaterias"=>$incluirGrade ? $this->gradesCurso : null,
            "imagensSlideCurso"=>$this->imagensSlidesCurso->toJsonArray()
        ];
    }

    /** @return Curso[] */
    public static function getTodosCursos():array{
        return self::getAllCursos(null);
    }

    public static function getCursoById(int $id): ?Curso{
        $curso = self::getAllCursos($id);
        return $curso !== null ? $curso[0] : null;
    }

    private static function getAllCursos(?int $id):?array {
        /** @var Curso[] $cursos */
        $cursos = [];
        /** @var mysqli $db */
        $db = require(__DIR__ . '/../db.php');

        $buscaCursos = $db->prepare(
            $id === null ? "SELECT * FROM Cursos" : "SELECT * FROM Cursos WHERE id = ?"
        );
        if($id !== null){
            $buscaCursos->bind_param("i",$id);
        }
        $buscaCursos->execute();
        $cursosObtidosDoDB = $buscaCursos->get_result();
        if($cursosObtidosDoDB->num_rows === 0) {
            return null;
        }
        while($linhaCurso = $cursosObtidosDoDB->fetch_assoc()){
            $buscaImagensCurso = $db->prepare("SELECT i.url, i.descricao,i.texto_alternativo_img FROM Imagens_slide_curso i INNER JOIN Cursos c ON i.id_curso = c.id WHERE c.id =  ?");
            $buscaImagensCurso->bind_param("i",$linhaCurso["id"]);
            $buscaImagensCurso->execute();
            $imagensCursoObtidoDB = $buscaImagensCurso->get_result();
            $imagensCurso = new ImagensSlidesCurso();
            while($linhaImgCurso = $imagensCursoObtidoDB->fetch_assoc()){
                $imagensCurso->add(new ImagemSlideCurso(
                    strval($linhaImgCurso["url"]),
                    strval($linhaImgCurso["texto_alternativo_img"]),
                    strval($linhaImgCurso["descricao"])
                ));
            }
            // SELECT m.nome FROM Grade_materia_cursos g INNER JOIN Materia_cursos m ON g.id_materia = m.id WHERE g.id_curso = 3 ORDER BY g.id, g.posicao;
            $buscaGradeCursos = $db->prepare("SELECT m.nome FROM Grade_materia_cursos g INNER JOIN Materia_cursos m ON g.id_materia = m.id WHERE g.id_curso = ? ORDER BY g.id, g.posicao");
            $buscaGradeCursos->bind_param("i",$linhaCurso["id"]);
            $buscaGradeCursos->execute();
            $gradeCursosObtidoDB = $buscaGradeCursos->get_result();
            $gradeCursos = [];
            while($linhaGradeCurso = $gradeCursosObtidoDB->fetch_assoc()){
                $gradeCursos[] = strval($linhaGradeCurso["nome"]);
            }
            $cursos[] = new Curso(
                intval($linhaCurso["id"]),
                strval($linhaCurso["nome"]),
                strval($linhaCurso["descricao"]),
                floatval($linhaCurso["preco"]),
                $imagensCurso,
                $gradeCursos,
                boolval($linhaCurso["descontinuado"])
            );
            unset($buscaImagensCurso,$imagensCursoObtidoDB,$buscaGradeCursos,$gradeCursosObtidoDB);
        }
        unset($db,$buscaCursos,$cursosObtidosDoDB);
        return $cursos;
    }
}