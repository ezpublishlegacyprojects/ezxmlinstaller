<?php

class eZXMLInstallerHandler
{

    function eZXMLInstallerHandler()
    {
    }


    function initialize( &$references, &$settings, &$counter )
    {
        $this->ReferenceArray = $references;
        $this->Settings       = $settings;
        $this->StepCounter    = $counter;
    }

    function execute()
    {
    }

    static public function handlerInfo()
    {
        return array( 'XMLName' => '', 'Info' => '' );
    }

    function writeMessage( $message, $type = 'notice' )
    {
        $handlerManager = eZXMLInstallerHandlerManager::instance();
        $handlerManager->writeMessage( $message, $type = 'notice' );
    }

    function addReference( $refInfo )
    {
        if ( is_array( $refInfo ) )
        {
            $this->ReferenceArray = array_merge( $this->ReferenceArray, $refInfo );
        }
              }

    function references( )
    {
        return $this->ReferenceArray;
    }

    function getReferenceID( $refInfo )
    {
        $splitted = split( ':', $refInfo );
        if ( !(is_array( $splitted ) && count($splitted) == 2 ) )
        {
            return $refInfo;
        }
        $type = $splitted[0];
        $refID = $splitted[1];
        $referenceID = false;
        switch( $type )
        {
            case 'internal':
            {
                if ( array_key_exists( $refID, $this->ReferenceArray ) )
                {
                    $referenceID = $this->ReferenceArray[$refID];
                }
            } break;
            case 'object_id':
            {
                $intRef = (int)$refID;
                if( $intRef > 0 )
                {
                    $relContentObject = eZContentObject::fetch( $intRef );
                    if ( $relContentObject )
                    {
                        $referenceID = $intRef;
                    }
                }
            } break;
            case 'node_id':
            {
                $intRef = (int)$refID;
                if( $intRef > 0 )
                {
                    $relNode = eZContentObjectTreeNode::fetch( $intRef );
                    if ( $relNode )
                    {
                        $referenceID = $intRef;
                    }
                }
            } break;
        }
        return $referenceID;
    }

    function parseAndReplaceStringReferences( $string )
    {
        $result = array();
        $count = preg_match_all( '|\[([^\]\[]*)\]|', $string, $result );
        if ( count( $result ) > 1 )
        {
            foreach ( $result[1] as $i => $refInfo )
            {
                $id = $this->getReferenceID( $refInfo );
                $string = str_replace( $result[0][$i], $id, $string );
            }
        }
        $string = str_replace( '&#93;', ']', $string );
        $string = str_replace( '&#91;', '[', $string );
        return $string;
    }

    function settings( )
    {
        return $this->Settings;
    }

    function setting( $key )
    {
        if ( array_key_exists( $key, $this->Settings ) )
        {
            return $this->Settings[$key];
        }
        else
        {
            return NULL;
        }
    }

    function setSettings( $settingArray )
    {
        if ( is_array( $settingArray ) )
        {
            $this->Settings = $settingArray;
        }
    }

    function counter()
    {
        return $this->StepCounter;
    }

    function increaseCouter()
    {
        return ++$this->StepCounter;
    }

    var $ReferenceArray;
    var $Settings;
    var $StepCounter;
}

?>
