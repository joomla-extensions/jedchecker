# JED Checker

This extension is able to check your components, modules or plugins for common errors that will prevent you
from publishing your extension on the JED (Joomla! Extensions Directory).

If you are a developer and want to contribute to this extension you can fork this repo.

## Uploading your package

After installing this extension in your Joomla! backend, you can use it by uploading a Joomla! extension-package using
the upload-button. Once uploaded, the contents of the package (your files) will be checked against JED-rules.

## Adding rules

If you want to write a rule have a look a the `administrator/components/com_jedchecker/library/rules` folder.

You just need to add a new file with your rule, for example `yourrule.php`.

The file `yourrule.php` needs to have a class `jedcheckerRulesYourrule` and that class needs to have a
function that accepts the basedir as parameter. This is all - the component will automatically call
your rule check function.

If you are going to contribute your rule to the project, then make sure that it follows the joomla coding conventions
and that it passes the code sniffer: http://docs.joomla.org/Joomla_CodeSniffer

## Checking on existing files and folders

The extension also supports a scan of a pre-defined set of existing files and folders.
For this to work, add a list of folders to a textfile `tmp/jed_checker/local.txt`.
There should be a folder on each line.
Once the file exists, a "Check" button becomes visible in the jedchecker-toolbar. Just hit it.

Example `tmp/jed_checker/local.txt` file:

        components/com_jedchecker
        administrator/components/com_jedchecker
        plugins/system

## History of the Extension

This extension was previously maintained by Compojoom (Daniel Dimitrov). Other developers that collaborated with the original project were Denis Dulici (mijosoft.com), Riccardo Zorn (fasterjoomla.com), Bernard Toplak, eaxs (projectfork.net).

Now, JED Checker is currently supported by Joomla (Open Source Matters).

## COPYRIGHT AND DISCLAIMER

Copyright (C) 2019 Open Source Matters, Inc. All rights reserved.
Copyright (C) 2008 - 2018 compojoom.com . All rights reserved.

This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program. If not, see http://www.gnu.org/licenses/.
