<?php
/*
Plugin Name: WP Project Manager - Kanban Board
Plugin URI: https://wedevs.com/products/plugins/wp-project-manager-pro/kanban-board/
Description: Kanban board add-on for WP Project Manager
Version: 1.0
Author: weDevs
Author URI: https://wedevs.com
*/

/**
 * Copyright (c) 2016 weDevs. All rights reserved.
 *
 * Released under the GPL license
 * http://www.opensource.org/licenses/gpl-license.php
 *
 * This is an add-on for WordPress
 * http://wordpress.org/
 *
 * **********************************************************************
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * **********************************************************************
 */

// don't call the file directly
if ( !defined( 'ABSPATH' ) ) exit;

if ( is_admin() ) {
    require_once dirname( __FILE__ ) . '/lib/wedevs-updater.php';

    new WeDevs_Plugin_Update_Checker( plugin_basename( __FILE__ ), 'cpm-kanboard' );
}

require_once dirname(__FILE__) . '/kbc-function.php';

/**
 * Kanboard class
 *
 * @author WeDevs
 */
class CPM_Kanboard {

    private $parent_path;

    function __construct() {
        $this->parent_path = dirname( __FILE__ );

        add_filter( 'cpm_project_nav_links', array( $this, 'project_nav_link' ), 10, 2 );
        add_filter( 'cpm_tab_file', array( $this, 'cpm_file_tab' ), 10, 5 );
        add_action( 'wp_enqueue_scripts', array( $this, 'scripts' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'scripts' ) );
        add_action( 'cpm_project_new', array( $this, 'init_section' ), 10, 2 );
        //add_action( 'cpm_task_new', array( $this, 'add_task' ), 10, 4 );

        add_action( 'init', array($this, 'register_post_type') );
        add_action( 'admin_init', array( $this, 'new_task' ), 20 );
        add_action( 'template_redirect', array( $this, 'new_task' ) );
        add_action( 'init', array( $this, 'new_section' ) );
        add_action( 'wp_ajax_update_section_item', array( $this, 'update_section_item' ) );
        add_action( 'wp_ajax_delete_section', array( $this, 'delete_section' ) );
        add_action( 'cpmf_project_tab', array( $this, 'frontend_url' ), 10, 3 );

        add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );

