<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

class Dashboard_model extends CI_Model {

    function __construct() {
        parent::__construct();
    }

	function getNewAgreementCount(){
		$this->db->select('*');
		$this->db->where('pr_rent_leaseagreement.create_date >=',date('Y-m-01'));
		$this->db->where('pr_rent_leaseagreement.create_date <=',date('Y-m-t'));
		$query = $this->db->get('pr_rent_leaseagreement'); 
		if($query->num_rows() > 0){
			return $query->num_rows(); 
		}else{
			return 0;
		}
	}

	function getExpireAgreementCount(){
		$this->db->select('*');
		$this->db->where('pr_rent_leaseagreement.end_date >=',date('Y-m-01'));
		$this->db->where('pr_rent_leaseagreement.end_date <=',date('Y-m-t'));
		$this->db->where('pr_rent_leaseagreement.status','MOVED-IN');
		$query = $this->db->get('pr_rent_leaseagreement'); 
		if($query->num_rows() > 0){
			return $query->num_rows(); 
		}else{
			return 0;
		}
	}

	function getTerminatedAgreementCount(){
		$this->db->select('*');
		$this->db->where('pr_rent_leaseagreement.terminate_date >=',date('Y-m-01'));
		$this->db->where('pr_rent_leaseagreement.terminate_date <=',date('Y-m-t'));
		$query = $this->db->get('pr_rent_leaseagreement'); 
		if($query->num_rows() > 0){
			return $query->num_rows(); 
		}else{
			return 0;
		}
	}

	function getTaskRequests(){
		$this->db->select('*');
		$this->db->where('pr_task_requests.create_date >=',date('Y-m-01'));
		$this->db->where('pr_task_requests.create_date <=',date('Y-m-d'));
		$query = $this->db->get('pr_task_requests'); 
		if($query->num_rows() > 0){
			return $query->num_rows(); 
		}else{
			return 0;
		}
	}

	function getTasks(){
		$this->db->select('*');
		$this->db->where('pr_task_masterdata.create_date >=',date('Y-m-01'));
		$this->db->where('pr_task_masterdata.create_date <=',date('Y-m-d'));
		$query = $this->db->get('pr_task_masterdata'); 
		if($query->num_rows() > 0){
			return $query->num_rows(); 
		}else{
			return 0;
		}
	}

	function getDueTasks (){
		$this->db->select('*');
		$this->db->where('pr_task_masterdata.due_date >=',date('Y-m-01'));
		$this->db->where('pr_task_masterdata.due_date <=',date('Y-m-d'));
		$this->db->where('pr_task_masterdata.task_status !=','Closed');
		$this->db->where('pr_task_masterdata.status !=','DELETED');
		$query = $this->db->get('pr_task_masterdata'); 
		if($query->num_rows() > 0){
			return $query->num_rows(); 
		}else{
			return 0;
		}
	}

	function getPendingWorkorders(){
		$this->db->select('*');
		$this->db->where('pr_task_workorder.due_date >=',date('Y-m-01'));
		$this->db->where('pr_task_workorder.due_date <=',date('Y-m-d'));
		$this->db->where('pr_task_workorder.status !=','CLOSED');
		$query = $this->db->get('pr_task_workorder'); 
		if($query->num_rows() > 0){
			return $query->num_rows(); 
		}else{
			return 0;
		}
	}

	function getNewCustomers(){
		$this->db->select('*');
		$this->db->where('cm_customerms.create_date >=',date('Y-m-01'));
		$this->db->where('cm_customerms.create_date <=',date('Y-m-d'));
		$query = $this->db->get('cm_customerms'); 
		if($query->num_rows() > 0){
			return $query->num_rows(); 
		}else{
			return 0;
		}
	}

	function getNewSuppliers(){
		$this->db->select('*');
		$this->db->where('cm_supplierms.create_date >=',date('Y-m-01'));
		$this->db->where('cm_supplierms.create_date <=',date('Y-m-d'));
		$query = $this->db->get('cm_supplierms'); 
		if($query->num_rows() > 0){
			return $query->num_rows(); 
		}else{
			return 0;
		}
	}

