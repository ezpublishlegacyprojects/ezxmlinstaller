<?php

include_once( 'kernel/classes/ezcontentobjecttreenode.php' );
include_once( 'kernel/classes/ezcontentclass.php' );
include_once( "lib/ezlocale/classes/ezdatetime.php" );
include_once( 'lib/ezutils/classes/ezoperationhandler.php' );

class eZXMLInstaller
{
    function eZXMLInstaller( $domDocument )
    {
        $this->rootDomNode = $domDocument->root();
        $this->cli =& eZCLI::instance();
    }

    function proccessXML( )
    {
        $installerHandlerManager = new eZXMLInstallerHandlerManager();
        $installerHandlerManager->initialize();
        if ( $this->rootDomNode &&
             $this->rootDomNode->type() == EZ_NODE_TYPE_ELEMENT &&
             $this->rootDomNode->name() == 'eZXMLImporter' )
        {
            if ( $this->rootDomNode->hasAttributes() )
            {
                $settings = $this->rootDomNode->attributeValues();
                $installerHandlerManager->setSettings( $settings );
            }
            if ( $this->rootDomNode->hasChildren() )
            {
                $children = $this->rootDomNode->children();
                foreach ( $children as $child )
                {
                    if ( $child->type() == EZ_NODE_TYPE_ELEMENT )
                    {
                        $installerHandlerManager->executeHandler( $child->name(), $child );
                    }
                }
            }
            else
            {
                $installerHandlerManager->writeMessage( "XML has no valid information.", 'error' );
                return false;
            }
        }
        else
        {
            $installerHandlerManager->writeMessage( "XML is not initialized.", 'error' );
            return false;
        }
        return true;
    }

    var $rootDomNode;
    var $cli;
}

?>
