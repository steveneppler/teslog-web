<script>
    (function() {
        var t = localStorage.getItem('theme');
        var d = document.documentElement;
        if (t === 'dark') { d.classList.add('dark'); d.style.colorScheme = 'dark'; }
        else if (t === 'light') { d.classList.add('light'); d.style.colorScheme = 'light'; }
        else {
            var prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            d.style.colorScheme = prefersDark ? 'dark' : 'light';
        }
    })();
</script>
