<?php
require_once 'phing/Task.php';
require_once 'phing/tasks/ext/svn/SvnBaseTask.php';

/**
 * Git latest tree version to Phing property
 * @package akeebabuilder
 * @copyright Copyright (c)2009-2010 Nicholas K. Dionysopoulos
 * @license GNU GPL version 3 or, at your option, any later version
 * @author nicholas
 */
class GitVersionTask extends SvnBaseTask
{
    private $propertyName = "git.version";

    /**
     * Sets the name of the property to use
     */
    function setPropertyName($propertyName)
    {
        $this->propertyName = $propertyName;
    }

    /**
     * Returns the name of the property to use
     */
    function getPropertyName()
    {
        return $this->propertyName;
    }

    /**
     * Sets the path to the working copy
     */
    function setWorkingCopy($wc)
    {
        $this->workingCopy = $wc;
    }

    /**
     * The main entry point
     *
     * @throws BuildException
     */
    function main()
    {
        $this->setup('info');


        exec('git log -n 1 --no-decorate --pretty=format:"%h" '.escapeshellarg(realpath($this->workingCopy)), $out);
        $version = $out[0];

        $this->project->setProperty($this->getPropertyName(), $version);
    }
}