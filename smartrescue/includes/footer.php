</main> <!-- End Main Container -->

<!-- Global JS -->
<script src="../assets/js/script.js"></script>

<!-- Optional per-page JS injected via $extra_js -->
<?php if (isset($extra_js)) echo $extra_js; ?>

</body>

<!-- Live Session Heartbeat (Enforces Immediate Redirect on Takeover) -->
<script>
(function() {
    // Determine path back to root for API call
    let path = window.location.pathname;
    let apiUrl = '../api/auth/verify_session.php';
    if(path.includes('/admin/') || path.includes('/user/') || path.includes('/driver/')) {
        apiUrl = '../api/auth/verify_session.php';
    } else {
        apiUrl = 'api/auth/verify_session.php';
    }

    setInterval(async () => {
        try {
            const res = await fetch(apiUrl);
            const data = await res.json();
            if (data.valid === false) {
                // Determine login redirect path
                let loginUrl = '../auth/login.php?error=session_expired';
                if(!path.includes('/admin/') && !path.includes('/user/') && !path.includes('/driver/')) {
                    loginUrl = 'auth/login.php?error=session_expired';
                }
                window.location.href = loginUrl;
            }
        } catch (e) {
            // Ignore temporary network errors to prevent false logouts
            console.warn("Session check heartbeat failed, retrying...");
        }
    }, 10000); // Check every 10 seconds
})();
</script>
</html>
