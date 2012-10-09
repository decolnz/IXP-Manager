<?php

/*
 * Copyright (C) 2009-2012 Internet Neutral Exchange Association Limited.
 * All Rights Reserved.
 *
 * This file is part of IXP Manager.
 *
 * IXP Manager is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by the Free
 * Software Foundation, version v2.0 of the License.
 *
 * IXP Manager is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for
 * more details.
 *
 * You should have received a copy of the GNU General Public License v2.0
 * along with IXP Manager.  If not, see:
 *
 * http://www.gnu.org/licenses/gpl-2.0.html
 */


/**
 * Controller: Statistics / graphs
 *
 * @author     Barry O'Donovan <barry@opensolutions.ie>
 * @category   INEX
 * @package    INEX_Controller
 * @copyright  Copyright (c) 2009 - 2012, Internet Neutral Exchange Association Ltd
 * @license    http://www.gnu.org/licenses/gpl-2.0.html GNU GPL V2.0
 */
class StatisticsController extends INEX_Controller_AuthRequiredAction
{

    public function preDispatch()
    {}

    
    public function listAction()
    {
        $this->assertPrivilege( \Entities\User::AUTH_SUPERUSER, true );
        $this->view->custs = $this->getD2EM()->getRepository( '\\Entities\\Customer')->getCurrentActive( true, true, true );
    }
    
    public function leagueTableAction()
    {
        $this->assertPrivilege( \Entities\User::AUTH_SUPERUSER, true );
        
        $this->view->metrics = $metrics = [
            'Total'   => 'data',
            'Max'     => 'max',
            'Average' => 'average'
        ];

        $metric = $this->getParam( 'metric', $metrics['Total'] );
        if( !in_array( $metric, $metrics ) )
            $metric = $metrics['Total'];
        $this->view->metric     = $metric;
        
        $day = $this->getParam( 'day', date( 'Y-m-d' ) );
        if( !Zend_Date::isDate( $day, 'Y-m-d' ) )
            $day = date( 'Y-m-d' );
        $this->view->day = $day = new \DateTime( $day );
        
        $category = $this->_setCategory();
                
        $this->view->trafficDaily = $this->getD2EM()->getRepository( '\\Entities\\TrafficDaily' )->load( $day, $category );
    }
    
    
    public function publicAction()
    {
        // get the available graphs
        foreach( $this->_options['mrtg']['traffic_graphs'] as $g )
        {
            $p = explode( '::', $g );
            $graphs[$p[0]] = $p[1];
            $images[]      = $p[0];
        }
        $this->view->graphs     = $graphs;
        
        $graph = $this->getParam( 'graph', $images[0] );
        if( !in_array( $graph, $images ) )
            $graph = $images[0];
        $this->view->graph      = $graph;
        
        $category = $this->_setCategory();
    
        $stats = array();
        foreach( INEX_Mrtg::$PERIODS as $period )
        {
            $mrtg = new INEX_Mrtg( $this->_options['mrtg']['path'] . '/ixp_peering-' . $graph . '-' . $category . '.log' );
            $stats[$period] = $mrtg->getValues( $period, $category );
        }
        $this->view->stats      = $stats;
        
        $this->view->periods    = INEX_Mrtg::$PERIODS;
    }
    
    public function trunksAction()
    {
        // get the available graphs
        foreach( $this->_options['mrtg']['trunk_graphs'] as $g )
        {
            $p = explode( '::', $g );
            $graphs[$p[0]] = $p[1];
            $images[]      = $p[0];
        }
        $this->view->graphs  = $graphs;
        
        $graph = $this->getParam( 'trunk', $images[0] );
        if( !in_array( $graph, $images ) )
            $graph = $images[0];
        $this->view->graph   = $graph;
        
        $stats = array();
        foreach( INEX_Mrtg::$PERIODS as $period )
        {
            $mrtg = new INEX_Mrtg( $this->_options['mrtg']['path'] . '/trunks/' . $graph . '.log' );
            $stats[$period] = $mrtg->getValues( $period, INEX_Mrtg::CATEGORY_BITS );
        }
        $this->view->stats   = $stats;
        
        $this->view->periods = INEX_Mrtg::$PERIODS;
    }
    
