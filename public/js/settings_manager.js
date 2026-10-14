document.addEventListener('DOMContentLoaded', function() {

    
    const darkModeToggle = document.getElementById('darkModeToggle');
    const themeToggleBtnMobile = document.getElementById('themeToggleBtnMobile');
    const themeToggleBtnDesktop = document.getElementById('themeToggleBtnDesktop');
    const languageButtons = document.querySelectorAll('[data-lang]');
    const htmlTag = document.documentElement;

  
    
    
    if (darkModeToggle) {
        darkModeToggle.checked = htmlTag.classList.contains('dark-mode');
    }
    
    
    const currentLang = htmlTag.lang || 'en';
    applyTranslations(currentLang);
    updateLanguageButtonState(currentLang);
    

    /**
     * Applies (or removes) dark mode, persists the choice, and keeps every
     * toggle control on the page (settings checkbox + navbar buttons) in sync.
     * @param {boolean} isDark
     */
    function setDarkMode(isDark) {
        htmlTag.classList.toggle('dark-mode', isDark);
        localStorage.setItem('theme', isDark ? 'dark' : 'light');
        if (darkModeToggle) {
            darkModeToggle.checked = isDark;
        }
        document.dispatchEvent(new CustomEvent('themeChanged', {
            detail: { theme: isDark ? 'dark' : 'light' }
        }));
    }

    if (darkModeToggle) {
        darkModeToggle.addEventListener('change', function() {
            setDarkMode(this.checked);
        });
    }

    [themeToggleBtnMobile, themeToggleBtnDesktop].forEach(function(btn) {
        if (btn) {
            btn.addEventListener('click', function() {
                setDarkMode(!htmlTag.classList.contains('dark-mode'));
            });
        }
    });

   
    languageButtons.forEach(button => {
        button.addEventListener('click', function() {
            const newLang = this.getAttribute('data-lang');
            
            if (newLang === htmlTag.lang) return;

            localStorage.setItem('language', newLang);
            setLanguage(newLang);
        });
    });

    /**
     * 
     * @param {string} 
     */
    function setLanguage(lang) {
      
        htmlTag.setAttribute('lang', lang);
        htmlTag.setAttribute('dir', lang === 'ar' ? 'rtl' : 'ltr');
        applyTranslations(lang);
        updateLanguageButtonState(lang);
        document.dispatchEvent(new CustomEvent('languageChanged', { 
            detail: { lang: lang } 
        }));
    }
    
    /**
     * 
     * @param {string} 
     */
    function applyTranslations(lang) {
        const elements = document.querySelectorAll('[data-translate-key]');
        
      
        if (typeof translations === 'undefined') {
            console.error('translations.js is not loaded or the translations object is missing.');
            return;
        }

        elements.forEach(el => {
            const key = el.dataset.translateKey;
            if (translations[key] && translations[key][lang]) {
                const translation = translations[key][lang];
                
               
                if (el.hasAttribute('placeholder')) {
                    el.placeholder = translation;
                } else {
                    
                    el.innerHTML = translation;
                }
            } else {
                
            }
        });

        // Tooltips / title attributes (e.g. action icon buttons) use a
        // separate data attribute so we don't clobber their inner icon HTML.
        const tooltipElements = document.querySelectorAll('[data-translate-title]');
        tooltipElements.forEach(el => {
            const key = el.dataset.translateTitle;
            if (translations[key] && translations[key][lang]) {
                el.title = translations[key][lang];
                if (el.hasAttribute('aria-label')) {
                    el.setAttribute('aria-label', translations[key][lang]);
                }
            }
        });
    }

    /**
     * Small helper other page scripts can call to get the current
     * translation for a key without duplicating the lookup logic.
     * Falls back to the key itself (or a provided default) if missing.
     */
    window.tr = function(key, fallback) {
        const lang = htmlTag.lang || 'en';
        if (typeof translations !== 'undefined' && translations[key] && translations[key][lang]) {
            return translations[key][lang];
        }
        return fallback !== undefined ? fallback : key;
    };

    /**
    
     * @param {string} 
     */
    function updateLanguageButtonState(activeLang) {
        languageButtons.forEach(button => {
            if (button.getAttribute('data-lang') === activeLang) {
                button.classList.add('btn-primary', 'active');
                button.classList.remove('btn-outline-primary');
            } else {
                button.classList.remove('btn-primary', 'active');
                button.classList.add('btn-outline-primary');
            }
        });
    }
});