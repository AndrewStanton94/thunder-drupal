/**
 * @file row-class.js
 */

(function (Drupal) {

  'use strict';

  /**
   * Add a row class to a search api result item.
   *
   * @type {Object}
   */
  Drupal.behaviors.searchApiMarkOutdatedAddClass = {
    attach: function (context, settings) {
      document.querySelectorAll('div[data-is-outdated="1"]').forEach(function(item) {
        item.closest('tr').classList.add('search-api-outdated');
      });
    }
  };


}(Drupal));
