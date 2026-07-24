// Global JavaScript for Startpage Tools

// Set toastr options globally
if (typeof toastr !== 'undefined') {
    toastr.options = {
        "closeButton": true,
        "progressBar": true,
        "positionClass": "toast-bottom-right",
        "timeOut": "3000"
    };
}

// Global utility for AJAX error handling
function handleAjaxError(error) {
    console.error('AJAX Error:', error);
    if (typeof toastr !== 'undefined') {
        toastr.error('Сталася помилка при виконанні запиту.');
    }
}
