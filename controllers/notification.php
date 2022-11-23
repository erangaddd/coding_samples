<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Notification extends CI_Controller {

	 function __construct() {
        parent::__construct();
		$this->is_logged_in();
		$this->load->model("pr/configuration_model",'configuration');
		$this->load->model("accesshelper_model");
		$this->load->model("pr/common_model");
		$this->load->model("branch_model");
        $this->load->model("pr/property_model");
        $this->load->model("pr/application_model");
        $this->load->model("pr/agreement_model");
        $this->load->model("pr/rental_model");
        $this->load->model("income_model");
		$this->load->library("pagination");
        $this->load->model("customer_model");
	$this->load->model("pr/rentalreport_model");
    }
	
	function getnotification(){
		$data['menu_name'] = 'Notification Center';
        $data['submenu_name'] = '';
	    $data['notifications'] = $this->common_model->get_unread_notification_count('all');
		
		$this->load->view('pr/notification/notification_data', $data);
	}

	
}
