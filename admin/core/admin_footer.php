    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/admin.js"></script>
    <script>
        // Toast Notification System
        window.showToast = function(type, title, message, duration = 3000) {
            const container = document.getElementById('toastContainer');
            if (!container) return;
            
            const icons = {
                success: 'fa-check-circle',
                error: 'fa-exclamation-circle',
                warning: 'fa-exclamation-triangle',
                info: 'fa-info-circle'
            };
            
            const toast = document.createElement('div');
            toast.className = `toast-notification ${type}`;
            toast.innerHTML = `
                <i class="fas ${icons[type] || icons.info} toast-icon"></i>
                <div class="toast-content">
                    <div class="toast-title">${title}</div>
                    <div class="toast-message">${message}</div>
                </div>
                <button class="toast-close" onclick="this.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
            `;
            
            container.appendChild(toast);
            
            // Auto remove after duration
            if (duration > 0) {
                setTimeout(() => {
                    toast.classList.add('hiding');
                    setTimeout(() => toast.remove(), 300);
                }, duration);
            }
        };
        
        // Loading Overlay Functions
        window.showLoading = function(text = 'Loading...') {
            const overlay = document.getElementById('loadingOverlay');
            const loadingText = document.getElementById('loadingText');
            if (overlay) {
                if (loadingText) loadingText.textContent = text;
                overlay.classList.add('show');
            }
        };
        
        window.hideLoading = function() {
            const overlay = document.getElementById('loadingOverlay');
            if (overlay) {
                overlay.classList.remove('show');
            }
        };
        
        // Button Loading State
        window.setButtonLoading = function(button, loading = true) {
            if (!button) return;
            if (loading) {
                button.classList.add('btn-loading');
                button.disabled = true;
            } else {
                button.classList.remove('btn-loading');
                button.disabled = false;
            }
        };
        
        // Mobile sidebar toggle
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('mobileOverlay');
            sidebar.classList.toggle('show');
            overlay.classList.toggle('show');
        }
        
        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const toggle = document.querySelector('.mobile-menu-toggle');
            const overlay = document.getElementById('mobileOverlay');
            
            if (window.innerWidth <= 768) {
                if (!sidebar.contains(event.target) && !toggle.contains(event.target) && sidebar.classList.contains('show')) {
                    sidebar.classList.remove('show');
                    overlay.classList.remove('show');
                }
            }
        });
        
        // Handle window resize
        window.addEventListener('resize', function() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('mobileOverlay');
            if (window.innerWidth > 768) {
                sidebar.classList.remove('show');
                overlay.classList.remove('show');
            }
        });
    </script>
</body>
</html>
