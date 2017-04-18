<?php
function kbc_get_sections( $project_id ) {
    $args = array(
        'post_parent' => $project_id, 
        'numberposts' => -1, 
        'post_type' => 'kbc_canboard', 
        'order' => 'ASC', 
        'orderby' => 'menu_order',
        'post_status' => 'publish'
    );
    $section = get_children( $args );
    return $section;
}

function kbc_get_section_task_id( $section_id ) {
    return get_post_meta( $section_id, '_task_id', true );
}