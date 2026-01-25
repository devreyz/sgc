
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
                <?php /**PATH D:\dev\www\sgc\storage\framework\views/df9aae02596703593225b9d1c33cfac6.blade.php ENDPATH**/ ?>