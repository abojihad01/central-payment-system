// Arabic RTL Support Script for Filament Admin Panel

document.addEventListener('DOMContentLoaded', function() {
    // Set HTML direction to RTL for Arabic
    const htmlElement = document.documentElement;
    
    // Check if the current locale is Arabic
    const currentLocale = document.querySelector('meta[name="locale"]')?.content || 'en';
    
    if (currentLocale === 'ar') {
        // Set RTL direction
        htmlElement.setAttribute('dir', 'rtl');
        htmlElement.setAttribute('lang', 'ar');
        
        // Add Arabic font class
        document.body.classList.add('arabic-interface');
        
        // Fix number formatting for Arabic
        const numberElements = document.querySelectorAll('.fi-ta-cell[data-column="amount"], .fi-ta-cell[data-column="run_count"], .fi-ta-cell[data-column="failure_count"]');
        numberElements.forEach(element => {
            element.classList.add('arabic-numbers');
        });
        
        // Fix search input direction
        const searchInputs = document.querySelectorAll('input[type="search"], .fi-ta-search-field input');
        searchInputs.forEach(input => {
            input.style.textAlign = 'right';
        });
        
        // Add observer for dynamically added content
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'childList') {
                    mutation.addedNodes.forEach(function(node) {
                        if (node.nodeType === 1) { // Element node
                            // Fix search inputs
                            const newSearchInputs = node.querySelectorAll('input[type="search"], .fi-ta-search-field input');
                            newSearchInputs.forEach(input => {
                                input.style.textAlign = 'right';
                            });
                            
                            // Fix number columns
                            const newNumberElements = node.querySelectorAll('.fi-ta-cell[data-column="amount"], .fi-ta-cell[data-column="run_count"], .fi-ta-cell[data-column="failure_count"]');
                            newNumberElements.forEach(element => {
                                element.classList.add('arabic-numbers');
                            });
                        }
                    });
                }
            });
        });
        
        // Start observing
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }
});

// Helper function to format Arabic numbers
function formatArabicNumber(number) {
    return new Intl.NumberFormat('ar-SA').format(number);
}

// Helper function to format Arabic dates
function formatArabicDate(date) {
    return new Intl.DateTimeFormat('ar-SA', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    }).format(new Date(date));
}