Gravity Forms Salsa Add-On
==========================================

Integrates [Gravity Forms][gravity] with [Salsa][salsa] allowing supporters
to be saved to Salsa and added to groups.

Usage
-----

Create your Gravity Form as usual and then add admin values for form elements
that you want saved to Salsa. These values should match the field names
in Salsa (i.e. *First_Name*).

To get a form to submit data to Salsa, visit the add on's settings page
and add the form ID to the *Salsa enabled forms* field.

### Groups

To add supporters to groups, you can allow the user to select groups by
adding a form field with the admin value `salsa_groups`.

You can also add a hidden field called `salsa_groups` if you'd like to
add all supporters to a single Salsa group.

Constraints
-----------

* Subfields are not supported. You need a single field per Salsa field so you can specify an admin value

Credits
-------

* [Henare Degan](http://www.henaredegan.com/)
* [Mikey Leung](http://www.mikeyleung.ca/)

This plugin was built with support from [The Oaktree Foundation][oaktree].

Changelog
---------

0.0.1 - Initial release

  [gravity]: http://www.gravityforms.com/
  [salsa]: http://www.salsalabs.com/
  [oaktree]: http://theoaktree.org/
