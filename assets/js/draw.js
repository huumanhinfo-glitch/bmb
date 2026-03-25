// assets/js/draw.js

class DrawManager {
    constructor() {
        this.init();
    }

    init() {
        this.initFileUpload();
        this.initTabs();
        this.initDragAndDrop();
        this.initFormValidation();
        this.initTeamCountCalculator();
        this.initExportButtons();
        this.initClearDataButtons();
        this.initTooltips();
    }

    initFileUpload() {
        const fileInput = document.getElementById('csvFile');
        const dropZone = document.getElementById('dropZone');
        const fileInfo = document.getElementById('fileInfo');
        
        if (fileInput && dropZone) {
            // Click to upload
            dropZone.addEventListener('click', () => {
                fileInput.click();
            });
            
            // File selection
            fileInput.addEventListener('change', (e) => {
                if (e.target.files.length) {
                    const file = e.target.files[0];
                    const fileSize = (file.size / 1024 / 1024).toFixed(2); // MB
                    
                    if (fileSize > 5) {
                        this.showAlert('File quá lớn! Vui lòng chọn file nhỏ hơn 5MB.', 'error');
                        fileInput.value = '';
                        return;
                    }
                    
                    fileInfo.innerHTML = `
                        <div class="alert alert-success d-flex align-items-center">
                            <i class="fas fa-file-csv fa-2x me-3"></i>
                            <div>
                                <h6 class="mb-1">File đã chọn:</h6>
                                <p class="mb-0">
                                    <strong>${file.name}</strong><br>
                                    <small class="text-muted">Kích thước: ${fileSize} MB</small>
                                </p>
                            </div>
                            <button type="button" class="btn-close ms-auto" onclick="event.stopPropagation(); this.closest('.alert').remove();"></button>
                        </div>
                    `;
                }
            });
        }
    }

