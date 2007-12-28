<?php
include_once('extension/ezxmlinstaller/classes/ezxmlinstallerhandler.php');

class eZCreateWorkflow extends eZXMLInstallerHandler
{
    function eZCreateWorkflow( )
    {
    }

    function execute( $xml )
    {
        $workflowGroupList = $xml->getElementsByTagName( 'WorkflowGroup' );

        $user = eZUser::currentUser();
        $userID = $user->attribute( "contentobject_id" );

        foreach ( $workflowGroupList as $workflowGroupNode )
        {
            $groupName   = $workflowGroupNode->attributeValue( 'name' );
            $referenceID = $workflowGroupNode->attributeValue( 'referenceID' );

            $this->writeMessage( "\tWorkflow Group '$groupName' will be created." , 'notice' );
            $workflowGroup = eZWorkflowGroup::create( $userID );
            $workflowGroup->setAttribute( "name", $groupName );
            $workflowGroup->store();
            $WorkflowGroupID = $workflowGroup->attribute( "id" );

            $refArray = array();
            if ( $referenceID )
            {
                $refArray[$referenceID] = $WorkflowGroupID;
            }
            $this->addReference( $refArray );

            $workflowList = $workflowGroupNode->getElementsByTagName( 'Workflow' );
            foreach ( $workflowList as $workflowNode )
            {
                $refArray = array();

                $workflowName          = $workflowNode->attributeValue( 'name' );
                $workflowTypeString    = $workflowNode->attributeValue( 'workflowTypeString' );
                $referenceID           = $workflowNode->attributeValue( 'referenceID' );

                $this->writeMessage( "\tWorkflow '$workflowName' will be created." , 'notice' );
                $workflow = eZWorkflow::create( $userID );
                $workflow->setAttribute( "name",  $workflowName );
                if ( $workflowTypeString )
                {
                    $workflow->setAttribute( "workflow_type_string",  $workflowTypeString );
                }


                $db = eZDB::instance();
                $db->begin();
                $workflow->store();
                $WorkflowID = $workflow->attribute( "id" );

                $WorkflowVersion = $workflow->attribute( "version" );
                $ingroup = eZWorkflowGroupLink::create( $WorkflowID, $WorkflowVersion, $WorkflowGroupID, $groupName );
                $ingroup->store();
                $db->commit();

                if ( $referenceID )
                {
                    $refArray[$referenceID] = $WorkflowID;
                }

                $eventList = $workflow->fetchEvents();

                $eventNodeList = $workflowNode->getElementsByTagName( 'Event' );
                foreach ( $eventNodeList as $eventNode )
                {
                    $description           = $eventNode->attributeValue( 'description' );
                    $workflowTypeString    = $eventNode->attributeValue( 'workflowTypeString' );
                    $placement             = $eventNode->attributeValue( 'placement' );

                    $event = eZWorkflowEvent::create( $WorkflowID, $workflowTypeString );
                    $eventType = $event->eventType();
//                     $db = eZDB::instance();
                    $db->begin();

                    $workflow->store( $eventList );

                    $eventType->initializeEvent( $event );
                    $event->store();

                    $eventDataNode = $eventNode->elementByName( 'Data' );

                    if ( $eventDataNode )
                    {
                        $attributes = $eventDataNode->children();
                        foreach ( $attributes as $attribute )
                        {
                            if ( $event->hasAttribute( $attribute->name() ) )
                            {
                                $data = $this->parseAndReplaceStringReferences( $attribute->textContent() );
                                $event->setAttribute( $attribute->name(), $data );
                            }
                        }
                    }

                    $db->commit();
                    $eventList[] = $event;

                }

                // Discard existing events, workflow version 1 and store version 0
//                 $db = eZDB::instance();
                $db->begin();

                $workflow->store( $eventList ); // store changes.

                // Remove old version 0 first
                eZWorkflowGroupLink::removeWorkflowMembers( $WorkflowID, 0 );

                $workflowgroups = eZWorkflowGroupLink::fetchGroupList( $WorkflowID, 1 );
                foreach( $workflowgroups as $workflowgroup )
                {
                    $workflowgroup->setAttribute("workflow_version", 0 );
                    $workflowgroup->store();
                }
                // Remove version 1
                eZWorkflowGroupLink::removeWorkflowMembers( $WorkflowID, 1 );

                eZWorkflow::removeEvents( false, $WorkflowID, 0 );
                $workflow->remove( true );
                $workflow->setVersion( 0, $eventList );
                $workflow->adjustEventPlacements( $eventList );
            //    $workflow->store( $event_list );
                $workflow->store( $eventList );
                $workflow->cleanupWorkFlowProcess();

                $db->commit();


                if ( $referenceID )
                {
                    $refArray[$referenceID] = $WorkflowID;
                }
                $this->addReference( $refArray );
            }
        }

        $triggerList = $xml->getElementsByTagName( 'Trigger' );
        foreach ( $triggerList as $triggerNode )
        {
            $module      = $triggerNode->attributeValue( 'module' );
            $operation   = $triggerNode->attributeValue( 'operation' );
            $connectType = $triggerNode->attributeValue( 'connectType' );
            $workflowID  = $this->getReferenceID( $triggerNode->attributeValue( 'workflowID' ) );

            $this->writeMessage( "\tTrigger '$module/$operation/$connectType' will be created/updated." , 'notice' );

            if ( $connectType == 'before' )
            {
                $connectType = 'b';
            }
            else
            {
                $connectType = 'a';
            }

            $parameters = array();
            $parameters['module']      = $module;
            $parameters['function']    = $operation;
            $parameters['connectType'] = $connectType;

            $triggerList = eZTrigger::fetchList( $parameters );

            if ( count( $triggerList ) )
            {
                $trigger = $triggerList[0];
                $trigger->setAttribute( 'workflow_id', $workflowID );
                $trigger->store();
            }
            else
            {
                $db = eZDB::instance();
                $db->begin();
                $newTrigger = eZTrigger::createNew( $module, $operation, $connectType, $workflowID );
                $db->commit();
            }
        }
    }

    function handlerInfo()
    {
        return array( 'XMLName' => 'CreateWorkflow', 'Info' => 'create new workflows' );
    }
}

?>