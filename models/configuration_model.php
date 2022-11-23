<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

class Configuration_model extends CI_Model {

	function __construct() {
		parent::__construct();
	}
	
	function get_config_systemsettings() {
		$this->db->select('*')->limit('1');
		$query = $this->db->get('pr_config_systemsettings');
		if ($query->num_rows >0) {
				return $query->row();
		}
       
		else
		return false; //if no matching records
	}

	function get_approval_tables($id){
		if($id != ''){
			$this->db->select('*')->where('id',$id);
			$query = $this->db->get('pr_config_approvelevel');
			if ($query->num_rows >0) {
					return $query->row();
			}else{
				return false; //if no matching records`
			}
		}else{
			$this->db->select('*');
			$this->db->order_by('description');
			$query = $this->db->get('pr_config_approvelevel');
			if ($query->num_rows >0) {
					return $query->result();
			}
		
			else
			return false; //if no matching records
		}
		
	}

	function getConfigMenu(){
		$this->db->where('main_id', '1002');
		$this->db->order_by('order_key');
		$query = $this->db->get('cm_menu_sub'); 
		if ($query->num_rows >0) {
			return $query->result();
		}
		else
		return false; //if no matching records
	}

	function getPaymentNotifications($limit, $start){
		$this->db->select('pr_config_notification.*,pr_config_renteepaytype.paytype_name');
		$this->db->join('pr_config_renteepaytype','pr_config_renteepaytype.paytype_id = pr_config_notification.paytype_id');
		$this->db->limit($limit, $start);
		$query = $this->db->get('pr_config_notification');
		if($query->num_rows() > 0){
			return $query->result();
		}else{
			return false;
		}
	}

	function getUnusedPaymentTypes(){
		$sql = 'SELECT pr_config_renteepaytype.* FROM pr_config_renteepaytype 
						WHERE (pr_config_renteepaytype.paytype_id 
						NOT IN (SELECT paytype_id FROM pr_config_notification))';
		$query = $this->db->query($sql);
		if ($query->num_rows > 0) 
			return $query->result();
		else
		return false;
	}

	function getActiveApprovalLevels($table){
		$this->db->where('base_table',$table);
		$this->db->limit('1');
		$query = $this->db->get('pr_config_approvelevel'); 
		if ($query->num_rows >0) {
			$data = $query->row();
			$access_levels = array();
			if($data->need_check == '1'){
				array_push($access_levels,$data->access_type_check);
			}
			if($data->need_confirm == '1'){
				array_push($access_levels,$data->access_type_confirm);
			}
			return $access_levels;
		}
		else
		return false; //if no matching records
	}

	function getChargeConfigData($paytype_id){
		$this->db->select('pr_config_renteepaytype.*');
		$this->db->where('pr_config_renteepaytype.paytype_id',$paytype_id);
		$query = $this->db->get('pr_config_renteepaytype');
		if($query->num_rows() > 0){
			return $query->row();
		}else{
			return false;
		}
	}

	function getAccountingMethod(){
		$this->db->select('pr_config_accmethod.*');
		$this->db->join('pr_config_systemsettings','pr_config_systemsettings.method_id = pr_config_accmethod.method_id');
		$this->db->limit('1');
		$query = $this->db->get('pr_config_accmethod');
		if($query->num_rows() > 0){
			return $query->row();
		}else{
			return false;
		}
	}

	function getAccountingMethods(){
		$this->db->select('pr_config_accmethod.*');
		$query = $this->db->get('pr_config_accmethod');
		if($query->num_rows() > 0){
			return $query->result();
		}else{
			return false;
		}
	}

	function updateDefaultLedger(){
		$data = array(
			'bankcash_account' => $this->input->post('bank_account')
		);
		$this->db->trans_start();
		$this->db->where('pr_config_accmethod.method_id',$this->input->post('method_id'));
		$this->db->update('pr_config_accmethod',$data);
		$this->db->trans_complete();
		if ($this->db->trans_status() === FALSE) {
			$this->db->trans_rollback();
			return false;
		} else {
			$this->db->trans_commit();
			return true;
		}
	}

	function getAllExpenseTypesConfirmed(){
		$this->db->select('pr_config_expensetype.*');
		$this->db->where('pr_config_expensetype.status','CONFIRMED');
		$query = $this->db->get('pr_config_expensetype');
		if($query->num_rows() > 0){
			return $query->result();
		}else{
			return false;
		}
	}

	function deleteExpenseType($id){
		$this->db->where('expense_id',$id);
		$this->db->trans_start();
		$this->db->delete('pr_config_expensetype');
		$this->db->trans_complete();
		if ($this->db->trans_status() === FALSE) {
			$this->db->trans_rollback();
			return false;
		} else {
			$this->db->trans_commit();
			return true;
		}
	}

	function getExpenseTypeById($id){
		$this->db->select('pr_config_expensetype.*');
		$this->db->where('pr_config_expensetype.expense_id', $id);
		$query = $this->db->get('pr_config_expensetype');
		if($query->num_rows() > 0){
			return $query->row();
		}else{
			return false;
		}
	}
}