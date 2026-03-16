<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Approval Center - Presensi Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <!-- Confirmation Modal -->
    <div id="confirmModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
            <h3 class="text-xl font-bold mb-4" id="modalTitle">Konfirmasi</h3>
            <p class="text-gray-600 mb-6" id="modalMessage">Apakah Anda yakin?</p>
            
            <!-- Rejection Reason Input (hidden by default) -->
            <div id="reasonInput" class="hidden mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Alasan Penolakan:</label>
                <textarea id="rejectionReason" class="w-full border border-gray-300 rounded-lg p-2" rows="3"></textarea>
            </div>
            
            <div class="flex gap-3 justify-end">
                <button onclick="closeModal()" class="px-4 py-2 bg-gray-300 hover:bg-gray-400 rounded-lg font-medium transition">
                    Batal
                </button>
                <button id="confirmButton" class="px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg font-medium transition">
                    Ya, Lanjutkan
                </button>
            </div>
        </div>
    </div>

    <div class="container mx-auto px-4 py-8">
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-800">Approval Center</h1>
            <p class="text-gray-600">Kelola permintaan izin dan koreksi absensi</p>
            <a href="/admin" class="text-blue-600 hover:underline mt-2 inline-block">← Kembali ke Admin Panel</a>
        </div>

        <!-- Permission Requests -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h2 class="text-2xl font-semibold mb-4 text-gray-800">📋 Permission Requests (Pending)</h2>
            <div id="permissions-list" class="space-y-4">
                <p class="text-gray-500">Loading...</p>
            </div>
        </div>

        <!-- Leaves (Cuti) -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h2 class="text-2xl font-semibold mb-4 text-gray-800">🏖️ Leaves / Cuti (Pending)</h2>
            <div id="leaves-list" class="space-y-4">
                <p class="text-gray-500">Loading...</p>
            </div>
        </div>

        <!-- Attendance Corrections -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-2xl font-semibold mb-4 text-gray-800">✏️ Attendance Corrections (Pending)</h2>
            <div id="corrections-list" class="space-y-4">
                <p class="text-gray-500">Loading...</p>
            </div>
        </div>
    </div>

    <script>
        let currentAction = null;
        let currentId = null;

        // Modal functions
        function showModal(title, message, needsReason = false) {
            return new Promise((resolve) => {
                document.getElementById('modalTitle').textContent = title;
                document.getElementById('modalMessage').textContent = message;
                document.getElementById('confirmModal').classList.remove('hidden');
                
                const reasonInput = document.getElementById('reasonInput');
                const reasonTextarea = document.getElementById('rejectionReason');
                
                if (needsReason) {
                    reasonInput.classList.remove('hidden');
                    reasonTextarea.value = '';
                } else {
                    reasonInput.classList.add('hidden');
                }
                
                document.getElementById('confirmButton').onclick = () => {
                    const reason = needsReason ? reasonTextarea.value : null;
                    if (needsReason && !reason) {
                        alert('Alasan penolakan harus diisi!');
                        return;
                    }
                    closeModal();
                    resolve({ confirmed: true, reason });
                };
            });
        }

        function closeModal() {
            document.getElementById('confirmModal').classList.add('hidden');
        }

        // Fetch and display permission requests
        async function loadPermissions() {
            try {
                const response = await fetch('/approval-center/permissions');
                const data = await response.json();
                
                const container = document.getElementById('permissions-list');
                
                if (!data.success || data.data.length === 0) {
                    container.innerHTML = '<p class="text-gray-500">Tidak ada permintaan pending</p>';
                    return;
                }
                
                container.innerHTML = data.data.map(item => `
                    <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition">
                        <div class="flex justify-between items-start">
                            <div class="flex-1">
                                <p class="font-semibold text-gray-800">ID: ${item.id} - ${item.user?.name || 'Unknown'}</p>
                                <p class="text-sm text-gray-600">Type: <span class="font-medium">${item.type}</span></p>
                                <p class="text-sm text-gray-600">Date: ${item.start_date}</p>
                                <p class="text-sm text-gray-600">Reason: ${item.reason}</p>
                                <p class="text-xs text-gray-500 mt-2">Created: ${new Date(item.created_at).toLocaleString('id-ID')}</p>
                            </div>
                            <div class="flex gap-2 ml-4">
                                <button data-action="approve-permission" data-id="${item.id}"
                                    class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg font-medium transition">
                                    ✓ Approve
                                </button>
                                <button data-action="reject-permission" data-id="${item.id}"
                                    class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg font-medium transition">
                                    ✗ Reject
                                </button>
                            </div>
                        </div>
                    </div>
                `).join('');
            } catch (error) {
                console.error('Error loading permissions:', error);
                document.getElementById('permissions-list').innerHTML = 
                    '<p class="text-red-500">Error loading data. Please refresh the page.</p>';
            }
        }

        // Fetch and display attendance corrections
        async function loadCorrections() {
            try {
                const response = await fetch('/approval-center/corrections');
                const data = await response.json();
                
                const container = document.getElementById('corrections-list');
                
                if (!data.success || data.data.length === 0) {
                    container.innerHTML = '<p class="text-gray-500">Tidak ada koreksi pending</p>';
                    return;
                }
                
                container.innerHTML = data.data.map(item => `
                    <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition">
                        <div class="flex justify-between items-start">
                            <div class="flex-1">
                                <p class="font-semibold text-gray-800">ID: ${item.id} - ${item.user?.name || 'Unknown'}</p>
                                <p class="text-sm text-gray-600">Type: <span class="font-medium">${item.type.toUpperCase()}</span></p>
                                <p class="text-sm text-gray-600">Date: ${item.date}</p>
                                <p class="text-sm text-gray-600">Time: ${item.time}</p>
                                <p class="text-sm text-gray-600">Reason: ${item.reason}</p>
                                ${item.evidence ? `<p class="text-sm text-blue-600"><a href="/storage/${item.evidence}" target="_blank">📎 View Evidence</a></p>` : ''}
                                <p class="text-xs text-gray-500 mt-2">Created: ${new Date(item.created_at).toLocaleString('id-ID')}</p>
                            </div>
                            <div class="flex gap-2 ml-4">
                                <button data-action="approve-correction" data-id="${item.id}"
                                    class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg font-medium transition">
                                    ✓ Approve
                                </button>
                                <button data-action="reject-correction" data-id="${item.id}"
                                    class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg font-medium transition">
                                    ✗ Reject
                                </button>
                            </div>
                        </div>
                    </div>
                `).join('');
            } catch (error) {
                console.error('Error loading corrections:', error);
                document.getElementById('corrections-list').innerHTML = 
                    '<p class="text-red-500">Error loading data. Please refresh the page.</p>';
            }
        }

        // Fetch and display leaves
        async function loadLeaves() {
            try {
                const response = await fetch('/approval-center/leaves');
                const data = await response.json();
                
                const container = document.getElementById('leaves-list');
                
                if (!data.success || data.data.length === 0) {
                    container.innerHTML = '<p class="text-gray-500">Tidak ada cuti pending</p>';
                    return;
                }
                
                container.innerHTML = data.data.map(item => `
                    <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition">
                        <div class="flex justify-between items-start">
                            <div class="flex-1">
                                <div class="flex items-center gap-3 mb-3">
                                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                        ${item.user?.name || 'Unknown'}
                                    </h3>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                        ${item.type.toUpperCase()}
                                    </span>
                                </div>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                                    <div>
                                        <span class="font-medium text-gray-700 dark:text-gray-300">Start Date:</span>
                                        <span class="text-gray-600 dark:text-gray-400">${item.start_date}</span>
                                    </div>
                                    <div>
                                        <span class="font-medium text-gray-700 dark:text-gray-300">End Date:</span>
                                        <span class="text-gray-600 dark:text-gray-400">${item.end_date}</span>
                                    </div>
                                    <div>
                                        <span class="font-medium text-gray-700 dark:text-gray-300">Created:</span>
                                        <span class="text-gray-600 dark:text-gray-400">${new Date(item.created_at).toLocaleString('id-ID')}</span>
                                    </div>
                                </div>
                                
                                <div class="mt-3">
                                    <span class="font-medium text-gray-700 dark:text-gray-300">Reason:</span>
                                    <p class="text-gray-600 dark:text-gray-400 mt-1">${item.reason}</p>
                                </div>
                            </div>
                            
                            <div class="flex gap-2 ml-4">
                                <button data-action="approve-leave" data-id="${item.id}"
                                    class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg font-medium transition">
                                    ✓ Approve
                                </button>
                                <button data-action="reject-leave" data-id="${item.id}"
                                    class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg font-medium transition">
                                    ✗ Reject
                                </button>
                            </div>
                        </div>
                    </div>
                `).join('');
            } catch (error) {
                console.error('Error loading leaves:', error);
                document.getElementById('leaves-list').innerHTML = 
                    '<p class="text-red-500">Error loading data. Please refresh the page.</p>';
            }
        }

        // Approve permission
        async function approvePermission(id) {
            const result = await showModal('Approve Permission', 'Approve permission request ini?', false);
            if (!result.confirmed) return;
            
            try {
                const response = await fetch(`/admin-api/permissions/${id}/approve`, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });
                const data = await response.json();
                
                if (data.success) {
                    await showModal('Success', '✓ Permission approved successfully!', false);
                    loadPermissions();
                } else {
                    await showModal('Error', data.message, false);
                }
            } catch (error) {
                await showModal('Error', 'Error approving permission: ' + error.message, false);
            }
        }

        // Reject permission
        async function rejectPermission(id) {
            const result = await showModal('Reject Permission', 'Masukkan alasan penolakan:', true);
            if (!result.confirmed) return;
            
            try {
                const response = await fetch(`/admin-api/permissions/${id}/reject`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ reason: result.reason })
                });
                const data = await response.json();
                
                if (data.success) {
                    await showModal('Success', '✓ Permission rejected successfully!', false);
                    loadPermissions();
                } else {
                    await showModal('Error', data.message, false);
                }
            } catch (error) {
                await showModal('Error', 'Error rejecting permission: ' + error.message, false);
            }
        }

        // Approve correction
        async function approveCorrection(id) {
            const result = await showModal('Approve Correction', 'Approve attendance correction ini?', false);
            if (!result.confirmed) return;
            
            try {
                const response = await fetch(`/admin-api/corrections/${id}/approve`, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });
                const data = await response.json();
                
                if (data.success) {
                    await showModal('Success', '✓ Correction approved successfully!', false);
                    loadCorrections();
                } else {
                    await showModal('Error', data.message, false);
                }
            } catch (error) {
                await showModal('Error', 'Error approving correction: ' + error.message, false);
            }
        }

        // Reject correction
        async function rejectCorrection(id) {
            const result = await showModal('Reject Correction', 'Masukkan alasan penolakan:', true);
            if (!result.confirmed) return;
            
            try {
                const response = await fetch(`/admin-api/corrections/${id}/reject`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ reason: result.reason })
                });
                const data = await response.json();
                
                if (data.success) {
                    await showModal('Success', '✓ Correction rejected successfully!', false);
                    loadCorrections();
                } else {
                    await showModal('Error', data.message, false);
                }
            } catch (error) {
                await showModal('Error', 'Error rejecting correction: ' + error.message, false);
            }
        }

        // Approve leave
        async function approveLeave(id) {
            const result = await showModal('Approve Leave', 'Approve cuti ini?', false);
            if (!result.confirmed) return;
            
            try {
                const response = await fetch(`/admin-api/leaves/${id}/approve`, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });
                const data = await response.json();
                
                if (data.success) {
                    await showModal('Success', '✓ ' + data.message, false);
                    loadLeaves();
                } else {
                    await showModal('Error', data.message, false);
                }
            } catch (error) {
                await showModal('Error', 'Error approving leave: ' + error.message, false);
            }
        }

        // Reject leave
        async function rejectLeave(id) {
            const result = await showModal('Reject Leave', 'Masukkan alasan penolakan:', true);
            if (!result.confirmed) return;
            
            try {
                const response = await fetch(`/admin-api/leaves/${id}/reject`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ reason: result.reason })
                });
                const data = await response.json();
                
                if (data.success) {
                    await showModal('Success', '✓ ' + data.message, false);
                    loadLeaves();
                } else {
                    await showModal('Error', data.message, false);
                }
            } catch (error) {
                await showModal('Error', 'Error rejecting leave: ' + error.message, false);
            }
        }

        // Load data on page load
        loadPermissions();
        loadLeaves();
        loadCorrections();

        // Event delegation for dynamically created buttons
        document.addEventListener('click', function(e) {
            let target = e.target;
            
            // If clicked on button text/icon, get the button element
            if (!target.hasAttribute('data-action') && target.parentElement && target.parentElement.hasAttribute('data-action')) {
                target = target.parentElement;
            }
            
            const action = target.getAttribute('data-action');
            const id = target.getAttribute('data-id');
            
            if (!action || !id) return;
            
            e.preventDefault();
            
            switch(action) {
                case 'approve-permission':
                    approvePermission(parseInt(id));
                    break;
                case 'reject-permission':
                    rejectPermission(parseInt(id));
                    break;
                case 'approve-leave':
                    approveLeave(parseInt(id));
                    break;
                case 'reject-leave':
                    rejectLeave(parseInt(id));
                    break;
                case 'approve-correction':
                    approveCorrection(parseInt(id));
                    break;
                case 'reject-correction':
                    rejectCorrection(parseInt(id));
                    break;
            }
        });

        // Auto-refresh every 30 seconds
        setInterval(() => {
            loadPermissions();
            loadLeaves();
            loadCorrections();
        }, 30000);
    </script>
</body>
</html>
