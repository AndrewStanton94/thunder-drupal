((Drupal, once) => {

  /**
   * Add click handler for create paragraphs modal.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.paragraphsPasteCreateModal = {
    attach: (context) => {
      once('paragraphs-paste-modal-trigger', '[data-paragraphs-paste-target]', context).forEach((elem) => {
        elem.addEventListener('click', (event) => {
          event.preventDefault();
          event.stopPropagation();

          const targetField = event.currentTarget.dataset.paragraphsPasteTarget;
          const originalForm = event.currentTarget.parentElement.querySelector('.paragraphs-paste-form');
          // Clean form before cloning.
          originalForm.querySelector(`[name="${targetField}_paste_area"]`).value = '';

          const title = Drupal.t('Create paragraphs');
          const modalContent = originalForm.cloneNode(true);
          modalContent.querySelector('textarea').style.height = '75vh';

          const dialog = Drupal.dialog(modalContent, {
            // Turn off autoResize from dialog.position so draggable is not disabled.
            autoResize: false,
            resizable: false,
            title: title,
            width: window.innerWidth * 0.5,
          });

          modalContent.querySelector(`[name="${targetField}_paste_submit"]`).addEventListener('mousedown', () => {
            originalForm.querySelector(`[name="${targetField}_paste_area"]`).value = modalContent.querySelector(`[name="${targetField}_paste_area"]`).value;
            dialog.close();
            originalForm.querySelector(`[name="${targetField}_paste_submit"]`).dispatchEvent(new Event('mousedown'));
          });

          dialog.showModal();
        });
      });
    }
  };

})(Drupal, once);
