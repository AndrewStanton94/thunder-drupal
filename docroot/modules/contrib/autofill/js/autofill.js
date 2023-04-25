((Drupal, once) => {

  Drupal.behaviors.autofillFromAnotherField = {
    attach: (context, settings) => {
      let target_field_was_manipulated = [];
      Object.keys(settings.autofill.field_mapping || {}).forEach((target_field) => {
        const source_field = settings.autofill.field_mapping[target_field];

        // Only process if source field and target field are present.
        const [source_field_element] = once('autofill_' + source_field + '_' + target_field, context.querySelector('[name="' + source_field + '[0][value]"]'));
        if (!source_field_element) {
          return;
        }

        const target_field_element = context.querySelector('[name="' + target_field + '[0][value]"]');
        if (!target_field_element) {
          return;
        }
        target_field_was_manipulated[target_field] = false;

        // Automatically fill target field with value of the source
        // field, when it's empty or values are identical.
        if (!source_field_element.value || source_field_element.value === target_field_element.value) {
          source_field_element.addEventListener('input', () => {
            // Autofill the target field only when it was not manipulated
            // before.
            if (!target_field_was_manipulated[target_field]) {
              target_field_element.value = source_field_element.value;
              // Trigger input event, to fire additional events, like
              // length indicator.
              target_field_element.dispatchEvent(new Event('input'));
            }
          });
        }
        else {
          target_field_was_manipulated[target_field] = true;
        }

        // Store, when target field was manipulated manually. Then we
        // should not process the autofill again.
        target_field_element.addEventListener('keypress', () => {
          target_field_was_manipulated[target_field] = true;
        });
      });
    }
  };

})(Drupal, once);
