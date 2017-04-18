<?php
$task_obj = CPM_Task::getInstance();

if ( cpm_user_can_access( $project_id, 'tdolist_view_private' ) ) {
    $lists = $task_obj->get_task_lists( $project_id, true );
} else {
    $lists = $task_obj->get_task_lists( $project_id );
}

cpm_get_header( __( 'Kanboard', 'cpm' ), $project_id );
$sections = kbc_get_sections( $project_id );


if ( $lists ) {

    foreach ($lists as $list) {
        $lists_dropdown[$list->ID] = $list->post_title;
        $tasks = $task_obj->get_tasks_by_access_role( $list->ID , $project_id );

        $tasks = cpm_tasks_filter( $tasks );

        if ( count( $tasks['pending'] ) ) {
            foreach ($tasks['pending'] as $task) {
                $pending_tasks[$task->ID] = $task->ID;
            }
        }

        if ( count( $tasks['completed'] ) ) {
            foreach ($tasks['completed'] as $task) {
                $completed_tasks[$task->ID] = $task->ID;
            }
        }

    }
}

$completed_tasks = isset( $completed_tasks ) ? $completed_tasks : array();
$pending_tasks = isset( $pending_tasks ) ? $pending_tasks : array();

?>



<?php
foreach ( $sections as $key => $section ) {
    if( $section->menu_order == 0 || $section->menu_order == 3  ) {
        continue;
    }

    $tasks_id = get_post_meta( $section->ID, '_tasks_id', true );
    $tasks_id = empty( $tasks_id ) ? array() : $tasks_id;

    foreach ( $tasks_id as $key => $task_id ) {
        if( in_array( $task_id, $pending_tasks ) ) {
            unset( $pending_tasks[$task_id] );
        }
    }
}
?>
<div class="kbc-body-wrap">
<?php
foreach ( $sections as $key => $section ) {
    $tasks_id = get_post_meta( $section->ID, '_tasks_id', true );
    $tasks_id = empty( $tasks_id ) ? array() : $tasks_id;
    $tasks_id = ( $section->menu_order == 0 ) ? $pending_tasks : $tasks_id; //array_unique( array_merge( $tasks_id, $pending_tasks ) ) : $tasks_id;
    $tasks_id = ( $section->menu_order == 3 ) ? $completed_tasks : $tasks_id;//array_unique( array_merge( $tasks_id, $completed_tasks ) ) : $tasks_id;

    $add_icon = ( $section->menu_order != 3 ) ? '+' : '';
    $class = ( $section->menu_order != 3 ) ? 'kbc-new-task' : '';
    $section_cross = ( $section->menu_order > 3 ) ? 'x' : '';
    $section_cross_class = ( $section->menu_order > 3 ) ? 'kbc-delete-section' : '';
    $last_menu_order = $section->menu_order;
    $add_more_class = ( $section->menu_order > 3 ) ? 'kbc-add-more-left' : 'kbc-add-more';

    ?>
    <div class="kbc-col-wrap">
        <h3 class="kbc-section-title">
            <?php echo $section->post_title; ?>
        </h3>

        <ul class="kbc-sortable connectedSortable" data-menu_order="<?php echo $section->menu_order; ?>" data-section_id="<?php echo $section->ID; ?>">

            <?php

            foreach ( $tasks_id as $key => $task_id ) {

                if ( 'publish' != get_post_status ( $task_id ) ) {
                    continue;
                }
                if ( $section->menu_order != 3 && in_array( $task_id, $completed_tasks ) ) {
                    continue;
                }

                $tasks = CPM_Task::getInstance()->get_task( $task_id );
                $url = cpm_url_single_task( $project_id, $tasks->post_parent, $task_id );
                ?>
                <li class="kbc-li-text" data-task_id="<?php echo $task_id; ?>">
                    <a href="<?php echo $url; ?>" data-id="<?php echo $task_id; ?>"><?php echo $tasks->post_title; ?></a>
                </li>
                <?php
            }
            ?>

        </ul>
        <?php
                    if ( $section->menu_order != 3 ) {
                        ?>
                        <div class="<?php echo $class .' '. $add_more_class; ?>" data-section_id="<?php echo $section->ID; ?>"><?php _e('+ Add More Task', 'kbc' ); ?></div>
                        <?php
                        if ( $section->menu_order > 3 ) {
                            ?>
                            <span class="kbc-close <?php echo $section_cross_class; ?>" data-section_id="<?php echo $section->ID; ?>"><?php _e('Delete', 'kbc' ); ?></span>
                            <?php
                        }
                    }
                ?>

    </div>

    <div class="kbc-task-dialog-init kbc-task-dialog-<?php echo $section->ID; ?>" style="display:none; z-index:999;" title="<?php _e( 'Start a new task', 'kbc' ); ?>">

        <form action="" method="post" class="cpm-task-form">
            <select name="list_id">
            <?php $lists_dropdown = isset( $lists_dropdown ) ? $lists_dropdown : array(); ?>
            <?php
            foreach ( $lists_dropdown as $id => $todo_title ) {
                ?>
                <option value="<?php echo $id; ?>"><?php echo $todo_title; ?> </option>
                <?php
            }
            ?>
            <input type="hidden" name="project_id" value="<?php echo $project_id; ?>">
            <input type="hidden" name="section_id" value="<?php echo $section->ID; ?>">
            <input type="hidden" name="action" value="cpm_task_add">
            <input type="hidden" name="single" value="0">
            <?php wp_nonce_field( 'kbc_task_add' ); ?>

            <div>
                <input type="text" placeholder="<?php esc_attr_e( 'To-do Title', 'kbc' ) ?>" value="" name="task_title" required>
            </div>
            <div class="item content">
                <textarea name="task_text" class="todo_content" cols="40" placeholder="<?php esc_attr_e( 'To-do details', 'kbc' ) ?>" rows="1"></textarea>
            </div>

            <div class="item date">
                <?php if(cpm_get_option( 'task_start_field' ) == 'on') { ?>
                    <div class="cpm-task-start-field">
                        <label><?php _e('Start date', 'kbc'); ?></label>
                        <input  type="text" autocomplete="off" class="datepicker" placeholder="<?php esc_attr_e( 'Start date', 'kbc' ); ?>" value="" name="task_start" />
                    </div>
                <?php } ?>

                <div class="cpm-task-due-field">
                    <label><?php _e('Due date', 'kbc'); ?></label>
                    <input type="text" autocomplete="off" class="datepicker" placeholder="<?php esc_attr_e( 'Due date', 'kbc' ); ?>" value="" name="task_due" />
                </div>
            </div>

            <div class="item user">
                <?php cpm_task_assign_dropdown( $project_id, '-1' ); ?>
            </div>

            <?php if( cpm_user_can_access( $project_id, 'todo_view_private' ) ) { ?>
                <div class="cpm-make-privacy">
                    <label>
                        <input type="checkbox"  value="yes" name="task_privacy">
                        <?php _e( 'Private', 'kbc' ); ?>
                    </label>
                </div>
            <?php } ?>

            <div class="item submit">
                <input type="hidden" name="project_id" value="<?php echo $project_id; ?>">
                <span class="cpm-new-task-spinner"></span>
                <input type="submit" class="button-primary" name="submit_kbc_task" value="<?php _e( 'Add this to-do', 'kbc' ) ?>">
            </div>
        </form>
    </div>

    <?php
}
?>
    <div class="kbc-clear"></div>
