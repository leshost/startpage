    <footer class="text-center py-4 text-secondary mt-auto border-top border-secondary" style="background-color: rgba(0,0,0,0.2);">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-4 text-md-start mb-2 mb-md-0">
                    <small>&copy; <?= date('Y') ?> Startpage Tools. Версія 2.2.0</small>
                </div>
                <div class="col-md-4 mb-2 mb-md-0">
                    <a href="https://github.com/leshost/startpage" target="_blank" class="text-secondary text-decoration-none">
                        <i class="bi bi-github fs-5"></i> GitHub Репозиторій
                    </a>
                </div>
                <div class="col-md-4 text-md-end">
                    <small>Ліцензія <a href="https://opensource.org/licenses/MIT" target="_blank" class="text-secondary">MIT (Open Source)</a></small>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery (required for Toastr) -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <!-- Toastr JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <!-- Custom JS -->
    <script src="/assets/js/app.js"></script>

    <!-- CSRF: глобальний fetch-перехоплювач -->
    <!-- Автоматично додає X-CSRF-Token до всіх не-GET запитів, що робляться через fetch() -->
    <script>
    (function () {
        var csrfToken = (document.querySelector('meta[name="csrf-token"]') || {}).getAttribute('content') || '';
        var _fetch = window.fetch;
        window.fetch = function (url, options) {
            options = options || {};
            var method = (options.method || 'GET').toUpperCase();
            if (method !== 'GET' && method !== 'HEAD') {
                options.headers = Object.assign({ 'X-CSRF-Token': csrfToken }, options.headers || {});
            }
            return _fetch.call(this, url, options);
        };
    })();
    </script>
</body>
</html>
