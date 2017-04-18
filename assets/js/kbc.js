(function($) {
	var kbc = {
		init: function() {
			$( '.kbc-new-task' ).on('click', this.openDialog);
			$( '.kbc-add-section-btn' ).on('click', this.openDialogSection);
			$('.kbc-delete-section').on('click', this.sectionDelete);
		},

		sectionDelete: function(e) {
			e.preventDefault();
			if ( !confirm( 'Are your sure!' ) ) {
				return;
			}

			var self = $(this),
				data = {
					section_id : self.data('section_id'),
					action : 'delete_section',
					_wpnonce: kbc_var.nonce,
				};

			$.post( kbc_var.ajaxurl, data, function( res ) {
	        	if ( res.success ) {
	        		window.location.reload();
	        	}
	        });
		},

		openDialog: function(e) {
			e.preventDefault();

			var self = $(this),
				section_id = self.data('section_id'),
				selector = 'kbc-task-dialog-' + section_id;

            $('.'+selector).dialog( "open" );
            $(".datepicker").datepicker();
		},

		openDialogSection: function(e) {
			e.preventDefault();
            $( ".kbc-section" ).dialog( "open" );
		}
	}
	kbc.init();

	$( ".kbc-col-wrap .kbc-sortable" ).sortable({
        connectWith: ".connectedSortable",

        update: function( event, ui ) {

        	if( ui.sender === null ) {
        		var self = ui.item,
        			ul = self.closest('ul'),
        			menu_order = ul.data('menu_order'),
        			section_id = ul.data('section_id'),
        			li = ul.find('li[data-task_id]'),
                                change_task = self.find('a[data-id]').data('id') ,
        			tasks_id = [];

	        	$.each( li, function( index, liattr ) {
	        		tasks_id.push( $(liattr).data('task_id') );

	        	});

	        	var data = {
        			action: 'update_section_item',
        			section_id: section_id,
        			menu_order: menu_order,
        			tasks_id : tasks_id,
                                change_task : change_task,
        			_wpnonce : kbc_var.nonce
        		}

				$.post( kbc_var.ajaxurl, data, function( res ) {

	        	});

        	} else {

                var sender_self    = ui.sender,
                    sender_ul          = sender_self.closest('ul'),
                    sender_menu_order  = sender_ul.data('menu_order'),
                    sender_enection_id = sender_ul.data('section_id'),
                    sender_li          = sender_ul.find('li[data-task_id]'),
                    sender_tasks_id    = [];

        		$.each( sender_li, function( index, liattr ) {
        			sender_tasks_id.push( $(liattr).data('task_id') );

        		});

        		var data = {
        			action: 'update_section_item',
        			section_id: sender_enection_id,
        			menu_order: sender_menu_order,
        			tasks_id : sender_tasks_id,
        			_wpnonce : kbc_var.nonce
        		}

				$.post( kbc_var.ajaxurl, data, function( res ) {

        		});
        	}
        },

    });

})(jQuery);