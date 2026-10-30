<?php
// includes/footer.php - Page Closure & Dynamic Slide-in Panels
?>
        </main>
    </div>
</div>

<!-- Global Slide Panel Backdrop (No modals per PRD) -->
<div id="slideBackdrop" class="slide-panel-backdrop" onclick="RMS.closeAllSlidePanels()"></div>

<!-- Global Toast Container for Async Messages (Top-right position to avoid overlapping bottom action buttons) -->
<div id="toastContainer" class="position-fixed top-0 end-0 p-3" style="z-index: 1200; pointer-events: none;"></div>

</body>
</html>
