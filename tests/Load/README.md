# Milestone 9 load harness

Jalankan hanya terhadap staging yang telah diisi dengan `php artisan m9:prepare-load-fixtures --run-id=...`. Script menolak host yang tampak production dan tetap membutuhkan `M9_ALLOW_LOAD_TEST=true`.

Session cookie, CSRF token, Reverb subscription payload, dan credential lain harus berasal dari secret CI. Jangan menaruhnya di argument yang direkam shell, source code, artifact, atau evidence manifest. Jalur mutation harus menunjuk mutation idempotent milik fixture staging.

Smoke: `k6 run -e M9_SMOKE=1 -e M9_ALLOW_LOAD_TEST=true -e M9_BASE_URL=http://localhost:8000 tests/Load/milestone-9.js` tetap membutuhkan environment authenticated/realtime agar semua scenario berjalan. Full run menghasilkan evidence eksternal; output mentah tidak di-commit.
