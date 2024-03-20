<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_integracao;

use Exception;
use core_external\external_value;
use core_external\external_single_structure;
use core_external\external_function_parameters;

/**
 * Class local_wsintegracao_course
 * @copyright 2018 Uemanet
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_wsintegracao_course extends wsintegracao_base {

    /**
     * @param $course
     * @return null
     * @throws Exception
     * @throws dml_transaction_exception
     * @throws invalid_parameter_exception
     */
    public static function create_course($course) {
        global $CFG, $DB;

        // Validação dos paramêtros.
        self::validate_parameters(self::create_course_parameters(), array('course' => $course));

        // Transforma o array em objeto.
        $course = (object)$course;

        // Verifica se o curso pode ser criado.
        self::get_create_course_validation_rules($course);

        // Adiciona a bibliteca de curso do moodle.
        require_once("{$CFG->dirroot}/course/lib.php");

        $returndata = null;

        try {
            // Inicia a transacao, qualquer erro que aconteca o rollback sera executado.
            $transaction = $DB->start_delegated_transaction();

            // Cria o curso usando a biblioteca do proprio moodle.
            $result = create_course($course);

            // Caso o curso tenha sido criado adiciona na tabela de controle os dados do curso e da turma.
            $res = null;

            if ($result->id) {
                $data['trm_id'] = $course->trm_id;
                $data['courseid'] = $result->id;

                $res = $DB->insert_record('int_turma_course', $data);
            }

            // Prepara o array de retorno.
            if ($res) {
                $returndata['id'] = $result->id;
                $returndata['status'] = 'success';
                $returndata['message'] = 'Curso criado com sucesso';
            } else {
                $returndata['id'] = 0;
                $returndata['status'] = 'error';
                $returndata['message'] = 'Erro ao tentar criar o curso';
            }

            // Persiste as operacoes em caso de sucesso.
            $transaction->allow_commit();

        } catch (Exception $e) {
            $transaction->rollback($e);
        }

        return $returndata;
    }

    /**
     * @return external_function_parameters
     */
    public static function create_course_parameters() {
        return new external_function_parameters(
            array(
                'course' => new external_single_structure(
                    array(
                        'trm_id' => new external_value(PARAM_INT, 'Id da turma no gestor'),
                        'category' => new external_value(PARAM_INT, 'Categoria do curso'),
                        'shortname' => new external_value(PARAM_TEXT, 'Nome curto do curso'),
                        'fullname' => new external_value(PARAM_TEXT, 'Nome completo do curso'),
                        'summaryformat' => new external_value(PARAM_INT, 'Formato do sumario'),
                        'format' => new external_value(PARAM_TEXT, 'Formato do curso'),
                        'numsections' => new external_value(PARAM_INT, 'Quantidade de sections')
                    )
                )
            )
        );
    }

    /**
     * @return external_single_structure
     */
    public static function create_course_returns() {
        return new external_single_structure(
            array(
                'id' => new external_value(PARAM_INT, 'Id do curso criado'),
                'status' => new external_value(PARAM_TEXT, 'Status da operacao'),
                'message' => new external_value(PARAM_TEXT, 'Mensagem de retorno da operacao')
            )
        );
    }

    /**
     * @param $course
     * @return null
     * @throws dml_transaction_exception
     * @throws invalid_parameter_exception
     */
    public static function update_course($course) {
        global $CFG, $DB;

        // Valida os parametros.
        self::validate_parameters(self::update_course_parameters(), array('course' => $course));

        // Inclui a biblioteca de cursos do moodle.
        require_once("{$CFG->dirroot}/course/lib.php");

        // Transforma o array em objeto.
        $course = (object)$course;

        $returndata = null;

        try {

            // Inicia a transacao, qualquer erro que aconteca o rollback sera executado.
            $transaction = $DB->start_delegated_transaction();

            // Busca o id do curso apartir do trm_id da turma.
            $courseid = self::get_course_by_trm_id($course->trm_id);

            // Se nao existir curso mapeado para a turma dispara uma excessao.
            if (!$courseid) {
                throw new Exception("Nenhum curso mapeado com a turma com trm_id: " . $course->trm_id);
            }

            $course->id = $courseid;

            // Atualiza o curso usando a biblioteca do proprio moodle.
            update_course($course);

            // Persiste as operacoes em caso de sucesso.
            $transaction->allow_commit();

            // Prepara o array de retorno.
            $returndata['id'] = $courseid;
            $returndata['status'] = 'success';
            $returndata['message'] = "Curso atualizado com sucesso";

        } catch (Exception $e) {
            $transaction->rollback($e);
        }

        return $returndata;
    }

    /**
     * @return external_function_parameters
     */
    public static function update_course_parameters() {
        return new external_function_parameters(
            array(
                'course' => new external_single_structure(
                    array(
                        'trm_id' => new external_value(PARAM_INT, 'Id da turma no gestor'),
                        'shortname' => new external_value(PARAM_TEXT, 'Nome curto do curso'),
                        'fullname' => new external_value(PARAM_TEXT, 'Nome completo do curso')
                    )
                )
            )
        );
    }

    /**
     * @return external_single_structure
     */
    public static function update_course_returns() {
        return new external_single_structure(
            array(
                'id' => new external_value(PARAM_INT, 'Id do curso atualizado'),
                'status' => new external_value(PARAM_TEXT, 'Status da operacao'),
                'message' => new external_value(PARAM_TEXT, 'Mensagem de retorno da operacao')
            )
        );
    }

    /**
     * @param $course
     * @return null
     * @throws Exception
     * @throws dml_transaction_exception
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     */
    public static function remove_course($course) {
        global $CFG, $DB;

        // Valida os parametros.
        self::validate_parameters(self::remove_course_parameters(), array('course' => $course));

        // Inclui a biblioteca de cursos do moodle.
        require_once("{$CFG->dirroot}/lib/moodlelib.php");

        // Transforma o array em objeto.
        $course = (object)$course;

        // Busca o id do curso apartir do trm_id da turma.
        $courseid = self::get_course_by_trm_id($course->trm_id);

        // Se nao existir curso mapeado para a turma dispara uma excessao.
        if (!$courseid) {
            throw new Exception("Nenhum curso mapeado com a turma com trm_id: " . $course->trm_id);
        }

        $course->id = $courseid;

        $returndata = null;

        try {
            // Inicia a transacao, qualquer erro que aconteca o rollback sera executado.
            $transaction = $DB->start_delegated_transaction();

            // Deleta o curso.
            delete_course($courseid, false);

            // Deleta os registros da tabela de controle.
            $DB->delete_records('int_turma_course', array('courseid' => $courseid));

            // Persiste as operacoes em caso de sucesso.
            $transaction->allow_commit();

            // Prepara o array de retorno.
            $returndata['id'] = 1;
            $returndata['status'] = 'success';
            $returndata['message'] = "Curso excluído com sucesso";

        } catch (Exception $e) {
            $transaction->rollback($e);
        }

        return $returndata;
    }

    /**
     * @return external_function_parameters
     */
    public static function remove_course_parameters() {
        return new external_function_parameters(
            array(
                'course' => new external_single_structure(
                    array(
                        'trm_id' => new external_value(PARAM_INT, 'Id da turma no gestor')
                    )
                )
            )
        );
    }

    /**
     * @return external_single_structure
     */
    public static function remove_course_returns() {
        return new external_single_structure(
            array(
                'id' => new external_value(PARAM_INT, 'Id do curso atualizado'),
                'status' => new external_value(PARAM_TEXT, 'Status da operacao'),
                'message' => new external_value(PARAM_TEXT, 'Mensagem de retorno da operacao')
            )
        );
    }

    /**
     * @param $course
     * @return bool
     * @throws Exception
     * @throws moodle_exception
     */
    protected static function get_create_course_validation_rules($course) {
        // Verifica se a turma já está mapeada para algum curso do ambiente.
        $courseid = self::get_course_by_trm_id($course->trm_id);

        // Dispara uma excessao se essa turma ja estiver mapeada para um curso.
        if ($courseid) {
            throw new \Exception("Essa turma ja esta mapeada com o curso de id: " . $courseid);
        }

        return true;
    }
}
