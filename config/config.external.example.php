<?php

/*
 * Copy this file to config/config.external.php and fill in the four
 * read-only database connections. Never commit config.external.php.
 */
return [
    'external_sources' => [
        'stratast_support' => [
            'enabled' => false,
            'label' => 'Strata Support Desk',
            'url' => 'http://support.stratastaffglobal.com/',
            'connection' => ['host' => '', 'port' => 3306, 'database' => '', 'username' => '', 'password' => ''],
            'tables' => [
                'tickets' => '92CpH3vC_psmsc_tickets', 'customers' => '92CpH3vC_psmsc_customers',
                'statuses' => '92CpH3vC_psmsc_statuses', 'priorities' => '92CpH3vC_psmsc_priorities',
                'categories' => '92CpH3vC_psmsc_categories', 'threads' => '92CpH3vC_psmsc_threads',
            ],
            'list_function' => 'fetch_stratast_support_tickets',
            'thread_function' => 'fetch_stratast_support_thread',
            'filter' => ['customer_domains' => ['jamesons.com.au'], 'customer_names' => ['Leo Sunga', 'Leonard Sunga', 'Sheena Magdaraog', 'Trisha Balingit']],
        ],
        'stratast_escalations' => [
            'enabled' => false,
            'label' => 'HR Escalation Desk',
            'url' => 'https://escalations.stratastaffglobal.com/',
            'connection' => ['host' => '', 'port' => 3306, 'database' => '', 'username' => '', 'password' => ''],
            'tables' => [
                'tickets' => 'Qeby0uHyy_psmsc_tickets', 'customers' => 'Qeby0uHyy_psmsc_customers',
                'statuses' => 'Qeby0uHyy_psmsc_statuses', 'priorities' => 'Qeby0uHyy_psmsc_priorities',
                'categories' => 'Qeby0uHyy_psmsc_categories', 'threads' => 'Qeby0uHyy_psmsc_threads',
            ],
            'list_function' => 'fetch_stratast_escalations_tickets',
            'thread_function' => 'fetch_stratast_escalations_thread',
            'filter' => ['customer_domains' => ['jamesons.com.au'], 'customer_names' => ['Leo Sunga', 'Leonard Sunga', 'Sheena Magdaraog', 'Trisha Balingit']],
        ],
        'stratast_wp346' => [
            'enabled' => false,
            'label' => 'Training Desk',
            'url' => 'https://learning.stratastaffglobal.com/',
            'connection' => ['host' => '', 'port' => 3306, 'database' => '', 'username' => '', 'password' => ''],
            'tables' => [
                'tickets' => 'wpnm_psmsc_tickets', 'customers' => 'wpnm_psmsc_customers',
                'statuses' => 'wpnm_psmsc_statuses', 'priorities' => 'wpnm_psmsc_priorities',
                'categories' => 'wpnm_psmsc_categories', 'threads' => 'wpnm_psmsc_threads',
            ],
            'list_function' => 'fetch_stratast_wp346_tickets',
            'thread_function' => 'fetch_stratast_wp346_thread',
            'filter' => ['customer_domains' => ['jamesons.com.au'], 'customer_names' => ['Leo Sunga', 'Leonard Sunga', 'Sheena Magdaraog', 'Trisha Balingit']],
        ],
        'stratast_requisition' => [
            'enabled' => false,
            'label' => 'Requisition Desk',
            'url' => 'https://requisition.stratastaffglobal.com/',
            'connection' => ['host' => '', 'port' => 3306, 'database' => '', 'username' => '', 'password' => ''],
            'tables' => [
                'tickets' => 'Qeby0uHyy_psmsc_tickets', 'customers' => 'Qeby0uHyy_psmsc_customers',
                'statuses' => 'Qeby0uHyy_psmsc_statuses', 'priorities' => 'Qeby0uHyy_psmsc_priorities',
                'categories' => 'Qeby0uHyy_psmsc_categories', 'threads' => 'Qeby0uHyy_psmsc_threads',
            ],
            'list_function' => 'fetch_stratast_requisition_tickets',
            'thread_function' => 'fetch_stratast_requisition_thread',
            'filter' => ['customer_domains' => ['jamesons.com.au'], 'customer_names' => ['Leo Sunga', 'Leonard Sunga', 'Sheena Magdaraog', 'Trisha Balingit']],
        ],
    ],
];
