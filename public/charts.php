<?php
require_once __DIR__ . '/../src/auth/auth_check.php';
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/lead_source_helper.php';
require_once __DIR__ . '/../src/access_helper.php';

$user_id = $_SESSION['user_id'];
$company_id = $_SESSION['company_id'] ?? null;
$where_clause = "WHERE company_id = " . (int)$company_id . access_user_ids_sql($conn, 'user_id');

$lead_source_stats = get_lead_source_stats($conn, $company_id);


$status_query = "SELECT final_status, COUNT(*) as count FROM clients $where_clause GROUP BY final_status";
$status_result = $conn->query($status_query);
$status_labels = [];
$status_data = [];
while ($row = $status_result->fetch_assoc()) {
    $status_labels[] = !empty($row['final_status']) ? $row['final_status'] : 'Pending';
    $status_data[] = $row['count'];
}


$monthly_query = "SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count FROM clients $where_clause GROUP BY month ORDER BY month ASC LIMIT 12";
$monthly_result = $conn->query($monthly_query);
$monthly_labels = [];
$monthly_data = [];
while ($row = $monthly_result->fetch_assoc()) {
    $monthly_labels[] = date("M Y", strtotime($row['month'] . "-01"));
    $monthly_data[] = $row['count'];
}


$location_query = "SELECT project_location, COUNT(*) as count FROM clients $where_clause GROUP BY project_location";
$location_result = $conn->query($location_query);
$location_labels = [];
$location_data = [];
while ($row = $location_result->fetch_assoc()) {
    $location_labels[] = $row['project_location'];
    $location_data[] = $row['count'];
}

require_once __DIR__ . '/../src/templates/header.php';
?>
<link rel="stylesheet" href="css/charts.css">

