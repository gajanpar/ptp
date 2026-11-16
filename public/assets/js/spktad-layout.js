/**
 * SPKTAD Shared Layout Script (Sidebar, Logout Dialog, Clock, Navigation Bar)
 */
(function () {
    window.toggleSidebar = function () {
        var sidebar = document.querySelector('.sidebar');
        var overlay = document.getElementById('sidebarOverlay');
        if (sidebar) sidebar.classList.toggle('open');
        if (overlay) overlay.classList.toggle('active');
        document.body.classList.toggle('sidebar-locked');
    };

    window.openLogoutModal = function () {
        var modal = document.getElementById('logoutModal');
        if (modal && typeof modal.showModal === 'function') {
            modal.showModal();
        } else if (modal) {
            modal.setAttribute('open', '');
        }
    };

    window.closeLogoutModal = function () {
        var modal = document.getElementById('logoutModal');
        if (modal && typeof modal.close === 'function' && modal.open) {
            modal.close();
        } else if (modal) {
            modal.removeAttribute('open');
        }
    };

    document.addEventListener('DOMContentLoaded', function () {
        var logoutModal = document.getElementById('logoutModal');
        if (logoutModal) {
            logoutModal.addEventListener('click', function (e) {
                if (e.target === this) {
                    var r = this.getBoundingClientRect();
                    if (e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom) {
                        window.closeLogoutModal();
                    }
                }
            });
        }

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                var sidebar = document.querySelector('.sidebar');
                if (sidebar && sidebar.classList.contains('open')) {
                    window.toggleSidebar();
                }
            }
        });

        // 24-Hour WIB Minute-Cadence Clock (No 1Hz ticking distraction)
        var clockEl = document.getElementById('serverClock') || document.getElementById('serverClockApp');
        if (clockEl) {
            var parts = clockEl.innerText.replace(' WIB', '').split(':').map(Number);
            if (parts.length >= 2 && !parts.some(isNaN)) {
                var hours = parts[0];
                var minutes = parts[1];
                setInterval(function () {
                    minutes++;
                    if (minutes >= 60) {
                        minutes = 0;
                        hours++;
                    }
                    if (hours >= 24) {
                        hours = 0;
                    }
                    clockEl.innerText = String(hours).padStart(2, '0') + ':' + String(minutes).padStart(2, '0') + ' WIB';
                }, 60000);
            }
        }

        // Internal page navigation progress bar
        var progressBar = document.getElementById('topLoadingProgress');
        if (progressBar) {
            var startProgress = function () {
                progressBar.classList.remove('is-finished');
                progressBar.classList.add('is-loading');
            };
            var finishProgress = function () {
                progressBar.classList.remove('is-loading');
                progressBar.classList.add('is-finished');
            };

            window.addEventListener('load', finishProgress);
            window.addEventListener('pageshow', finishProgress);

            document.addEventListener('click', function (e) {
                var target = e.target.closest('a');
                if (!target || !target.href) return;
                if (target.target === '_blank' || target.hasAttribute('download')) return;
                var href = target.getAttribute('href');
                if (!href || href.startsWith('#') || href.startsWith('javascript:')) return;

                try {
                    var url = new URL(target.href);
                    if (url.origin === window.location.origin) {
                        startProgress();
                    }
                } catch (err) {}
            });

            document.addEventListener('submit', function (e) {
                var form = e.target;
                if (form && form.target === '_blank') return;
                startProgress();
            });
        }
    });
})();
