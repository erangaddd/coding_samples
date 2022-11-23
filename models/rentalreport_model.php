<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

class Rentalreport_model extends CI_Model {

    function __construct() {
		$this->load->model('entry_model');
        parent::__construct();
    }

	 function getPropertyData()
    {
        $this->db->select('pr_rent_propertymain.*,pr_config_proptype.proptype_name');
        $this->db->join('pr_config_proptype','pr_config_proptype.proptype_id = pr_rent_propertymain.property_type');
		$this->db->where('pr_rent_propertymain.status','CONFIRMED');
        $this->db->order_by('pr_rent_propertymain.property_id','DESC');
        $query = $this->db->get('pr_rent_propertymain');
        if($query->num_rows() > 0)
            return $query->result();
        else
            return false;
    }
	function get_chargepeyments($amount,$fromdate,$todate,$property,$agreement,$paytype)
	{
	
		  $this->db->select('SUM(pr_rent_charges_paydata.receipt_amount-pr_rent_charges_paydata.di_amount) as pay_amount,SUM(pr_rent_charges_paydata.di_amount) as diamount,pr_rent_charges_paydata.pay_date,pr_rent_charges_bills.due_date,pr_rent_charges_bills.bill_amount,pr_rent_charges_bills.bill_number,pr_config_renteepaytype.paytype_name,pr_rent_charges.description,cm_customerms.first_name,cm_customerms.last_name,pr_rent_propertymain.property_name,pr_rent_propertyunit.unit_name,pr_incomedata.rct_no');
		$this->db->join('pr_rent_charges_bills','pr_rent_charges_bills.bill_id=pr_rent_charges_paydata.bill_id','left');
		$this->db->join('pr_rent_charges','pr_rent_charges.charge_id=pr_rent_charges_paydata.charge_id','left');
        $this->db->join('pr_config_renteepaytype','pr_config_renteepaytype.paytype_id=pr_rent_charges.paytype_id','left');
		$this->db->join('pr_rent_leaseagreement','pr_rent_leaseagreement.agreement_id=pr_rent_charges_paydata.agreement_id','left');
		$this->db->join('cm_customerms','cm_customerms.cus_code=pr_rent_leaseagreement.master_tenant','left');
		$this->db->join('pr_rent_propertymain','pr_rent_propertymain.property_id=pr_rent_leaseagreement.property_id','left');
		$this->db->join('pr_rent_propertyunit','pr_rent_propertyunit.unit_id=pr_rent_leaseagreement.unit_id','left');
		$this->db->join('pr_incomedata','pr_incomedata.id=pr_rent_charges_paydata.income_id','left');
	    
		 if($agreement!="")
		  {
			  $this->db->where('pr_rent_charges.agreement_id',$agreement);
		  }
		  else
		  {
			  if($property!="")
			 $this->db->where('pr_rent_leaseagreement.property_id',$property);
		  }
		  if($paytype!="")
		   $this->db->where('pr_rent_charges.paytype_id',$paytype);
		     if($fromdate)
		   $this->db->where('pr_rent_charges_paydata.pay_date >=',$fromdate);
		    if($todate)
		   $this->db->where('pr_rent_charges_paydata.pay_date <=',$todate);
		    if($amount)
			$this->db->like('pr_rent_charges_paydata.pay_amount',$amount);
		//	$this->db->group_by('pr_rent_charges_paydata.bill_id');
			$this->db->group_by('pr_rent_charges_paydata.bill_id,pr_rent_charges_paydata.income_id');
			//$this->db->group_by('pr_rent_charges_paydata.pay_id');
			$this->db->order_by('pr_rent_charges_paydata.pay_date');
		 $ledger_q = $this->db->get('pr_rent_charges_paydata');
		 if ($ledger_q->num_rows() > 0){
            return $ledger_q->result();
        }else
            return false;
	}

	function getParkingSpacesByProperty($property_id, $unit_id, $rego){
		$this->db->select('pr_rent_parking.*,pr_rent_propertymain.property_name,pr_rent_propertyunit.unit_name');
        $this->db->join('pr_rent_propertymain','pr_rent_propertymain.property_id = pr_rent_parking.property_id');
		$this->db->join('pr_rent_propertyunit','pr_rent_propertyunit.unit_id = pr_rent_parking.unit_id');
		if($property_id){
			$this->db->where('pr_rent_parking.property_id',$property_id);
		}
		if($unit_id){
			$this->db->where('pr_rent_parking.unit_id',$unit_id);
		}
		if($rego){
			$this->db->like('pr_rent_parking.rego',$rego);
		}
        $this->db->order_by('pr_rent_parking.unit_id','ASC');
        $query = $this->db->get('pr_rent_parking');
        if($query->num_rows() > 0)
            return $query->result();
        else
            return false;
	}
}