<div class="container-fluid">
    <div class="page-header">
        <h1 class="page-title" data-translate-key="charts_analytics_title">Charts & Analytics</h1>
        <p class="page-subtitle" data-translate-key="charts_analytics_subtitle">Visual insights into your client data.</p>
    </div>

    <div class="lead-source-section mb-4">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h5 class="lead-source-section-title mb-0" data-translate-key="lead_source_stats_title">Lead Source Statistics</h5>
            <span class="lead-source-live-indicator" id="leadSourceLiveIndicator">
                <span class="lead-source-live-dot"></span>
                <span data-translate-key="live_updating">Live updating</span>
            </span>
        </div>
        <div class="row g-3 mb-4" id="leadSourceStatCards">
            <?php foreach (LEAD_SOURCES as $source): ?>
                <div class="col-6 col-md-4 col-lg-2">
                    <div class="card stat-card lead-source-stat-card" data-source="<?php echo htmlspecialchars($source); ?>">
                        <div class="stat-icon lead-source-stat-icon" style="background-color: <?php echo get_lead_source_color($source); ?>;">
                            <i class="<?php echo get_lead_source_icon($source); ?>"></i>
                        </div>
                        <div class="stat-info">
                            <h5 class="stat-title" data-translate-key="<?php echo $source; ?>"><?php echo $source; ?></h5>
                            <p class="stat-number lead-source-count"><?php echo (int) $lead_source_stats['counts'][$source]; ?></p>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="row g-4">
            <div class="col-lg-6">
                <div class="chart-container">
                    <canvas id="clientsByLeadSourceChart"></canvas>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="chart-container lead-source-breakdown">
                    <h6 class="lead-source-breakdown-title" data-translate-key="lead_source_breakdown">Lead Source Breakdown</h6>
                    <ul class="list-group list-group-flush lead-source-breakdown-list" id="leadSourceBreakdownList">
                        <?php foreach (LEAD_SOURCES as $source):
                            $count = (int) $lead_source_stats['counts'][$source];
                            $total = max(1, (int) $lead_source_stats['total']);
                            $pct = round(($count / $total) * 100, 1);
                        ?>
                            <li class="list-group-item lead-source-breakdown-item" data-source="<?php echo htmlspecialchars($source); ?>">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="lead-source-breakdown-label">
                                        <i class="<?php echo get_lead_source_icon($source); ?>" style="color: <?php echo get_lead_source_color($source); ?>;"></i>
                                        <span data-translate-key="<?php echo $source; ?>"><?php echo $source; ?></span>
                                    </span>
                                    <span class="lead-source-breakdown-value">
                                        <strong class="lead-source-breakdown-count"><?php echo $count; ?></strong>
                                        <small class="text-muted lead-source-breakdown-pct">(<?php echo $pct; ?>%)</small>
                                    </span>
                                </div>
                                <div class="progress lead-source-progress">
                                    <div class="progress-bar lead-source-progress-bar" style="width: <?php echo $pct; ?>%; background-color: <?php echo get_lead_source_color($source); ?>;"></div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="chart-container">
                <canvas id="clientsByStatusChart"></canvas>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="chart-container">
                <canvas id="clientsByLocationChart"></canvas>
            </div>
        </div>
        <div class="col-12">
            <div class="chart-container">
                <canvas id="newClientsPerMonthChart"></canvas>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
   
    let currentLang = localStorage.getItem('language') || 'en';

    // Theme-aware Chart.js defaults so charts stay readable in dark mode.
    function applyChartTheme() {
        const isDark = document.documentElement.classList.contains('dark-mode');
        Chart.defaults.color = isDark ? '#e6e9f0' : '#1c2333';
        Chart.defaults.borderColor = isDark ? '#2c3446' : '#e3e7ef';
    }
    applyChartTheme();

    document.addEventListener('themeChanged', function() {
        applyChartTheme();
        Chart.instances && Object.values(Chart.instances).forEach(function(chart) {
            chart.update();
        });
    });

    
    const chartPalette = [
        '#B48C42', '#0d6efd', '#198754', '#dc3545', '#fd7e14',
        '#0dcaf0', '#6f42c1', '#20c997', '#ffc107', '#6c757d'
    ];

    // DB values (e.g. "Not Interested", "New Cairo") are stored in English;
    // translate them for display the same way the server-side T() helper
    // does (space/hyphen -> underscore key lookup), falling back to the raw
    // value if no translation exists.
    function translateLabel(value, lang) {
        if (!value) return value;
        const key = String(value).replace(/[ -]/g, '_');
        if (typeof translations !== 'undefined' && translations[key] && translations[key][lang]) {
            return translations[key][lang];
        }
        return value;
    }

    const rawStatusLabels = <?php echo json_encode($status_labels); ?>;
    const rawLocationLabels = <?php echo json_encode($location_labels); ?>;

    const ctxStatus = document.getElementById('clientsByStatusChart');
    const statusChart = new Chart(ctxStatus, {
        type: 'pie',
        data: {
            labels: rawStatusLabels.map(l => translateLabel(l, currentLang)),
            datasets: [{
                label: 'Clients',
                data: <?php echo json_encode($status_data); ?>,
                backgroundColor: chartPalette,
                borderColor: document.documentElement.classList.contains('dark-mode') ? '#12161f' : '#ffffff',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'top' },
                title: { 
                    display: true, 
                    text: translations['chart_clients_by_status'][currentLang], 
                    font: { size: 18 } 
                }
            }
        }
    });

    
    const ctxLocation = document.getElementById('clientsByLocationChart');
    const locationChart = new Chart(ctxLocation, {
        type: 'doughnut',
        data: {
            labels: rawLocationLabels.map(l => translateLabel(l, currentLang)),
            datasets: [{
                label: 'Clients',
                data: <?php echo json_encode($location_data); ?>,
                backgroundColor: chartPalette,
                borderColor: document.documentElement.classList.contains('dark-mode') ? '#12161f' : '#ffffff',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'top' },
                title: { 
                    display: true, 
                    text: translations['chart_clients_by_location'][currentLang], 
                    font: { size: 18 } 
                }
            }
        }
    });

    
    const ctxMonthly = document.getElementById('newClientsPerMonthChart');
    const monthlyChart = new Chart(ctxMonthly, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($monthly_labels); ?>,
            datasets: [{
                label: 'New Clients',
                data: <?php echo json_encode($monthly_data); ?>,
                backgroundColor: 'rgba(180, 140, 66, 0.7)',
                borderColor: 'rgba(180, 140, 66, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            scales: { y: { beginAtZero: true } },
            plugins: {
                legend: { display: false },
                title: { 
                    display: true, 
                    text: translations['chart_new_clients_per_month'][currentLang], 
                    font: { size: 18 } 
                }
            }
        }
    });

    // Lead Source chart: canonical source list (fixed order/colors) so the
    // legend and card grid always line up, even for sources with 0 leads.
    const leadSourceKeys = <?php echo json_encode(LEAD_SOURCES); ?>;
    const leadSourceColors = <?php echo json_encode(array_combine(LEAD_SOURCES, array_map('get_lead_source_color', LEAD_SOURCES))); ?>;
    let leadSourceCounts = <?php echo json_encode(array_values($lead_source_stats['counts'])); ?>;

    const ctxLeadSource = document.getElementById('clientsByLeadSourceChart');
    const leadSourceChart = new Chart(ctxLeadSource, {
        type: 'doughnut',
        data: {
            labels: leadSourceKeys.map(k => translateLabel(k, currentLang)),
            datasets: [{
                label: 'Leads',
                data: leadSourceCounts,
                backgroundColor: leadSourceKeys.map(k => leadSourceColors[k]),
                borderColor: document.documentElement.classList.contains('dark-mode') ? '#12161f' : '#ffffff',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'top' },
                title: {
                    display: true,
                    text: translations['chart_clients_by_lead_source'][currentLang],
                    font: { size: 18 }
                }
            }
        }
    });

    /**
     * Applies a freshly-fetched stats payload ({counts, total}) to the
     * chart, the stat cards, and the breakdown list — used both for the
     * initial render's live-language pass and for auto-refresh polling.
     */
    function applyLeadSourceStats(counts, total) {
        leadSourceCounts = leadSourceKeys.map(k => counts[k] || 0);
        leadSourceChart.data.datasets[0].data = leadSourceCounts;
        leadSourceChart.update();

        const safeTotal = Math.max(1, total || 0);
        leadSourceKeys.forEach(function(source) {
            const count = counts[source] || 0;
            const pct = Math.round((count / safeTotal) * 1000) / 10;

            const card = document.querySelector('.lead-source-stat-card[data-source="' + source + '"] .lead-source-count');
            if (card) card.textContent = count;

            const item = document.querySelector('.lead-source-breakdown-item[data-source="' + source + '"]');
            if (item) {
                const countEl = item.querySelector('.lead-source-breakdown-count');
                const pctEl = item.querySelector('.lead-source-breakdown-pct');
                const barEl = item.querySelector('.lead-source-progress-bar');
                if (countEl) countEl.textContent = count;
                if (pctEl) pctEl.textContent = '(' + pct + '%)';
                if (barEl) barEl.style.width = pct + '%';
            }
        });
    }

    /**
     * Auto-update: polls the lead source stats endpoint in the background
     * so the chart/cards stay current without a manual page reload.
     */
    function refreshLeadSourceStats() {
        const indicator = document.getElementById('leadSourceLiveIndicator');
        fetch('../src/actions/get_lead_source_stats.php', { credentials: 'same-origin' })
            .then(res => res.json())
            .then(data => {
                if (data && data.success) {
                    applyLeadSourceStats(data.counts, data.total);
                    if (indicator) indicator.classList.remove('lead-source-live-error');
                }
            })
            .catch(() => {
                if (indicator) indicator.classList.add('lead-source-live-error');
            });
    }
    setInterval(refreshLeadSourceStats, 30000);
    document.addEventListener('visibilitychange', function() {
        if (document.visibilityState === 'visible') {
            refreshLeadSourceStats();
        }
    });

    document.addEventListener('languageChanged', function(e) {
        currentLang = e.detail.lang;

        statusChart.data.labels = rawStatusLabels.map(l => translateLabel(l, currentLang));
        statusChart.options.plugins.title.text = translations['chart_clients_by_status'][currentLang];
        statusChart.update();

        locationChart.data.labels = rawLocationLabels.map(l => translateLabel(l, currentLang));
        locationChart.options.plugins.title.text = translations['chart_clients_by_location'][currentLang];
        locationChart.update();

        monthlyChart.options.plugins.title.text = translations['chart_new_clients_per_month'][currentLang];
        monthlyChart.update();

        leadSourceChart.data.labels = leadSourceKeys.map(k => translateLabel(k, currentLang));
        leadSourceChart.options.plugins.title.text = translations['chart_clients_by_lead_source'][currentLang];
        leadSourceChart.update();
    });
});
</script>

<?php
require_once __DIR__ . '/../src/templates/footer.php';
?>