    initDragAndDrop() {
        const dropZone = document.getElementById('dropZone');
        if (!dropZone) return;

        const preventDefaults = (e) => {
            e.preventDefault();
            e.stopPropagation();
        };

        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, preventDefaults, false);
        });

        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, () => {
                dropZone.classList.add('dragover');
            }, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, () => {
                dropZone.classList.remove('dragover');
            }, false);
        });

        dropZone.addEventListener('drop', (e) => {
            const dt = e.dataTransfer;
            const files = dt.files;
            const fileInput = document.getElementById('csvFile');
            
            if (files.length) {
                const file = files[0];
                
                // Check file type
                if (!file.name.toLowerCase().endsWith('.csv')) {
                    this.showAlert('Chỉ chấp nhận file CSV!', 'error');
                    return;
                }
                
                // Create a new FileList
                const dataTransfer = new DataTransfer();
                dataTransfer.items.add(file);
                fileInput.files = dataTransfer.files;
                
                // Trigger change event
                fileInput.dispatchEvent(new Event('change'));
            }
        }, false);
    }

    initTabs() {
        // Lưu tab active vào localStorage
        const tabEls = document.querySelectorAll('[data-bs-toggle="tab"]');
        tabEls.forEach(tab => {
            tab.addEventListener('shown.bs.tab', (e) => {
                const activeTab = e.target.getAttribute('data-bs-target');
                localStorage.setItem('activeDrawTab', activeTab);
                
                // Gửi AJAX request để lưu tab vào session
                fetch('api/save_tab.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ tab: activeTab.replace('#', '') })
                });
            });
        });

        // Khôi phục tab đang active
        const activeTab = localStorage.getItem('activeDrawTab');
        if (activeTab) {
            const tabTrigger = document.querySelector(`[data-bs-target="${activeTab}"]`);
            if (tabTrigger) {
                new bootstrap.Tab(tabTrigger).show();
            }
        }
    }

    initFormValidation() {
        const drawForm = document.getElementById('drawForm');
        if (drawForm) {
            drawForm.addEventListener('submit', (e) => {
                const tournament = drawForm.querySelector('[name="tournament_filter"]');
                const numGroups = drawForm.querySelector('[name="num_groups"]');
                
                if (!tournament.value) {
                    e.preventDefault();
                    this.showAlert('Vui lòng chọn giải đấu!', 'warning');
                    tournament.focus();
                    return;
                }
                
                if (parseInt(numGroups.value) < 2 || parseInt(numGroups.value) > 8) {
                    e.preventDefault();
                    this.showAlert('Số bảng phải từ 2 đến 8!', 'warning');
                    numGroups.focus();
                    return;
                }
                
                // Hiển thị loading
                const submitBtn = drawForm.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Đang xử lý...';
                submitBtn.disabled = true;
                
                setTimeout(() => {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }, 3000);
            });
        }
    }

    initTeamCountCalculator() {
        const teamCountSlider = document.getElementById('teamCountSlider');
        const teamCountDisplay = document.getElementById('teamCount');
        const roundRobinMatches = document.getElementById('roundRobinMatches');
        const playoffMatches = document.getElementById('playoffMatches');
        const totalMatches = document.getElementById('totalMatches');
        
        if (teamCountSlider) {
            teamCountSlider.addEventListener('input', (e) => {
                const count = parseInt(e.target.value);
                teamCountDisplay.textContent = count;
                
                // Tính số trận vòng tròn: n*(n-1)/2
                const rrMatches = (count * (count - 1)) / 2;
                roundRobinMatches.textContent = rrMatches;
                
                // Tính số trận playoff (top 2 đấu với nhau)
                const poMatches = count >= 2 ? 1 : 0;
                playoffMatches.textContent = poMatches;
                
                // Tổng số trận
                totalMatches.textContent = rrMatches + poMatches;
            });
        }
    }

    initExportButtons() {
        // Các nút export sẽ được xử lý bằng sự kiện click global
    }

    initClearDataButtons() {
        // Các nút clear data sẽ được xử lý bằng sự kiện click global
    }

    initTooltips() {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(tooltipTriggerEl => {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }

    exportData(type, format = 'csv') {
        Swal.fire({
            title: 'Xuất dữ liệu',
            text: `Bạn muốn xuất ${type} sang định dạng ${format.toUpperCase()}?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Xuất',
            cancelButtonText: 'Hủy'
        }).then((result) => {
            if (result.isConfirmed) {
                // Hiển thị loading
                Swal.fire({
                    title: 'Đang xuất...',
                    text: 'Vui lòng chờ trong giây lát',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                // Thực hiện export
                window.location.href = `draw.php?export=${type}&format=${format}`;
                
                // Tự động đóng loading sau 3 giây
                setTimeout(() => {
                    Swal.close();
                }, 3000);
            }
        });
    }

    clearData(dataType) {
        const messages = {
            'teams': 'Bạn có chắc chắn muốn xóa TẤT CẢ đội thi?',
            'groups': 'Bạn có chắc chắn muốn xóa TẤT CẢ bảng đấu?',
            'matches': 'Bạn có chắc chắn muốn xóa TẤT CẢ trận đấu?',
            'all': 'Bạn có chắc chắn muốn xóa TOÀN BỘ dữ liệu?'
        };
        
        const confirmText = {
            'teams': 'Xóa đội',
            'groups': 'Xóa bảng',
            'matches': 'Xóa trận',
            'all': 'Xóa tất cả'
        };
        
        Swal.fire({
            title: 'Xác nhận xóa',
            html: `
                <div class="text-center">
                    <i class="fas fa-exclamation-triangle fa-3x text-danger mb-3"></i>
                    <h4>${messages[dataType]}</h4>
                    <p class="text-danger"><strong>Hành động này không thể hoàn tác!</strong></p>
                </div>
            `,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: confirmText[dataType],
            cancelButtonText: 'Hủy',
            confirmButtonColor: '#dc3545',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                // Gửi request xóa dữ liệu
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'draw.php';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'clear_data';
                form.appendChild(actionInput);
                
                const dataTypeInput = document.createElement('input');
                dataTypeInput.type = 'hidden';
                dataTypeInput.name = 'data_type';
                dataTypeInput.value = dataType;
                form.appendChild(dataTypeInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        });
    }

    switchTab(tabId) {
        const tabTrigger = document.querySelector(`[data-bs-target="#${tabId}"]`);
        if (tabTrigger) {
            bootstrap.Tab.getInstance(tabTrigger) || new bootstrap.Tab(tabTrigger);
            tabTrigger.click();
        }
    }

    editTeam(teamId) {
        Swal.fire({
            title: 'Chỉnh sửa đội',
            text: 'Chức năng đang phát triển',
            icon: 'info',
            confirmButtonText: 'OK'
        });
    }

    deleteTeam(teamId) {
        Swal.fire({
            title: 'Xóa đội',
            text: 'Bạn có chắc chắn muốn xóa đội này?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Xóa',
            cancelButtonText: 'Hủy'
        }).then((result) => {
            if (result.isConfirmed) {
                // Gửi request xóa
                fetch(`api/delete_team.php?id=${teamId}`, {
                    method: 'DELETE'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire('Thành công!', 'Đội đã được xóa.', 'success')
                            .then(() => location.reload());
                    } else {
                        Swal.fire('Lỗi!', data.message, 'error');
                    }
                })
                .catch(error => {
                    Swal.fire('Lỗi!', 'Không thể xóa đội.', 'error');
                });
            }
        });
    }

    printTeams() {
        window.print();
    }

    showAlert(message, type = 'info') {
        const alertTypes = {
            'success': { icon: 'check-circle', color: 'success' },
            'error': { icon: 'times-circle', color: 'danger' },
            'warning': { icon: 'exclamation-triangle', color: 'warning' },
            'info': { icon: 'info-circle', color: 'info' }
        };
        
        const alertType = alertTypes[type] || alertTypes.info;
        
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${alertType.color} alert-dismissible fade show`;
        alertDiv.innerHTML = `
            <i class="fas fa-${alertType.icon} me-2"></i>${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        // Thêm alert vào đầu container
        const container = document.querySelector('.container-fluid');
        if (container) {
            container.insertBefore(alertDiv, container.firstChild);
            
            // Tự động xóa sau 5 giây
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, 5000);
        }
    }

    simulateDraw() {
        const progressSteps = document.querySelectorAll('.progress-step');
        const drawResults = document.getElementById('drawResults');
        
        if (!progressSteps.length) return;
        
        // Hiệu ứng tiến trình
        let currentStep = 0;
        const steps = progressSteps.length;
        
        const progressInterval = setInterval(() => {
            progressSteps.forEach(step => step.classList.remove('active', 'completed'));
            
            for (let i = 0; i <= currentStep; i++) {
                if (i < currentStep) {
                    progressSteps[i].classList.add('completed');
                } else {
                    progressSteps[i].classList.add('active');
                }
            }
            
            currentStep++;
            
            if (currentStep > steps) {
                clearInterval(progressInterval);
                this.showDrawResults();
            }
        }, 500);
    }

    showDrawResults() {
        const drawResults = document.getElementById('drawResults');
        if (drawResults) {
            drawResults.innerHTML = `
                <div class="alert alert-success">
                    <h4><i class="fas fa-check-circle me-2"></i>Bốc thăm thành công!</h4>
                    <p>Đã tạo bảng đấu với phương thức đã chọn.</p>
                </div>
                <div class="text-center">
                    <button class="btn btn-primary" onclick="window.location.href='matches.php'">
                        <i class="fas fa-eye me-2"></i>Xem lịch thi đấu
                    </button>
                </div>
            `;
        }
    }
}

// Khởi tạo khi trang load
document.addEventListener('DOMContentLoaded', () => {
    window.drawManager = new DrawManager();
    
    // Global event listeners
    document.addEventListener('click', (e) => {
        // Export buttons
        if (e.target.closest('[onclick*="exportData"]')) {
            const onclick = e.target.closest('[onclick*="exportData"]').getAttribute('onclick');
            const match = onclick.match(/exportData\('([^']+)',\s*'([^']+)'\)/);
            if (match) {
                e.preventDefault();
                window.drawManager.exportData(match[1], match[2]);
            }
        }
        
        // Clear data buttons
        if (e.target.closest('[onclick*="clearData"]')) {
            const onclick = e.target.closest('[onclick*="clearData"]').getAttribute('onclick');
            const match = onclick.match(/clearData\('([^']+)'\)/);
            if (match) {
                e.preventDefault();
                window.drawManager.clearData(match[1]);
            }
        }
        
        // Switch tab buttons
        if (e.target.closest('[onclick*="switchTab"]')) {
            const onclick = e.target.closest('[onclick*="switchTab"]').getAttribute('onclick');
            const match = onclick.match(/switchTab\('([^']+)'\)/);
            if (match) {
                e.preventDefault();
                window.drawManager.switchTab(match[1]);
            }
        }
    });
    
    // Initialize popovers
    const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    popoverTriggerList.map(popoverTriggerEl => {
        return new bootstrap.Popover(popoverTriggerEl);
    });
});

// Helper functions for global access
function editTeam(teamId) {
    if (window.drawManager) {
        window.drawManager.editTeam(teamId);
    }
}

function deleteTeam(teamId) {
    if (window.drawManager) {
        window.drawManager.deleteTeam(teamId);
    }
}

function printTeams() {
    if (window.drawManager) {
        window.drawManager.printTeams();
    }
}

function exportData(type, format) {
    if (window.drawManager) {
        window.drawManager.exportData(type, format);
    }
}

function clearData(dataType) {
    if (window.drawManager) {
        window.drawManager.clearData(dataType);
    }
}

function switchTab(tabId) {
    if (window.drawManager) {
        window.drawManager.switchTab(tabId);
    }
}