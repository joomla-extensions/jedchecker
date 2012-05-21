<?php

require_once "phing/Task.php";

class explodeTask extends Task
{


    public function setString($string)
    {
        $this->string = $string;
    }

    public function setDelimiter($delimiter) {
        $this->delimiter = $delimiter;
    }

    public function setKey($key) {
        $this->key = $key;
    }

    /**
     * Defines the name of a property to be set .
     *
     * @param string $name Name for the property to be set from input
     */
    public function setPropertyName($name) {
        $this->propertyName = $name;
    }

    /**
     * The init method: Do init steps.
     */
    public function init()
    {
        // nothing to do here
    }

    /**
     * The main entry point method.
     */
    public function main()
    {
        if ($this->propertyName === null) {
            throw new BuildException("You must specify a value for propertyName attribute.");
        }


        $value = explode($this->delimiter, $this->string);

        if ($value !== null) {
            $this->project->setUserProperty($this->propertyName, $value[$this->key]);
        }
    }

}