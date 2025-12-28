document.addEventListener('DOMContentLoaded', () => {

    // Tabs
    const tabButtons = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');

    tabButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            const target = btn.dataset.tab;

            tabContents.forEach(tab => tab.classList.remove('active'));
            tabButtons.forEach(b => b.classList.remove('active'));

            document.getElementById(target).classList.add('active');
            btn.classList.add('active');

            const chartsInTab = document.getElementById(target).querySelectorAll('canvas');
            chartsInTab.forEach(canvas => {
                const chartInstance = Chart.getChart(canvas);
                if (chartInstance) {
                    chartInstance.resize();
                }
            });

            // Service Chart
            if (target === 'services') {
                setTimeout(() => {
                    initServiceChart();
                }, 100);
            }

            // Customer Charts
            if (target === 'customers') {
                setTimeout(() => {
                    initCustomerCharts();
                }, 100);
            }

            if (target === 'stylists') {
                setTimeout(() => {
                    initStylistCharts();
                }, 100);
            }

            // Update hidden input if exists
            const tabInput = document.getElementById('activeTabInput');
            if (tabInput) {
                tabInput.value = target;
            }
        });
    });

    // Restore active tab from hidden input (populated by PHP)
    const storedTab = document.getElementById('activeTabInput');
    if (storedTab && storedTab.value && storedTab.value !== 'appointments') {
        const btnToClick = document.querySelector(`.tab-btn[data-tab="${storedTab.value}"]`);
        if (btnToClick) {
            btnToClick.click();
        }
    }

    // Appointment Charts
    function initAppointmentCharts() {
        // 0. Main Stacked Trend (Existing)
        const trendCanvas = document.getElementById('appointmentTrendChart');
        if (trendCanvas && !Chart.getChart(trendCanvas)) {
            new Chart(trendCanvas.getContext('2d'), {
                type: 'line',
                data: {
                    labels: JSON.parse(trendCanvas.dataset.labels || '[]'),
                    datasets: [
                        
                        { label: 'Confirmed', data: JSON.parse(trendCanvas.dataset.confirmed || '[]').map(Number), borderColor: '#3b82f6', backgroundColor: 'rgba(59, 130, 246, 0.2)', tension: 0.3 },
                        { label: 'Cancelled', data: JSON.parse(trendCanvas.dataset.cancelled || '[]').map(Number), borderColor: '#ef4444', backgroundColor: 'rgba(239, 68, 68, 0.2)', tension: 0.3 },
                        
                    ]
                },
                options: { responsive: true, aspectRatio: 3.5, plugins: { legend: { position: 'bottom' } }, scales: { y: { beginAtZero: true }, x: {} } }
            });
        }

        // 1. Status Distribution (Donut)
        const statusCanvas = document.getElementById('apptStatusChart');
        if (statusCanvas && !Chart.getChart(statusCanvas)) {
            new Chart(statusCanvas.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: ['Confirmed', 'Cancelled'],
                    datasets: [{
                        data: [
                            
                            parseInt(statusCanvas.dataset.confirmed || 0),
                            parseInt(statusCanvas.dataset.cancelled || 0),
                            
                        ],
                        backgroundColor: ['#10b981','#ef4444']
                    }]
                },
                options: {
                    responsive: true,
                    aspectRatio: 1.5,
                    plugins: {
                        legend: { position: 'right', labels: { boxWidth: 10, font: { size: 10 } } }
                    }
                }
            });
        }


        // 3. Cancellation Trend
        const cancCanvas = document.getElementById('cancellationTrendChart');
        if (cancCanvas && !Chart.getChart(cancCanvas)) {
            new Chart(cancCanvas.getContext('2d'), {
                type: 'line',
                data: {
                    labels: JSON.parse(cancCanvas.dataset.labels || '[]'),
                    datasets: [{
                        label: 'Cancelled',
                        data: JSON.parse(cancCanvas.dataset.values || '[]'),
                        borderColor: '#ef4444',
                        backgroundColor: 'rgba(239, 68, 68, 0.1)',
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    aspectRatio: 1.5,
                    scales: {
                        y: {
                            beginAtZero: true,
                            display: true,
                            title: {
                                display: true,
                                text: 'Count'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                display: true
                            },
                            title: {
                                display: true,
                                text: 'Period'
                            }
                        }
                    },
                    plugins: { legend: { display: false } }
                }
            });
        }

        // 4. Duration Utilization
        const durCanvas = document.getElementById('durationChart');
        if (durCanvas && !Chart.getChart(durCanvas)) {
            const rawMins = JSON.parse(durCanvas.dataset.values || '[]');
            const rawHours = rawMins.map(m => (m / 60).toFixed(1)); // Convert to hours
            new Chart(durCanvas.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: JSON.parse(durCanvas.dataset.labels || '[]'),
                    datasets: [{
                        label: 'Hours',
                        data: rawHours,
                        backgroundColor: '#3b82f6',
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    aspectRatio: 1.5,
                    scales: {
                        y: {
                            beginAtZero: true,
                            display: true,
                            title: {
                                display: true,
                                text: 'Hours'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                display: true
                            },
                            title: {
                                display: true,
                                text: 'Period'
                            }
                        }
                    },
                    plugins: { legend: { display: false } }
                }
            });
        }
    }

    // Init immediately for Appointments tab
    initAppointmentCharts();

    // Service Pie Chart
    let serviceChartInstance = null;
    const serviceCanvas = document.getElementById('serviceChart');

    function initServiceChart() {
        if (!serviceCanvas || serviceChartInstance) return;

        serviceChartInstance = new Chart(serviceCanvas.getContext('2d'), {
            type: 'pie',
            data: {
                labels: JSON.parse(serviceCanvas.dataset.labels || '[]'),
                datasets: [{
                    label: 'Service Count',
                    data: JSON.parse(serviceCanvas.dataset.values || '[]'),
                    backgroundColor: [
                        '#7c3aed', '#6d28d9', '#2563eb', '#059669', '#dc2626',
                        '#f59e0b', '#eab308', '#3b82f6', '#10b981', '#f97316'
                    ]
                }]
            },
            options: {
                responsive: true,
                aspectRatio: 1.5,
                plugins: { legend: { position: 'bottom' } }
            }
        });
    }

    // Customer Charts
    let customerChartsInitialized = false;

    function initCustomerCharts() {
        if (customerChartsInitialized) return;
        customerChartsInitialized = true;

        // Growth Chart
        const growthCanvas = document.getElementById('customerGrowthChart');
        if (growthCanvas) {
            new Chart(growthCanvas.getContext('2d'), {
                type: 'line',
                data: {
                    labels: JSON.parse(growthCanvas.dataset.labels || '[]'),
                    datasets: [{
                        label: 'New Customers',
                        data: JSON.parse(growthCanvas.dataset.values || '[]'),
                        borderColor: '#7c3aed',
                        backgroundColor: 'rgba(124, 58, 237, 0.1)',
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    aspectRatio: 2,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true } }
                }
            });
        }

        // Segmentation Chart
        const segCanvas = document.getElementById('customerSegmentChart');
        if (segCanvas) {
            const data = {
                high: parseInt(segCanvas.dataset.high || 0),
                regular: parseInt(segCanvas.dataset.regular || 0),
                onetime: parseInt(segCanvas.dataset.onetime || 0),
                dormant: parseInt(segCanvas.dataset.dormant || 0)
            };

            new Chart(segCanvas.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: ['High Value', 'Regular', 'One-Time', 'Dormant'],
                    datasets: [{
                        data: [data.high, data.regular, data.onetime, data.dormant],
                        backgroundColor: ['#8b5cf6', '#3b82f6', '#9ca3af', '#ef4444']
                    }]
                },
                options: {
                    responsive: true,
                    aspectRatio: 1.5,
                    plugins: { legend: { position: 'bottom' } }
                }
            });
        }
    }

    // Stylist Charts 
    let stylistChartsInitialized = false;

    function initStylistCharts() {
        if (stylistChartsInitialized) return;
        stylistChartsInitialized = true;

        // Workload
        const barCanvas = document.getElementById('stylistBarChart');
        if (barCanvas) {
            new Chart(barCanvas.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: JSON.parse(barCanvas.dataset.labels || '[]'),
                    datasets: [{
                        label: 'Total Appointments',
                        data: JSON.parse(barCanvas.dataset.values || '[]'),
                        backgroundColor: '#7c3aed',
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    aspectRatio: 2,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true } }
                }
            });
        }

        // Market Share 
        const pieCanvas = document.getElementById('stylistPieChart');
        if (pieCanvas) {
            new Chart(pieCanvas.getContext('2d'), {
                type: 'pie',
                data: {
                    labels: JSON.parse(pieCanvas.dataset.labels || '[]'),
                    datasets: [{
                        data: JSON.parse(pieCanvas.dataset.values || '[]'),
                        backgroundColor: [
                            '#7c3aed', '#6d28d9', '#2563eb', '#059669', '#dc2626',
                            '#f59e0b', '#eab308', '#3b82f6', '#10b981', '#f97316'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    aspectRatio: 1.5,
                    plugins: { legend: { position: 'bottom' } }
                }
            });
        }
    }

    // EXPORT LOGIC

    function prepareForExport() {
        const tabs = document.querySelectorAll('.tab-content');
        tabs.forEach(tab => {
            if (!tab.classList.contains('active')) {
                tab.classList.add('export-visible');
                tab.style.display = 'block';

                tab.style.position = 'absolute';
                tab.style.left = '-9999px';
                tab.style.top = '0';
                tab.style.width = '1200px';
            }
        });
        return tabs;
    }

    function cleanupAfterExport() {
        const tabs = document.querySelectorAll('.tab-content');
        tabs.forEach(tab => {
            if (tab.classList.contains('export-visible')) {
                tab.style.display = '';
                tab.classList.remove('export-visible');
                tab.style.position = '';
                tab.style.left = '';
                tab.style.top = '';
                tab.style.width = '';
            }
        });
    }

    // PDF 
    document.getElementById('exportPDF').addEventListener('click', async () => {
        prepareForExport();

        initCustomerCharts();
        initStylistCharts();
        initServiceChart();

        await new Promise(r => setTimeout(r, 500));

        try {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();

            doc.setFontSize(20);
            doc.text("Salon System - Comprehensive Report", 14, 20);
            doc.setFontSize(10);
            doc.text(`Generated on: ${new Date().toLocaleDateString()}`, 14, 28);

            // Appointment Section
            doc.setFontSize(16);
            doc.text("1. Appointment Summary", 14, 40);

            let yPos = 50;

            const insights = document.querySelectorAll('#appointmentInsights .stat-card');
            if (insights.length > 0) {
                let insightText = [];
                insights.forEach(card => insightText.push(card.innerText.replace(/\n/g, ': ')));
                doc.setFontSize(11);
                doc.setTextColor(50, 50, 50);
                doc.text("Highlights:", 14, yPos);
                yPos += 7;
                doc.text(insightText, 14, yPos);
                yPos += 20;
                doc.setTextColor(0, 0, 0);
            }

            // Stats
            const apptStatsRaw = document.querySelectorAll('#appointmentStats .stat-card');
            let statsText = [];
            apptStatsRaw.forEach(card => {
                statsText.push(card.innerText.replace(/\n/g, ': '));
            });
            doc.setFontSize(12);
            doc.text(statsText, 14, yPos);
            yPos += 30;

            do {
                // Charts Row 1 (Status, Cancel, Duration)
                const statusCanvas = document.getElementById('apptStatusChart');
                const cancCanvas = document.getElementById('cancellationTrendChart');
                const durCanvas = document.getElementById('durationChart');

                if (statusCanvas && cancCanvas && durCanvas) {
                    const statusImg = statusCanvas.toDataURL("image/png");
                    const cancImg = cancCanvas.toDataURL("image/png");
                    const durImg = durCanvas.toDataURL("image/png");

                    doc.setFontSize(10);
                    doc.text("Status", 14, yPos);
                    doc.text("Cancelled", 75, yPos);
                    doc.text("Duration", 135, yPos);
                    yPos += 5;

                    doc.addImage(statusImg, 'PNG', 14, yPos, 55, 35);
                    doc.addImage(cancImg, 'PNG', 75, yPos, 55, 35);
                    doc.addImage(durImg, 'PNG', 135, yPos, 55, 35);
                    yPos += 45;
                }
            } while (0);

            // Main Stacked Trend
            const trendCanvas = document.getElementById('appointmentTrendChart');
            if (trendCanvas) {
                if (yPos > 220) { doc.addPage(); yPos = 20; }
                doc.setFontSize(14);
                doc.text("Volume Trend", 14, yPos);
                yPos += 8;
                const chartImg = trendCanvas.toDataURL("image/png");
                doc.addImage(chartImg, 'PNG', 14, yPos, 180, 80);
                yPos += 90;
            }

            doc.addPage();

            // Customer Section
            doc.addPage();
            doc.setFontSize(16);
            doc.text("2. Customer Intelligence", 14, 20);

            // Customer Stats
            const custStatsRaw = document.querySelectorAll('#customerStats .stat-card');
            statsText = [];
            custStatsRaw.forEach(card => {
                statsText.push(card.innerText.replace(/\n/g, ': '));
            });
            doc.setFontSize(11);
            doc.text(statsText, 14, 30);

            yPos = 50;

            // Growth Chart
            const growthCanvas = document.getElementById('customerGrowthChart');
            if (growthCanvas) {
                doc.setFontSize(14);
                doc.text("Acquisition Growth", 14, yPos);
                yPos += 10;
                const growthImg = growthCanvas.toDataURL("image/png");
                doc.addImage(growthImg, 'PNG', 14, yPos, 180, 80);
                yPos += 90;
            }

            // Segmentation Chart
            const segCanvas = document.getElementById('customerSegmentChart');
            if (segCanvas) {
                if (yPos > 200) {
                    doc.addPage();
                    yPos = 20;
                }
                doc.setFontSize(14);
                doc.text("Customer Segmentation", 14, yPos);
                yPos += 10;
                const segImg = segCanvas.toDataURL("image/png");
                doc.addImage(segImg, 'PNG', 14, yPos, 140, 80); // Smaller width for Pie
                yPos += 90;
            }

            // Leaderboard
            doc.addPage();
            doc.setFontSize(14);
            doc.text("Top Loyal Customers", 14, 20);
            doc.autoTable({
                html: '#topCustomers',
                startY: 25,
                theme: 'striped',
                headStyles: { fillColor: [124, 58, 237] }
            });

            doc.addPage();

            // Stylist Section (Workload & Utilization)
            doc.addPage();
            doc.setFontSize(16);
            doc.text("3. Stylist Performance", 14, 20);

            // Stylist Stats
            const styStatsRaw = document.querySelectorAll('#stylistStats .stat-card');
            statsText = [];
            styStatsRaw.forEach(card => {
                statsText.push(card.innerText.replace(/\n/g, ': '));
            });
            doc.setFontSize(11);
            doc.text(statsText, 14, 30);

            yPos = 50;
            // Workload Chart
            const barCanvas = document.getElementById('stylistBarChart');
            if (barCanvas) {
                doc.setFontSize(14);
                doc.text("Workload Distribution", 14, yPos);
                yPos += 10;
                const barImg = barCanvas.toDataURL("image/png");
                doc.addImage(barImg, 'PNG', 14, yPos, 180, 80);
                yPos += 90;
            }

            // Share Chart
            const pieCanvas = document.getElementById('stylistPieChart');
            if (pieCanvas) {
                if (yPos > 200) {
                    doc.addPage();
                    yPos = 20;
                }
                doc.setFontSize(14);
                doc.text("Market Share", 14, yPos);
                yPos += 10;
                const pieImg = pieCanvas.toDataURL("image/png");
                doc.addImage(pieImg, 'PNG', 14, yPos, 140, 80);
                yPos += 90;
            }

            doc.addPage();
            doc.text("Stylist Leaderboard", 14, 20);
            doc.autoTable({
                html: '#stylistTable',
                startY: 25,
                headStyles: { fillColor: [124, 58, 237] }
            });

            doc.addPage();

            // 4. Service Section
            doc.addPage();
            doc.setFontSize(16);
            doc.text("4. Service Intelligence", 14, 20);

            // Stats
            const svcStatsRaw = document.querySelectorAll('#serviceStats .stat-card');
            statsText = [];
            svcStatsRaw.forEach(card => {
                statsText.push(card.innerText.replace(/\n/g, ': '));
            });
            doc.setFontSize(11);
            doc.text(statsText, 14, 30);

            yPos = 50;
            // Chart
            if (serviceCanvas) {
                const serviceImg = serviceCanvas.toDataURL("image/png");
                doc.addImage(serviceImg, 'PNG', 14, yPos, 140, 80);
                yPos += 90;
            }

            // Top 5 Table
            doc.text("Top 5 Performing Services", 14, yPos);
            doc.autoTable({
                html: '#topServicesTable',
                startY: yPos + 5,
                headStyles: { fillColor: [124, 58, 237] }
            });

            // Bottom 5 (New Page)
            doc.addPage();
            doc.text("Growth Opportunities", 14, 20);
            doc.autoTable({
                html: '#bottomServicesTable',
                startY: 25,
                headStyles: { fillColor: [245, 158, 11] }
            });

            // Combos
            const finalY = doc.lastAutoTable.finalY || 40;
            doc.text("Popular Combinations", 14, finalY + 15);
            doc.autoTable({
                html: '#comboTable',
                startY: finalY + 20,
                headStyles: { fillColor: [99, 102, 241] }
            });

            doc.save('Salon_Comprehensive_Report.pdf');

        } catch (e) {
            console.error("PDF Export Error:", e);
            alert("Error generating PDF");
        } finally {
            cleanupAfterExport();
        }
    });

    // Excel
    document.getElementById('exportExcel').addEventListener('click', () => {
        prepareForExport();

        setTimeout(() => {
            try {
                const wb = XLSX.utils.book_new();

                // Appointments
                const apptData = [['Metric', 'Value']];
                document.querySelectorAll('#appointmentStats .stat-card').forEach(card => {
                    const label = card.childNodes[0].textContent.trim();
                    const valueEl = card.querySelector('b');
                    const value = valueEl ? valueEl.innerText.trim() : '';
                    apptData.push([label, value]);
                });
                const wsAppt = XLSX.utils.aoa_to_sheet(apptData);
                XLSX.utils.book_append_sheet(wb, wsAppt, "Appointments");

                // Customers
                const wsCust = XLSX.utils.table_to_sheet(document.getElementById('topCustomers'));
                XLSX.utils.book_append_sheet(wb, wsCust, "Customers");

                // Stylists
                const wsStylist = XLSX.utils.table_to_sheet(document.getElementById('stylistTable'));
                XLSX.utils.book_append_sheet(wb, wsStylist, "Stylists");

                // Services
                const elService = document.getElementById('topServicesTable');
                if (elService) {
                    const wsService = XLSX.utils.table_to_sheet(elService);
                    XLSX.utils.book_append_sheet(wb, wsService, "Services");
                }

                XLSX.writeFile(wb, "Salon_All_Reports.xlsx");
            } catch (e) {
                console.error("Excel Export Error:", e);
                alert("Error generating Excel");
            } finally {
                cleanupAfterExport();
            }
        }, 50);
    });

    // CSV 
    document.getElementById('exportCSV').addEventListener('click', () => {
        const activeTabBtn = document.querySelector('.tab-btn.active');
        const tabName = activeTabBtn ? activeTabBtn.innerText : 'Report';
        let tableId = null;

        if (tabName.includes('Customer')) tableId = 'topCustomers';
        else if (tabName.includes('Stylist')) tableId = 'stylistTable';
        else if (tabName.includes('Service')) tableId = 'topServicesTable';

        if (tableId) {
            const wb = XLSX.utils.table_to_book(document.getElementById(tableId), { sheet: "Sheet1" });
            XLSX.writeFile(wb, `${tabName}_Report.csv`);
        } else {
            const apptData = [['Metric', 'Value']];
            document.querySelectorAll('#appointmentStats .stat-card').forEach(card => {
                const label = card.childNodes[0].textContent.trim();
                const valueEl = card.querySelector('b');
                const value = valueEl ? valueEl.innerText.trim() : '';
                apptData.push([label, value]);
            });
            const ws = XLSX.utils.aoa_to_sheet(apptData);
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, "Sheet1");
            XLSX.writeFile(wb, "Appointment_Summary.csv");
        }
    });

});