</div>
<div><a class="button-primary kbc-add-section-btn" href="#"><?php _e( 'Add new section', 'kbc' ); ?></a></div>


<div class="kbc-section" style="display:none; z-index:999;" title="<?php _e( 'Start a section', 'kbd' ); ?>">
    <form action="" method="post" class="cpm-task-form">
        <?php wp_nonce_field( 'kbc_task_add' ); ?>
        <input type="text" name="post_title" placeholder="<?php _e( 'Section Name', 'kbc' ); ?>">
        <input type="hidden" value="<?php echo $last_menu_order+1; ?>" name="menu_order">
        <input type="hidden" name="project_id" value="<?php echo $project_id; ?>">
        <input type="submit" class="button-primary" value="Submit" name="kbc_new_section">
    </form>

</div>


<script type="text/javascript">
    jQuery(function($) {
        $( ".kbc-task-dialog-init, .kbc-section" ).dialog({
            autoOpen: false,
            modal: true,
            dialogClass: 'kbc-ui-dialog',
            width: 485,
            height: 425,
            //position:['middle', 100],

            zIndex: 999,

        });
        $( ".kbc-section" ).dialog({
            autoOpen: false,
            modal: true,
            dialogClass: 'kbc-ui-dialog',
            width: 485,
            height: 200,
            //position:['middle', 100],

            zIndex: 999,

        });
    });

</script>