    public function switchesAction()
    {
        $switches = $this->view->switches
            = $this->getD2EM()->getRepository( '\\Entities\\Switcher' )->getNames( true, \Entities\Switcher::TYPE_SWITCH );
    
        $switch = $this->getParam( 'switch', array_keys( $switches )[0] );
        if( !in_array( $switch, array_keys( $switches ) ) )
            $switch = array_keys( $switches )[0];
        $this->view->switch     = $switch;
        
        $category = $this->_setCategory();
        $this->_setPeriod();
        
        $stats = array();
        foreach( INEX_Mrtg::$PERIODS as $period )
        {
            $mrtg = new INEX_Mrtg(
                $this->_options['mrtg']['path'] . '/switches/' . 'switch-aggregate-'
                    . $switches[$switch] . '-' . $category . '.log'
            );
    
            $stats[$period] = $mrtg->getValues( $period, $category );
        }
        $this->view->stats      = $stats;
        
    }
    
    
    public function membersAction()
    {
        $this->assertPrivilege( \Entities\User::AUTH_SUPERUSER, true );
        
        $this->view->infras = $infras = INEX_Mrtg::$INFRASTRUCTURES_TEXT;
        $this->view->infra  = $infra  = $this->getParam( 'infra', 'aggregate' );

        if( $infra != 'aggregate' && !in_array( $infra, $infras ) )
            $infra = 'aggregate';
        
        $this->_setCategory();
        $this->_setPeriod();
        $this->view->custs = $this->getD2EM()->getRepository( '\\Entities\\Customer' )->getCurrentActive( false, true, true );
    }
    
    public function memberAction()
    {
        if( $this->getUser()->getPrivs() < \Entities\User::AUTH_SUPERUSER )
            $shortname = $this->getCustomer()->getShortname();
        else
            $shortname = $this->getParam( 'shortname', $this->getCustomer()->getShortname() );
    
        $this->view->cust = $cust = $this->loadCustomerByShortname( $shortname );  // redirects on failure
        
        $this->_setCategory();
    }
    
    public function memberDrilldownAction()
    {
        $category = $this->_setCategory();
        $this->view->monitorindex = $monitorindex = $this->getParam( 'monitorindex', 1 );
        
        if( $this->getUser()->getPrivs() != \Entities\User::AUTH_SUPERUSER )
            $shortname = $this->getCustomer()->getShortname();
        else
            $shortname = $this->getParam( 'shortname', $this->getCustomer()->getShortname() );
    
        $this->view->cust = $cust = $this->loadCustomerByShortname( $shortname );  // redirects on failure
    
        if( $monitorindex != 'aggregate' )
        {
            $vint = false;
            $pi = null;
            foreach( $this->getCustomer()->getVirtualInterfaces() as $vi )
            {
                foreach( $vi->getPhysicalInterfaces() as $pi )
                {
                    if( $pi->getMonitorindex() == $monitorindex )
                    {
                        $vint = $vi;
                        break 2;
                    }
                }
            }
            
            if( !$vint )
                throw new INEX_Exception( 'Member statistics drilldown requested for unknown monitor index' );
    
            $this->view->switchname = $pi->getSwitchPort()->getSwitcher()->getName();
            $this->view->portname   = $pi->getSwitchPort()->getName();
        }
        else
        {
            $this->view->switchname = '';
            $this->view->portname   = '';
        }
    
        $this->view->periods      = INEX_Mrtg::$PERIODS;
    
        $stats = array();
        foreach( INEX_Mrtg::$PERIODS as $period )
        {
            $mrtg = new INEX_Mrtg(
                INEX_Mrtg::getMrtgFilePath( $this->_options['mrtg']['path'] . '/members', 'LOG', $monitorindex, $category, $cust->getShortname() )
            );
    
            $stats[$period] = $mrtg->getValues( $period, $this->view->category );
        }
        $this->view->stats     = $stats;
    
        if( $this->_request->getParam( 'mini', false ) )
        {
            Zend_Controller_Action_HelperBroker::removeHelper( 'viewRenderer' );
            $this->view->display( 'statistics/member-drilldown-mini.phtml' );
        }
    }
    
