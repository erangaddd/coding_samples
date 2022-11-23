<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

class financialentry_model extends CI_Model {

    function __construct() {
        parent::__construct();
    }

    function get_accounting_mothod(){
		$this->db->select('pr_config_accmethod.method_name');
		$this->db->join('pr_config_accmethod','pr_config_accmethod.method_id = pr_config_systemsettings.method_id');
		
		$query = $this->db->get('pr_config_systemsettings');
		if($query->num_rows() > 0){
			$data= $query->row();
			return $data->method_name;
		}else{
			return '';
		}
      
    }
	function get_bankcash_account(){
		$this->db->select('pr_config_accmethod.bankcash_account');
		$this->db->join('pr_config_accmethod','pr_config_accmethod.method_id = pr_config_systemsettings.method_id');
		
		$query = $this->db->get('pr_config_systemsettings');
		if($query->num_rows() > 0){
			$data= $query->row();
			return $data->bankcash_account;
		}else{
			return 1;
		}
      
    }

	function get_unrealized_incomeaccount(){
		$this->db->select('pr_config_accmethod.asset_account');
		$this->db->join('pr_config_accmethod','pr_config_accmethod.method_id = pr_config_systemsettings.method_id');
		
		$query = $this->db->get('pr_config_systemsettings');
		if($query->num_rows() > 0){
			$data= $query->row();
			return $data->asset_account;
		}else{
			return 0;
		}
      
    }
	 function get_incomesByEntryid($entryid)
    {
      
        $this->db->select('pr_incomedata.*,cm_customerms.first_name,cm_customerms.last_name,pr_rent_leaseagreement.agreement_code,pr_rent_leaseagreement.unit_id,pr_rent_leaseagreement.property_id');
		$this->db->where('pr_incomedata.id',$entryid);
		$this->db->join('cm_customerms','cm_customerms.cus_code=pr_incomedata.cus_code');
        $this->db->join('pr_rent_leaseagreement','pr_rent_leaseagreement.agreement_id=pr_incomedata.agreement_id');
        $ledger_q = $this->db->get('pr_incomedata');
        if ($ledger_q->num_rows() > 0){
            return $ledger_q->row();
        }else
            return false;
    }
	function get_bill_paydataset_byincomeid($entryid)
	{
		  $this->db->select('pr_rent_charges_paydata.*,pr_rent_charges_bills.due_date,pr_config_renteepaytype.ledger_id,pr_config_renteepaytype.receivable_ledger,pr_rent_charges.description');
		$this->db->where('pr_rent_charges_paydata.income_id',$entryid);
		$this->db->join('pr_rent_charges_bills','pr_rent_charges_bills.bill_id=pr_rent_charges_paydata.bill_id');
		$this->db->join('pr_rent_charges','pr_rent_charges.charge_id=pr_rent_charges_paydata.charge_id');
        $this->db->join('pr_config_renteepaytype','pr_config_renteepaytype.paytype_id=pr_rent_charges.paytype_id');
	     $ledger_q = $this->db->get('pr_rent_charges_paydata');
        if ($ledger_q->num_rows() > 0){
            return $ledger_q->result();
        }else
            return false;
	}
	
	function accounttransfers_reciept_insert($entry_id,$receipt_date){
		$method=get_accounting_mothod();//Pr account hleper funcion
		$incomdedata=$this->get_incomesByEntryid($entry_id);
		if($method == 'Cash')
		{
			$drledger=get_unrealized_incomeaccount();//account hleper funcion
			$dataset=$this->get_bill_paydataset_byincomeid($entry_id);
			if($dataset)
			{
				foreach($dataset as $raw)
				{
					if($raw->due_date<=$receipt_date)
					{
						$amount=$raw->receipt_amount-$raw->di_amount;
						if($amount>0)
						{
							$crlist[0]['ledgerid']=$raw->ledger_id;
							$crlist[0]['amount']=$crtot=$amount;
							$drlist[0]['ledgerid']=$drledger;
							$drlist[0]['amount']=$drtot=$amount;
							$narration = $incomdedata->agreement_code.'-'.$raw->description.' Income transfer on recipting   '  ;
							$int_entry=jurnal_entry_pr($crlist,$drlist,$crtot,$drtot,$receipt_date,$narration,0,$incomdedata->unit_id,$incomdedata->agreement_id);				 	
							$this->insert_pay_enties($entry_id,$int_entry,'Income transfer');
							
						}
						
						
					}
				}
			}
		}
		
	}
	
