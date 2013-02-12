JEDchecker
==========

This extension is able to check your components, modules or plugins for common errors that will prevent you
from publishing your extension on the JED (Joomla! Extensions Directory).

Installing this extension
-------------------------
ZIP packages for installation in joomla can be found here:
https://compojoom.com/downloads/official-releases-stable/jedchecker

Alternatively, download the sources of this repository and use Phing to build the packages.

Uploading your package
----------------------
After installing this extension in your Joomla! backend, you can use it by uploading a Joomla! extension-package using
the upload-button. Once uploaded, the contents of the package (your files) will be checked against JED-rules.

Adding rules
------------
If you want to write a rule have a look a the `administrator/components/com_jedchecker/library/rules` folder.

You just need to add a new file with your rule, for example `yourrule.php`.

The file `yourrule.php` needs to have a class `jedcheckerRulesYourrule` and that class needs to have a
function that accepts the basedir as parameter. This is all - the component will automatically call
your rule check function.

Checking on existing files and folders
--------------------------------------
The extension also supports a scan of a pre-defined set of existing files and folders.
For this to work, add a list of folders to a textfile `tmp/jed_checker/local.txt`.
There should be a folder on each line. 
Once the file exists, a "Check" button becomes visible in the jedchecker-toolbar. Just hit it.

Example `tmp/jed_checker/local.txt` file:

        components/com_weblinks
        administrator/components/com_weblinks
        plugins/system


