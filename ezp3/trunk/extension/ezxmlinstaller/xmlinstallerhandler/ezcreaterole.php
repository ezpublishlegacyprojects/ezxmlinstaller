<?php
include_once( 'extension/ezxmlinstaller/classes/ezxmlinstallerhandler.php' );
include_once( 'kernel/classes/ezrole.php' );
class eZCreateRole extends eZXMLInstallerHandler
{

    function eZCreateRole( )
    {
    }

    function execute( $xml )
    {
        $roleList = $xml->getElementsByTagName( 'Role' );
        $refArray = array();
        foreach ( $roleList as $roleNode )
        {

            $roleName              = $roleNode->attributeValue( 'name' );
            $createRoleIfNotExists = $roleNode->attributeValue( 'createRole' );
            $replacePolicies       = $roleNode->attributeValue( 'replacePolicies' );
            $referenceID           = $roleNode->attributeValue( 'referenceID' );

            $this->writeMessage( "\tRole '$roleName' will be created." , 'notice' );


            $rolePolicyList = $roleNode->getElementsByTagName( 'Policy' );
            $policyList = array();
            foreach ( $rolePolicyList as $policyNode )
            {
                $policyModule   = $policyNode->attributeValue( 'module' );
                $policyFunction = $policyNode->attributeValue( 'function' );

                $policyLimitationList = array();
                $policyLimitationNodeList = $policyNode->elementByName( 'Limitations' );
                if ( $policyLimitationNodeList )
                {
                    $limitations = $policyLimitationNodeList->children();
                    foreach ( $limitations as $limitation )
                    {
                        if ( $limitation->Type == EZ_XML_NODE_ELEMENT )
                        {
                            if ( !array_key_exists( $limitation->name(), $policyLimitationList ) )
                            {
                                $policyLimitationList[$limitation->name()] = array();
                            }
                            $policyLimitationList[$limitation->name()][] = $this->getReferenceID( $limitation->textContent() );
                        }
                    }
                }
                $policyList[] = array( 'module'     => $policyModule,
                                       'function'   => $policyFunction,
                                       'limitation' => $policyLimitationList );
            }
            $role = eZRole::fetchByName( $roleName );
            if( is_object( $role ) || ( $createRoleIfNotExists == "true" ) )
            {
                if( !is_object( $role ) )
                {
                    $role = eZRole::create( $roleName );
                    $role->store();
                }

                $roleID = $role->attribute( 'id' );
                if( count( $policyList ) > 0 )
                {
                    if ( $replacePolicies == "true" )
                    {
                        $role->removePolicies();
                        $role->store();
                    }
                    foreach( $policyList as $policyDefinition )
                    {
                        if( isset( $policyDefinition['limitation'] ) )
                        {
                            $role->appendPolicy( $policyDefinition['module'], $policyDefinition['function'], $policyDefinition['limitation'] );
                        }
                        else
                        {
                            $role->appendPolicy( $policyDefinition['module'], $policyDefinition['function'] );
                        }
                    }
                }

                if ( $referenceID )
                {
                    $refArray[$referenceID] = $role->attribute( 'id' );
                }
            }
            else
            {
                $this->writeMessage( "\tRole '$roleName' doesn't exist." , 'notice' );
            }
        }
        $this->addReference( $refArray );
    }

    function handlerInfo()
    {
        return array( 'XMLName' => 'CreateRole', 'Info' => 'create role' );
    }
}

?>