/**
 * @author Daniel Dimitrov - compojoom.com
 * @date: 09.06.12
 *
 * @copyright  Copyright (C) 2008 - 2012 compojoom.com . All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

var police = new Class({
    Implements:[Options],
    options:{},
    initialize:function (options) {
        var self = this;
        this.setOptions(options);

        // Clear result from any previous check
        if(document.id('police-check-result').getChildren('div').length > 0) {
            document.id('police-check-result').empty();
        }
        

        this.options.rules.each(function(rule){
           self.check(rule);
        });
        new Fx.Scroll(window).toElement(document.id('police-check-result'));
    },

    check: function(rule) {
        var self = this;
        new Request({
            url: self.options.url + '/index.php?option=com_jedchecker&task=police.check&format=raw&rule='+rule,
            async: false,
            onComplete: function(result) {
                var div = new Element('div', {
                    html: result
                });
                div.inject(document.id('police-check-result'));
                document.id('prison').setStyle('display', 'block');
            }
        }).send();
    }
});