        register_activation_hook( __FILE__, array($this, 'install') );
    }

    function load_textdomain() {
        load_plugin_textdomain( 'kbc', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
    }

    function frontend_url( $project_id, $tab, $action ) {
        if ( $tab == 'kanboard' ) {
            require_once $this->parent_path . '/views/index.php';
        }
    }

    function delete_section() {
        check_ajax_referer('kbc_nonce');
        $delete = wp_delete_post( $_POST['section_id'], true );
        if ( $delete ) {
            wp_send_json_success();
        } else {
            wp_send_json_error();
        }
    }

    function update_section_item() {
        check_ajax_referer('kbc_nonce');

        $tasks_id = isset( $_POST['tasks_id'] ) ? $_POST['tasks_id'] : array();
        $change_task = isset( $_POST['change_task'] ) ? $_POST['change_task'] : 0;
        update_post_meta( $_POST['section_id'], '_tasks_id', $tasks_id );
        if ( $_POST['menu_order'] == 3 ) {
            foreach ( $tasks_id as $task_id ) {
                if( $task_id === $change_task) { 
                    CPM_Task::getInstance()->mark_complete( $task_id );
                }
            }

        } else {
            foreach ( $tasks_id as $task_id ) {
                if( $task_id === $change_task) { 
                    CPM_Task::getInstance()->mark_open( $task_id );
                }
            }
        }


    }

    function install() {

        $this->register_post_type();

        $projects = CPM_Project::getInstance()->get_projects();
        foreach ( $projects as $project ) {

            $args = array(
                'post_parent' => $project->ID,
                'post_status' => 'publish',
                'post_type'   => 'kbc_canboard'
            );

            $kanboard = get_children( $args );

            if ( count( $kanboard ) <= 0 ) {
                $this->init_section( $project->ID );
            }
        }
    }

    function init_section( $project_id, $data = array() ) {

        $backlog_id = $this->create_section( 'Backlog', $project_id, 0 );
        $lists = CPM_Task::getInstance()->get_task_lists( $project_id );

        if ( $lists ) {
            foreach ($lists as $list) {
                $tasks = CPM_Task::getInstance()->get_tasks_by_access_role( $list->ID , $project_id );
                $tasks = cpm_tasks_filter( $tasks );

                if ( count( $tasks['pending'] ) ) {
                    foreach ($tasks['pending'] as $pending_task) {
                        $tasks_id_pending[$pending_task->ID] = $pending_task->ID;
                    }
                }

                if ( count( $tasks['completed'] ) ) {
                    foreach ($tasks['completed'] as $completed_task) {
                        $tasks_id_completed[$completed_task->ID] = $completed_task->ID;
                    }
                }

            }
        }

        if ( isset( $tasks_id_pending ) ) {
            update_post_meta( $backlog_id, '_tasks_id', $tasks_id_pending );
        }

        $this->create_section( __( 'To do', 'kbc' ), $project_id, 1 );
        $this->create_section( __( 'Work in progress', 'kbc' ), $project_id, 2 );
        $done_id = $this->create_section( __( 'Done', 'kbc' ), $project_id, 3 );

        if ( isset( $tasks_id_completed ) ) {
            update_post_meta( $done_id, '_tasks_id', $tasks_id_completed );
        }
    }

    function new_section() {

        if ( !isset( $_POST['kbc_new_section']) ) {
            return;
        }
        if ( !wp_verify_nonce( $_POST['_wpnonce'], 'kbc_task_add' ) ) {
            return;
        }

        if ( empty( $_POST['post_title'] ) || empty( $_POST['project_id'] )  ) {
            return;
        }

        $section_id = $this->create_section( $_POST['post_title'], $_POST['project_id'], $_POST['menu_order'] );
        update_post_meta( $section_id, '_tasks_id', '');
        if ( is_admin() ) {
            $redirect = add_query_arg( array(
                    'page'   => 'cpm_projects',
                    'tab'    => 'kanboard',
                    'action' => 'index',
                    'pid'    => $_POST['project_id']
                ),
                admin_url( 'admin.php' )
            );
        } else {
            $page_id = cpm_get_option('project');
            $redirect = add_query_arg( array(
                'project_id' => $_POST['project_id'],
                'tab'        => 'kanboard',
                'action'     => 'index'
            ), get_permalink( $page_id ) );
        }

        wp_redirect( $redirect );
        exit();
    }

    function create_section( $post_title, $project_id, $menu_order ) {
        $args = array(
            'post_title'  => $post_title,
            'post_status' => 'publish',
            'post_type'   => 'kbc_canboard',
            'post_parent' => $project_id,
            'menu_order'  => $menu_order
        );

        $post_id = wp_insert_post( $args );

        if ( $post_id ) {
            return $post_id;
        } else {
            return false;
        }
    }

    /**
     * Canban post types
     *
     * @return void
     */
    function register_post_type() {

        register_post_type( 'kbc_canboard', array(
            'label'               => __( 'Canboard', 'kbc' ),
            'description'         => __( 'canboard post type', 'kbc' ),
            'public'              => false,
            'show_in_admin_bar'   => false,
            'exclude_from_search' => true,
            'publicly_queryable'  => false,
            'show_in_admin_bar'   => false,
            'show_ui'             => false,
            'show_in_menu'        => true,
            'capability_type'     => 'post',
            'hierarchical'        => false,
            'rewrite'             => array('slug' => ''),
            'query_var'           => true,
            'supports'            => array('title', 'editor', 'comments'),
            'labels' => array(
                'name'               => __( 'Canboard', 'kbc' ),
                'singular_name'      => __( 'canboard', 'kbc' ),
                'menu_name'          => __( 'canboard', 'kbc' ),
                'add_new'            => __( 'Add canboard', 'kbc' ),
                'add_new_item'       => __( 'Add New canboard', 'kbc' ),
                'edit'               => __( 'Edit', 'kbc' ),
                'edit_item'          => __( 'Edit canboard', 'kbc' ),
                'new_item'           => __( 'New canboard', 'kbc' ),
                'view'               => __( 'View canboard', 'kbc' ),
                'view_item'          => __( 'View canboard', 'kbc' ),
                'search_items'       => __( 'Search canboard', 'kbc' ),
                'not_found'          => __( 'No canboard Found', 'kbc' ),
                'not_found_in_trash' => __( 'No canboard Found in Trash', 'kbc' ),
                'parent'             => __( 'Parent canboard', 'kbc' ),
            ),
        ));
    }


    function new_task() {

        if ( !isset( $_POST['submit_kbc_task']) ) {
            return;
        }

        if ( !wp_verify_nonce( $_POST['_wpnonce'], 'kbc_task_add' ) ) {
            return;
        }

        if ( empty( $_POST['task_text'] ) ) {
            return;
        }

        $task_id = CPM_Task::getInstance()->add_task( $_POST['list_id'], $_POST );

        $section_id = $_POST['section_id'];

        $section_meta_id = get_post_meta( $section_id, '_tasks_id', true );
        if ( empty( $section_meta_id ) ) {
            $section_meta_id = array( $task_id );
        } else {
            array_push( $section_meta_id, $task_id );
        }

        update_post_meta( $section_id, '_tasks_id', $section_meta_id );

        if ( is_admin() ) {
            $redirect = add_query_arg( array(
                'page'   => 'cpm_projects',
                'tab'    => 'kanboard',
                'action' => 'index',
                'pid'    => $_POST['project_id'] ),
                admin_url( 'admin.php' )
            );
        } else {

            $page_id = cpm_get_option('project');
            $redirect = add_query_arg( array(
                'project_id' => $_POST['project_id'],
                'tab'        => 'kanboard',
                'action'     => 'index'
            ), get_permalink( $page_id ) );
        }


        wp_redirect( $redirect );
        exit();

    }


    function scripts() {

        wp_enqueue_script('jquery-ui-sortable');
        wp_enqueue_script( 'jquery-ui-dialog' );

        wp_enqueue_script( 'kbc', plugins_url( 'assets/js/kbc.js', __FILE__ ), array('jquery'), false, true );
        wp_localize_script( 'kbc', 'kbc_var', array(
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'kbc_nonce' ),
        ));

        wp_enqueue_style('kbc_style', plugins_url( 'assets/css/kbc.css', __FILE__ ), false, false, false  );
    }

    function cpm_file_tab( $file, $project_id, $page, $tab, $action  ) {

        if ( $tab == 'kanboard' ) {
            $file = dirname( __FILE__ ) . '/views/index.php';
        }

        return $file;
    }

    function project_nav_link( $links, $project_id ) {
      $links[__( 'Kanboard', 'kbc' )] = array('url' => $this->kbc_url_project_kanboard( $project_id ), 'count' => '', 'class' => 'cpmkanboard cpm-sm-col-12' );

      return $links;
    }

    function kbc_url_project_kanboard( $project_id ) {
        if ( is_admin() ) {
            $url = sprintf( '%s?page=cpm_projects&tab=kanboard&action=index&pid=%d', admin_url( 'admin.php' ), $project_id );
        } else {

            $page_id = cpm_get_option('project');
            $url = add_query_arg( array(
                'project_id' => $project_id,
                'tab'        => 'kanboard',
                'action'     => 'index'
            ), get_permalink( $page_id ) );
        }

        return apply_filters( 'cpm_url_kanboard', $url, $project_id );
    }
}


new CPM_Kanboard();