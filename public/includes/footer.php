    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 mb-4">
                    <h5>Parque Industrial de Catamarca</h5>
                    <p class="mb-3">Impulsando el desarrollo industrial de la provincia.</p>
                    <div class="d-flex gap-3">
                        <a href="#" class="fs-5"><i class="bi bi-facebook"></i></a>
                        <a href="#" class="fs-5"><i class="bi bi-instagram"></i></a>
                        <a href="#" class="fs-5"><i class="bi bi-twitter-x"></i></a>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 mb-4">
                    <h5>Enlaces</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="<?= PUBLIC_URL ?>/">Inicio</a></li>
                        <li class="mb-2"><a href="<?= PUBLIC_URL ?>/empresas.php">Empresas</a></li>
                        <li class="mb-2"><a href="<?= PUBLIC_URL ?>/mapa.php">Mapa</a></li>
                        <li class="mb-2"><a href="<?= PUBLIC_URL ?>/estadisticas.php">Estadísticas</a></li>
                    </ul>
                </div>
                <div class="col-lg-3 col-md-4 mb-4">
                    <h5>Contacto</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><i class="bi bi-geo-alt me-2"></i>San Fernando del Valle de Catamarca</li>
                        <li class="mb-2"><i class="bi bi-telephone me-2"></i>(0383) 4123456</li>
                        <li class="mb-2"><i class="bi bi-envelope me-2"></i>contacto@parqueindustrial.gob.ar</li>
                    </ul>
                </div>
                <div class="col-lg-3 col-md-4 mb-4">
                    <h5>Gobierno de Catamarca</h5>
                    <p class="small">Ministerio de Producción e Industria</p>
                    <img src="<?= PUBLIC_URL ?>/img/logo-gobierno.png" alt="Gobierno de Catamarca" style="max-height: 50px; opacity: 0.8;" onerror="this.style.display='none'">
                </div>
            </div>
            <div class="footer-bottom">
                <p class="mb-0">&copy; <?= date('Y') ?> Parque Industrial de Catamarca - Todos los derechos reservados</p>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Custom JS -->
    <script src="<?= PUBLIC_URL ?>/js/main.js"></script>
    
    <?php if (isset($extra_js)): ?>
    <?= $extra_js ?>
    <?php endif; ?>
</body>
</html>
