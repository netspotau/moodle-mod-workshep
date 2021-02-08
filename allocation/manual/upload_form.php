<?php

require_once(dirname(dirname(dirname(dirname(dirname(__FILE__)))))."/lib/formslib.php");

class workshep_allocation_manual_upload_form extends moodleform {

    //todo: i18n

    function definition() {
        global $workshep;
        
        $mform = $this->_form;
        
        $helptext = get_string('uploadform_helptext','workshepallocation_manual');
        
        $mform->addElement('static', 'helptext', '', $helptext);
        $mform->addElement('filepicker','file','CSV file',null,array('accepted_types' => '.csv'));
        $mform->addElement('hidden','cm',$workshep->cm->id);
        $mform->setType('cm', PARAM_INT);
        $mform->addElement('submit','submit','Submit');
        $mform->addElement('submit','clear','Clear All Allocations', array('onclick' => 'return areYouSure();'));
    }
    
    function toHtml() {
        return $this->_form->toHtml();  
    }

    function exportValue($whatever) {
        return $this->_form->exportValue($whatever);
    }

}   

//team mode
class workshep_allocation_teammode_manual_upload_form extends moodleform {

    //todo: i18n

    function definition() {
        global $workshep;
        
        $mform = $this->_form;
        
        $helptext = get_string('uploadform_teammode_helptext','workshepallocation_manual');
        
        $mform->addElement('static', 'helptext', '', $helptext);
        $mform->addElement('filepicker','file','CSV file',null,array('accepted_types' => '.csv'));
        $mform->addElement('hidden','cm',$workshep->cm->id);
        $mform->setType('cm', PARAM_INT);
        $mform->addElement('submit','submit','Submit');
        $mform->addElement('submit','clear','Clear All Allocations', array('onclick' => 'return areYouSure();'));
    }
    
    function toHtml() {
        return $this->_form->toHtml();  
    }

    function exportValue($whatever) {
        return $this->_form->exportValue($whatever);
    }

}