    /**
     * sFlow Peer to Peer statistics
     */
    public function p2pAction()
    {
        if( $this->getUser()->getPrivs() != \Entities\User::AUTH_SUPERUSER )
            $shortname = $this->getCustomer()->getShortname();
        else
            $shortname = $this->getParam( 'shortname', $this->getCustomer()->getShortname() );
    
        $this->view->cust = $cust = $this->loadCustomerByShortname( $shortname );  // redirects on failure

        $category = $this->_setCategory();
        $period   = $this->_setPeriod();
        $infra    = $this->_setInfrastructure();
        $proto    = $this->_setProtocol();
        $dvid     = $this->view->dvid = $this->getParam( 'dvid', false );
    
        // find the possible virtual interfaces that this customer peers with
        $vints = [];
        foreach( $cust->getVirtualInterfaces() as $vi )
        {
            $enabled = false;
            foreach( $vi->getVlanInterfaces() as $vli )
            {
                $fn = "getIpv{$proto}enabled";
                if( $vli->$fn() )
                {
                    $enabled = true;
                    break;
                }
            }
            
            if( !$enabled )
                continue;
            
            foreach( $vi->getPhysicalInterfaces() as $pi )
            {
                if( $pi->getSwitchPort()->getSwitcher()->getInfrastructure() == $infra )
                    $vints[ $vi->getId() ] = $vi;
            }
        }
            
        $this->view->vints = $vints;
        $this->view->customersWithVirtualInterfaces = false;
        
        if( count( $vints ) )
        {
            if( count( $vints ) > 1 )
            {
                $interfaces = array();
                foreach( $vints as $vi )
                    $interfaces[] = $vi->getId();
    
                $interface = $this->view->interface = $this->getParam( 'interface', $interfaces[0] );
                if( !in_array( $interface, $interfaces ) )
                    $interface = $this->view->interface = $interfaces[0];
    
                $this->view->svid = $interface;
            }
            else
                $this->view->svid = $vints[ ( array_keys( $vints )[0] ) ]->getId();
    
            // find the possible virtual interfaces that this customer peers with
            $dql = "SELECT c.id AS cid, c.name AS cname, c.shortname AS cshortname,
                        vi.id AS viid, pi.id AS piid, vli.id AS vlidid, sp.id AS spid, s.id AS sid
            
                    FROM \\Entities\\Customer c
                        LEFT JOIN c.VirtualInterfaces vi
                        LEFT JOIN vi.PhysicalInterfaces pi
                        LEFT JOIN vi.VlanInterfaces vli
                        LEFT JOIN pi.SwitchPort sp
                        LEFT JOIN sp.Switcher s
                        
                    WHERE
                        s.infrastructure = {$infra}
                        AND vli.ipv{$proto}enabled = 1
                        AND c.shortname != ?1
                        AND c.type IN ( " . \Entities\Customer::TYPE_FULL . ", " . \Entities\Customer::TYPE_PROBONO . " )
                        AND c.status = " . \Entities\Customer::STATUS_NORMAL . "
                        AND ( c.dateleave IS NULL OR c.dateleave = '0000-00-00' )
                        AND pi.status = " . \Entities\PhysicalInterface::STATUS_CONNECTED;
                        
            
    
            if( $dvid )
                $dql .= " AND WHERE vi.id = {$dvid}";
            
            $dql .= "  ORDER BY c.name";
            
           
            $q  = $this->getD2EM()->createQuery( $dql )->setParameter( 1, $shortname );
            
            $this->view->customersWithVirtualInterfaces = $q->getArrayResult();
        }
    
        if( $dvid )
        {
            Zend_Controller_Action_HelperBroker::removeHelper( 'viewRenderer' );
            $this->view->display( 'statistics/p2p-single.phtml' );
        }
    }
    
    /**
     * Utility function to extract, validate (and default if necessary) a
     * category from request parameters.
     *
     * Sets the view variables `$category` to the chosen / defaulted category
     * and `$categories` to all available categories.
     *
     * @param string $pname The name of the parameter to extract the category from
     * @return string The chosen / defaulted category
     */
    protected function _setCategory( $pname = 'category' )
    {
        $category = $this->getParam( $pname, INEX_Mrtg::$CATEGORIES['Bits'] );
        if( !in_array( $category, INEX_Mrtg::$CATEGORIES ) )
            $category = INEX_Mrtg::$CATEGORIES['Bits'];
        $this->view->category   = $category;
        $this->view->categories = INEX_Mrtg::$CATEGORIES;
        return $category;
    }
    
