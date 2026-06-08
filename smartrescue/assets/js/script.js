/**
 * SmartRescue Global Utilities
 */

const SmartRescue = {
    // Show a bottom-center toast notification
    toast: function(message, color = null) {
        let toastEl = document.getElementById('sr-toast-global');
        if (!toastEl) {
            toastEl = document.createElement('div');
            toastEl.id = 'sr-toast-global';
            toastEl.className = 'sr-toast';
            document.body.appendChild(toastEl);
        }
        
        toastEl.textContent = message;
        if (color) {
            toastEl.style.backgroundColor = color;
            toastEl.style.color = '#fff';
        } else {
            toastEl.style.backgroundColor = '';
            toastEl.style.color = '';
        }
        
        // Trigger reflow
        void toastEl.offsetWidth;
        toastEl.classList.add('show');
        
        if (this.toastTimeout) clearTimeout(this.toastTimeout);
        this.toastTimeout = setTimeout(() => {
            toastEl.classList.remove('show');
        }, 3000);
    },

    // Toggle global dark/light theme
    toggleTheme: function() {
        const root = document.documentElement;
        const isDark = root.getAttribute('data-theme') === 'dark';
        const nextTheme = isDark ? 'light' : 'dark';
        
        root.setAttribute('data-theme', nextTheme);
        
        // Optional: Save to backend if endpoint exists
        const formData = new FormData();
        formData.append('action', 'toggle_preference');
        formData.append('preference', 'dark_mode');
        formData.append('value', isDark ? 0 : 1);
        
        fetch('../api/user/user_settings.php', { method: 'POST', body: formData })
            .catch(e => console.log('Theme sync skipped:', e));
            
        this.toast(`Switched to ${nextTheme} mode`);
    },
    
    // Go Back
    goBack: function() {
        if(document.referrer !== "") {
            window.history.back();
        } else {
            window.location.href = '../user/index.php';
        }
    }
};
