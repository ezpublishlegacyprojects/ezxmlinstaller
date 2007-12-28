<?php

class eZXMLPublisherType extends eZWorkflowEventType
{
    function eZXMLPublisherType()
    {
        $this->eZWorkflowEventType( 'ezxmlpublisher', ezi18n( 'extension/ezxmkinstaller', 'XML Publisher' ) );
        $this->setTriggerTypes( array( 'content' => array( 'publish' => array( 'after' ) ) ) );
    }

    function attributeDecoder( $event, $attr )
    {
        $retValue = null;
        return $retValue;
    }

    function typeFunctionalAttributes()
    {
        return array( );
    }

    function unserializeUserGroupsConfig( &$event )
    {
        $retValue = array();
        return $retValue;
    }

    function serializeUserGroupsConfig( $userGroups )
    {
        $xmlString = '';
        return $xmlString;
    }

    function fetchHTTPInput( $http, $base, $event )
    {
    }

    /*!
     \reimp
    */
    function customWorkflowEventHTTPAction( $http, $action, $workflowEvent )
    {
    }

    function execute( $process, $event )
    {
        $parameters = $process->attribute( 'parameter_list' );
        $object = eZContentObject::fetch( $parameters['object_id'] );
        $attribute = false;

        $dataMap = $object->attribute( 'data_map' );
        foreach ( $dataMap as $attr )
        {
            $dataType = $attr->attribute( 'data_type_string' );
            if ( $dataType == 'ezfeatureselect' )
            {
                $attribute = $attr;
                continue;
            }
        }

        // if object does not have a featureselect attribute.
        if ( $attribute == false )
        {
            return EZ_WORKFLOW_TYPE_STATUS_ACCEPTED;
        }

        // if we have not the first version published, we only need to enable/disable features
        if ( $object->attribute( 'modified' ) != $object->attribute( 'published' ) )
        {
            $attributeContent = $attribute->attribute( 'content' );
            $installedFeatureList = $attributeContent['installed_feature_list'];
            $availibleFeatureList = $attributeContent['availible_feature_list'];

            $teamroomNodeID = $object->attribute( 'main_node_id' );

            foreach( $availibleFeatureList as $feature => $featureName )
            {
                $featureObject = eZContentObject::fetchByRemoteID( $teamroomNodeID . '_' . $feature );
                if( !$featureObject )
                {
                    eZDebug::writeError( "Cannot find feature object", "eZXMLPublisherType::execute" );
                    continue;
                }
                $featureNode = $featureObject->attribute( 'main_node' );
                if( !$featureNode )
                {
                    eZDebug::writeError( "Cannot find feature node", "eZXMLPublisherType::execute" );
                    continue;
                }
                if ( in_array( $feature, $installedFeatureList ) && $featureNode->attribute( 'is_hidden' ) )
                {
                    eZContentObjectTreeNode::unhideSubTree( $featureNode );
                }
                elseif ( !in_array( $feature, $installedFeatureList ) && !$featureNode->attribute( 'is_hidden' ) )
                {
                    eZContentObjectTreeNode::hideSubTree( $featureNode );
                }
            }
        }

        // defer to cron, this is safer because we might do a lot of things here
        include_once( 'lib/ezutils/classes/ezsys.php' );
        if ( eZSys::isShellExecution() == false )
        {
            return EZ_WORKFLOW_TYPE_STATUS_DEFERRED_TO_CRON_REPEAT;
        }

        // if we have the first version published, we need to set up the related things.
        if ( $object->attribute( 'modified' ) == $object->attribute( 'published' ) )
        {
            $classAttribute = $attribute->attribute( 'contentclass_attribute' );
            $templateName = $classAttribute->attribute( 'data_text1' );

            $attributeContent = $attribute->attribute( 'content' );
            $installedFeatureList = $attributeContent['installed_feature_list'];
            $availibleFeatureList = $attributeContent['availible_feature_list'];

            if( $templateName == '' )
            {
                return EZ_WORKFLOW_TYPE_STATUS_ACCEPTED;
            }

            $template = 'design:' . $templateName;
            $tpl = templateInit();
            $tpl->setVariable( 'tpl_info', false );

            $content = $tpl->fetch( $template );

            $tpl->setVariable( 'install_features', $installedFeatureList );

            $userID = $object->attribute( 'owner_id' );
            $tpl->setVariable( 'owner_object_id', $userID );

            $nodeID = $object->attribute( 'main_node_id' );
            $tpl->setVariable( 'teamroom_node_id', $nodeID );


            $content = $tpl->fetch( $template );
            $xml = $tpl->variable( "xml_data" );

            $doc = new DOMDocument();
            if( !$doc->loadXML( $xml ) )
            {
                eZDebug::writeError( "Cannot parse XML", "eZXMLPublisherType::execute" );
                return EZ_WORKFLOW_TYPE_STATUS_WORKFLOW_CANCELLED;
            }

            $xmlInstaller = new eZXMLInstaller( $doc );

            if (! $xmlInstaller->proccessXML() )
            {
                eZDebug::writeError( "Cannot proccess XML", "eZXMLPublisherType::execute" );
                return EZ_WORKFLOW_TYPE_STATUS_WORKFLOW_CANCELLED;
            }

            return EZ_WORKFLOW_TYPE_STATUS_ACCEPTED;
        }
        // otherwise we need only to enable, disable the selected features.
        else
        {
        }


        return EZ_WORKFLOW_TYPE_STATUS_ACCEPTED;
    }
}


eZWorkflowEventType::registerType( 'ezxmlpublisher', 'eZXMLPublisherType' );

?>