    /**
     * Utility function to extract, validate (and default if necessary) a
     * period from request parameters.
     *
     * Sets the view variables `$period` to the chosen / defaulted category
     * and `$periods` to all available periods.
     *
     * @param string $pname The name of the parameter to extract the period from
     * @return string The chosen / defaulted period
     */
    protected function _setPeriod( $pname = 'period' )
    {
        $period = $this->getParam( $pname, INEX_Mrtg::$PERIODS['Day'] );
        if( !in_array( $period, INEX_Mrtg::$PERIODS ) )
            $period = INEX_Mrtg::$PERIODS['Day'];
        $this->view->period     = $period;
        $this->view->periods    = INEX_Mrtg::$PERIODS;
        return $period;
    }
    
    /**
     * Utility function to extract, validate (and default if necessary) an
     * infrastructure from request parameters.
     *
     * Sets the view variables `$infra` to the chosen / defaulted infrastructure
     * and `$infrastructures` to all available infrastructures.
     *
     * @param string $pname The name of the parameter to extract the infrastructure from
     * @return string The chosen / defaulted infrastructure
     */
    protected function _setInfrastructure( $pname = 'infra' )
    {
        $infra = $this->view->infra = $this->getParam( $pname, 1 );
        if( !in_array( $infra, INEX_Mrtg::$INFRASTRUCTURES ) )
            $infra = INEX_Mrtg::INFRASTRUCTURE_PRIMARY;
        
        $this->view->infra      = $infra;
        $this->view->infrastructures = INEX_Mrtg::$INFRASTRUCTURES;
        
        return $infra;
    }
    
    
    /**
     * Utility function to extract, validate (and default if necessary) a
     * protocol from request parameters.
     *
     * Sets the view variables `$proto` to the chosen / defaulted protocol
     * and `$protocols` to all available protocols.
     *
     * @param string $pname The name of the parameter to extract the protocol from
     * @return string The chosen / defaulted protocol
     */
    protected function _setProtocol( $pname = 'proto' )
    {
        $proto = $this->getParam( $pname, 4 );
        if( !in_array( $proto, INEX_Mrtg::$PROTOCOLS ) )
            $proto = INEX_Mrtg::PROTOCOL_IPV4;
        
        $this->view->proto     = $proto;
        $this->view->protocols = INEX_Mrtg::$PROTOCOLS;
            
        return $proto;
    }
    
    
    /*
    public function ninetyFifthAction()
    {
        $month = $this->_request->getParam( 'month', date( 'Y-m-01' ) );
    
        $cost = $this->_request->getParam( 'cost', "20.00" );
        if( !is_numeric( $cost ) )
            $cost = "20.00";
        $this->view->cost = $cost;
    
        $months = array();
        for( $year = 2010; $year <= date( 'Y' ); $year++ )
            for( $mth = ( $year == 2010 ? 4 : 1 ); $mth <= ( $year == 2010 ? date('n') : 12 ); $mth++ )
            {
                $ts = mktime( 0, 0, 0, $mth, 1, $year );
                $months[date( 'M Y', $ts )] = date( 'Y-m-01', $ts );
            }
    
            $this->view->months = $months;
    
            if( in_array( $month, array_values( $months ) ) )
                $this->view->month = $month;
            else
                $this->view->month = date( 'Y-m-01' );
    
            // load values from the database
            $traffic95thMonthly = Doctrine_Query::create()
            ->from( 'Traffic95thMonthly tf' )
            ->leftJoin( 'tf.Cust c' )
            ->where( 'month = ?', $month )
            ->execute()
            ->toArray();
    
            foreach( $traffic95thMonthly as $index => $row )
                $traffic95thMonthly[$index]['cost'] = sprintf( "%0.2f", $row['max_95th'] / 1024 / 1024 * $cost );
    
            $this->view->traffic95thMonthly = $traffic95thMonthly;
    
            $this->view->display( 'customer' . DIRECTORY_SEPARATOR . 'ninety-fifth.tpl' );
    }
    */

}

