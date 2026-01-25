
                    <script>
                        if ("serviceWorker" in navigator) {
                            window.addEventListener("load", function() {
                                navigator.serviceWorker.register("/sw.js")
                                    .then(function(registration) {
                                        console.log("ServiceWorker registered:", registration.scope);
                                    })
                                    .catch(function(error) {
                                        console.log("ServiceWorker registration failed:", error);
                                    });
                            });
                        }
                    </script>
                <?php /**PATH D:\dev\www\sgc\storage\framework\views/175139d6a7d7cf1e0d197afeab99a4ce.blade.php ENDPATH**/ ?>