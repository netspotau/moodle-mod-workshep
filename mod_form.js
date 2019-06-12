/*
 * JavaScript library for WORKSHEP module
 */
M.mod_workshep = {};
M.mod_workshep.mod_form = {};

M.mod_workshep.mod_form.init = function() {
	var uc = $('#id_usecalibration');
	
	uc.change(function(evt) {
		M.mod_workshep.mod_form.updateCalibration(true);
	});
	
	M.mod_workshep.mod_form.updateCalibration(false);
	
}

M.mod_workshep.mod_form.updateCalibration = function(animated) {
	var uc = $('#id_usecalibration');
	var ue = $("#id_useexamples");
	var cp = $("#id_calibrationphase");
	var em = $("#id_examplesmode");
	var ec = $("#id_examplescompare");
	var er = $("#id_examplesreassess");
	
	var checked = uc.prop("checked");
	
	if (checked) {
		ue.prop({checked: true, disabled: true});
		em.prop({disabled: true});
		
		//set up the binding between the two selects
		cp.bind('change', M.mod_workshep.mod_form.updateExamplePhase);
		
		//set up preventing examples from simultaneous compare/reassess
		if (ec.prop('checked') && er.prop('checked')) {
			
			if (animated) {
				
				$("#id_examplesubmissionssettings").removeClass("collapsed");
				var div = er.closest('.fitem');
				div.css('position','relative');
				var bg = $("<div style='background-color: #fff3a5; position: absolute; left:0px; right:0px; top:0px; bottom:0px; z-index:-1;' />");
				div.prepend(bg);
				div.fadeOut(100).fadeIn(100).fadeOut(100).fadeIn(100).fadeOut(100).fadeIn(100, function() {
					er.prop({checked: false});
					bg.fadeOut(2000, function() {
						bg.remove();
					});
				});
			} else {
				er.prop({checked: false});
			}
			
		}
		
		// These two are now mutually exclusive
		ec.bind('change', M.mod_workshep.mod_form.updateExamplesOptions);
		er.bind('change', M.mod_workshep.mod_form.updateExamplesOptions);
		
	} else {
		ue.prop({disabled: false});
		em.prop({disabled: false});
		cp.unbind('change', M.mod_workshep.mod_form.updateExamplePhase);
		er.unbind('change', M.mod_workshep.mod_form.updateExamplesOptions);
		ec.unbind('change', M.mod_workshep.mod_form.updateExamplesOptions);
	}
}

M.mod_workshep.mod_form.updateExamplePhase = function() {
	
	console.log("updateExamplePhase");
	
	var cp = $("#id_calibrationphase");
	var em = $("#id_examplesmode");
	var val = cp.val();
	switch(val) {
	case '10':
		em.val('1');
		break;
	case '20':
		em.val('2');
		break;
	}
	
}

M.mod_workshep.mod_form.updateExamplesOptions = function(evt) {
	
	var ec = $("#id_examplescompare");
	var er = $("#id_examplesreassess");
	var target = $(evt.target);
	
	if (ec.prop('checked') && er.prop('checked')) {
		ec.prop('checked', false);
		er.prop('checked', false);
		target.prop('checked', true);
	}
	
}