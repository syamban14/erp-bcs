<script>
// Approval functions for Permission Requests
async function approvePermission(id) {
    if (!confirm('Approve permission request ini?')) return;
    
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
            alert('✓ Permission approved successfully!');
            window.location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    } catch (error) {
        alert('Error approving permission: ' + error.message);
    }
}

async function rejectPermission(id) {
    const reason = prompt('Masukkan alasan penolakan:');
    if (!reason) return;
    
    try {
        const response = await fetch(`/admin-api/permissions/${id}/reject`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ reason })
        });
        const data = await response.json();
        
        if (data.success) {
            alert('✓ Permission rejected successfully!');
            window.location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    } catch (error) {
        alert('Error rejecting permission: ' + error.message);
    }
}

// Approval functions for Attendance Corrections
async function approveCorrection(id) {
    if (!confirm('Approve attendance correction ini?')) return;
    
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
            alert('✓ Correction approved successfully!');
            window.location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    } catch (error) {
        alert('Error approving correction: ' + error.message);
    }
}

async function rejectCorrection(id) {
    const reason = prompt('Masukkan alasan penolakan:');
    if (!reason) return;
    
    try {
        const response = await fetch(`/admin-api/corrections/${id}/reject`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ reason })
        });
        const data = await response.json();
        
        if (data.success) {
            alert('✓ Correction rejected successfully!');
            window.location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    } catch (error) {
        alert('Error rejecting correction: ' + error.message);
    }
}

// Approval functions for Leaves (Cuti)
async function approveLeave(id) {
    if (!confirm('Approve cuti ini?')) return;
    
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
            alert('✓ ' + data.message);
            window.location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    } catch (error) {
        alert('Error approving leave: ' + error.message);
    }
}

async function rejectLeave(id) {
    const reason = prompt('Masukkan alasan penolakan:');
    if (!reason) return;
    
    try {
        const response = await fetch(`/admin-api/leaves/${id}/reject`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ reason })
        });
        const data = await response.json();
        
        if (data.success) {
            alert('✓ ' + data.message);
            window.location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    } catch (error) {
        alert('Error rejecting leave: ' + error.message);
    }
}
</script>
