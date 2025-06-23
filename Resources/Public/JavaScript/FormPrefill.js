const FORM_FRAMEWORK_PREFIX = 'tx_form_formframework';
const FIELD_SELECTORS = ['input', 'textarea'];

const createFieldSelector = (formId, formField) => {
  return FIELD_SELECTORS
    .map(element => `${element}[name^="${FORM_FRAMEWORK_PREFIX}[${formId}][${formField}]"]`)
    .join(', ');
};

const prefillFormField = (formElement, value) => {
  if (formElement && value) {
    formElement.value = value;
  }
};

const processFormFields = (userData, formId, fieldMap) => {
  Object.entries(fieldMap).forEach(([feField, formField]) => {
    const selector = createFieldSelector(formId, formField);
    const formElement = document.querySelector(selector);
    prefillFormField(formElement, userData[feField]);
  });
};

document.addEventListener('DOMContentLoaded', async () => {
  try {
    const response = await fetch('/prefill-user.json');
    if (!response.ok) {
      return;
    }

    const userData = await response.json();
    const formMappings = window.formPrefillMappings || {};

    Object.entries(formMappings).forEach(([formId, fieldMap]) => {
      processFormFields(userData, formId, fieldMap);
    });
  } catch (error) {
    console.error('Error when pre-filling the form data:', error);
  }
});