	function accounttransfers_reciept_delete($payid)
	{
		$data=$this->get_payment_entires($payid);
			//echo $paymentdata->id;
			
			if($data){
				foreach($data as $raw)
				{
						
					$entry_id=$raw->entry_id;
					if ( ! $this->db->delete('ac_entry_items', array('entry_id' => $entry_id)))
					{
					   $this->db->trans_rollback();
					}
					if ( ! $this->db->delete('ac_entries', array('id' => $entry_id)))
					{
					  $this->db->trans_rollback();
					}
				}
			}
			$this->db->where('pay_id',$payid);
			$this->db->delete('pr_income_jounalentries');
			
		
	}
	function accounttransfers_reciept_cancel($payid,$receipt_date)
	{
		$data=$this->get_payment_entires($payid);
			//echo $paymentdata->id;
			
			if($data){
				foreach($data as $raw)
				{
						
					$entry_id=$raw->entry_id;
					$this->cancelation_entry($entry_id,$receipt_date);
				}
			}
			$this->db->where('pay_id',$payid);
			$this->db->delete('pr_income_jounalentries');
		
	}
	function get_entry_data($entry_id)
	{
		$this->db->select('*');
		$this->db->where('id',$entry_id);
		$query = $this->db->get('ac_entries'); 
		if ($query->num_rows() > 0){
		return $query->row(); 
		}
		else
		return false;
   
	}
	function get_entry_items($entry_id)
	{
		$this->db->select('*');
		$this->db->where('entry_id',$entry_id);
		$query = $this->db->get('ac_entry_items'); 
		if ($query->num_rows() > 0){
		return $query->result(); 
		}
		else
		return false;
	}
	function insert_pay_enties($pay_id,$entry_id,$type)
	{//
	
			$insert_status = array(
				'pay_id ' => $pay_id,
				'entry_id' => $entry_id,
				'type' => $type,	
			);
			$this->db->insert('pr_income_jounalentries', $insert_status);
			
	
	}
	function get_payment_entires($id)
	{
		$this->db->select('*');
		$this->db->where('pay_id',$id);
		$query = $this->db->get('pr_income_jounalentries'); 
		if ($query->num_rows() > 0){
		return $query->result(); 
		}
		else
		return false;
   
	
	}
	function next_entry_number($entry_type_id)
 	{
 		 $last_no_q = $this->db->query("SELECT MAX(CONVERT(number, SIGNED)) as lastno  FROM  ac_entries where entry_type='".$entry_type_id."'");
 			 //$last_no_q = $this->db->get();
  			if ($row = $last_no_q->row())
  			{
  				 $last_no = $row->lastno;
  				 $last_no++;
  				 return $last_no;
 			 } else {
  			 return 1;
  			}
 	}
	function cancelation_entry($entryid,$date)
	{
		$data_number=$this->next_entry_number(5);
		$entrydata=$this->get_entry_data($entryid);
		if($entrydata)
		{
			$this->db->trans_start();
			$insert_data = array(
				'number' => $entrydata->number,
				'date' => $date,
				'narration' =>  'Cancellation of '.$entrydata->narration,
				'entry_type' => 5,
				'uid' => $entrydata->uid,
				'pid' => $entrydata->pid,
				'dr_total'=>$entrydata->dr_total,
				'cr_total'=>$entrydata->cr_total,
				'agreement_id' => $entrydata->agreement_id,
				'create_date' => date('Y-m-d'),
				
				'status' => 'CONFIRMED',
				'module'=> 'P',
			);
			if ( ! $this->db->insert('ac_entries', $insert_data))
			{
				$this->db->trans_rollback();
				$this->logger->write_message("error", $narration."Error adding since failed inserting entry");
			
				return false;
			} else {
				$entry_id_new = $this->db->insert_id();
			}
			$enryitems=$this->get_entry_items($entryid);
			if($enryitems)
			{
				foreach($enryitems as $raw)
				{
					if($raw->dc=='C')
					$dc='D';
					else
					$dc='C';
					$insert_ledger_data = array(
					'entry_id' => $entry_id_new,
					'ledger_id' => $raw->ledger_id,
					'amount' => $raw->amount,
					'dc' => $dc,
					);
					if ( ! $this->db->insert('ac_entry_items', $insert_ledger_data))
					{
						$this->db->trans_rollback();
						$this->logger->write_message("error", $narration."Error adding since failed inserting entry Items");
					
						return false;
					}
					
					
				}
			}
		}
		
		
				
		
	}
	function jurnal_entry_pr($crlist,$drlist,$crtot,$drtot,$date,$narration,$p_id,$u_id,$agreement_id)
	{
		$data_number=$this->next_entry_number(4);
		
		$this->db->trans_start();
			$insert_data = array(
				'number' => $data_number,
				'date' => $date,
				'narration' => $narration,
				'entry_type' => 4,
				'uid' =>$u_id,
				'pid' =>$p_id,
				'agreement_id' =>$agreement_id,
				'create_date' => date('Y-m-d'),
				'status' => 'CONFIRMED',
				'module'=> 'P',
			);
			if ( ! $this->db->insert('ac_entries', $insert_data))
			{
				$this->db->trans_rollback();
				$this->logger->write_message("error", $narration."Error adding since failed inserting entry");
			
				return false;
			} else {
				$entry_id = $this->db->insert_id();
			}
				
			for($i=0; $i<count($crlist); $i++)
			{
				$insert_ledger_data = array(
					'entry_id' => $entry_id,
					'ledger_id' => $crlist[$i]['ledgerid'],
					'amount' => $crlist[$i]['amount'],
					'dc' => 'C',
				);
				if ( ! $this->db->insert('ac_entry_items', $insert_ledger_data))
				{
					$this->db->trans_rollback();
					$this->logger->write_message("error", $narration."Error adding since failed inserting entry Items");
				
					return false;
				}
			}
			for($i=0; $i<count($drlist); $i++)
			{
				$insert_ledger_data = array(
					'entry_id' => $entry_id,
					'ledger_id' => $drlist[$i]['ledgerid'],
					'amount' => $drlist[$i]['amount'],
					'dc' => 'D',
				);
				if ( ! $this->db->insert('ac_entry_items', $insert_ledger_data))
				{
					$this->db->trans_rollback();
					$this->logger->write_message("error", $narration."Error adding since failed inserting entry Items");
				
					return false;
				}
			}
			/* Adding ledger accounts */
			

			/* Updating Debit and Credit Total in ac_entries table */
			$update_data = array(
				'dr_total' => $crtot,
				'cr_total' => $drtot,
			);
			if ( ! $this->db->where('id', $entry_id)->update('ac_entries', $update_data))
			{
				$this->db->trans_rollback();
				$this->logger->write_message("error", $narration."Error Updating since failed inserting entry Items");
				$this->template->load('template', 'entry/add', $data);
				return false;
			}
			$insert_status = array(
				'entry_id' => $entry_id,
				'status' => 'CONFIRM',
			);
			if ( ! $this->db->insert('ac_entry_status', $insert_status))
			{
				$this->db->trans_rollback();
				$this->messages->add('Error Inserting Entry Status.', 'error');
				$this->logger->write_message("error", "Error Entry Status " . $current_entry_type['name'] . " Entry number " . full_entry_number($entry_type_id, $data_number) . " since failed updating debit and credit total");
				$this->template->load('template', 'entry/add', $data);
				return false;
			}
			
			/* Success */
			$this->db->trans_complete();
			return $entry_id;
	}
	
	
}