	function getNewProperties(){
		$this->db->select('*');
		$this->db->where('pr_rent_propertymain.create_date >=',date('Y-m-01'));
		$this->db->where('pr_rent_propertymain.create_date <=',date('Y-m-d'));
		$query = $this->db->get('pr_rent_propertymain'); 
		if($query->num_rows() > 0){
			return $query->num_rows(); 
		}else{
			return 0;
		}
	}

	function getNewUnits(){
		$this->db->select('pr_rent_propertyunit.*');
		$this->db->join('pr_rent_propertymain','pr_rent_propertymain.property_id = pr_rent_propertyunit.property_id');
		$this->db->where('pr_rent_propertymain.create_date >=',date('Y-m-01'));
		$this->db->where('pr_rent_propertymain.create_date <=',date('Y-m-d'));
		$query = $this->db->get('pr_rent_propertyunit'); 
		if($query->num_rows() > 0){
			return $query->num_rows(); 
		}else{
			return 0;
		}
	}

	function getDue($startdate, $enddate){
		$this->db->select('pr_rent_charges_bills.bill_amount,pr_rent_charges_bills.bill_id');
		$this->db->where('pr_rent_charges_bills.due_date >=',$startdate);
		$this->db->where('pr_rent_charges_bills.due_date <=',$enddate);
		$query = $this->db->get('pr_rent_charges_bills'); 
		if($query->num_rows() > 0){
			$bills = $query->result(); 
			$total_bill_amount = 0;
			$total_payments = 0;
			foreach($bills as $bill){
				$total_bill_amount = $total_bill_amount + $bill->bill_amount;

				$this->db->select('pr_rent_charges_paydata.pay_amount');
				$this->db->where('pr_rent_charges_paydata.bill_id',$bill->bill_id);
				$query = $this->db->get('pr_rent_charges_paydata'); 
				if($query->num_rows() > 0){
					$payments = $query->result();
					foreach($payments as $payment){
						$total_payments = $total_payments + $payment->pay_amount;
					}
				}
			}
			return $total_bill_amount - $total_payments;
		}else{
			return 0;
		}
	}

	function getMinBillDate(){
		$this->db->select_min('pr_rent_charges_bills.due_date');
		$query = $this->db->get('pr_rent_charges_bills'); 
		if($query->num_rows() > 0){
			return $query->row()->due_date;
		}else{
			return 0;
		}
	}

	function getCollection($startdate, $enddate, $rental){
		$this->db->select('pr_rent_charges_paydata.pay_amount');
		if($rental == 'yes'){
			$this->db->join('pr_rent_charges','pr_rent_charges.charge_id = pr_rent_charges_paydata.charge_id');
			$this->db->where('pr_rent_charges.occurrence','recurring');
		}
		$this->db->where('pr_rent_charges_paydata.pay_date >=',$startdate);
		$this->db->where('pr_rent_charges_paydata.pay_date <=',$enddate);
		$query = $this->db->get('pr_rent_charges_paydata'); 
		if($query->num_rows() > 0){
			$total_payments = 0;
			$payments = $query->result();
			foreach($payments as $payment){
				$total_payments = $total_payments + $payment->pay_amount;
			}
			return $total_payments;
		}else{
			return 0;
		}
	}

	function getRecurrentChargeTypes(){
		$this->db->select('pr_rent_charges.charge_id, pr_config_renteepaytype.paytype_name, pr_config_renteepaytype.paytype_id');
		$this->db->join('pr_config_renteepaytype','pr_config_renteepaytype.paytype_id = pr_rent_charges.paytype_id');
		$this->db->where('pr_rent_charges.occurrence','recurring');
		$this->db->group_by('pr_rent_charges.paytype_id');
		$query = $this->db->get('pr_rent_charges'); 
		if($query->num_rows() > 0){
			return $query->result();
		}else{
			return false;
		}
	}
}