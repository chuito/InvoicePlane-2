<?php
if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

/**
 * Class Form_Validation_Model
 * @package Core
 * @property CI_Loader $load
 * @property MY_Form_validation $form_validation
 */
class Form_Validation_Model extends MY_Model
{
    /**
     * Form_Validation_Model constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->load->library('form_validation');
        $this->form_validation->CI =& $this;
    }

}
