<?php
require_once __DIR__ . '/../src/auth/auth_check.php';
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/property_matching_helper.php';

$company_id = $_SESSION['company_id'] ?? null;

// ---- JSON endpoint used by the live search on this page ----
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    $criteria = property_matching_criteria($_GET);
    $results = match_properties($conn, $company_id, $criteria);
    $out = [];
    foreach ($results as $r) {
        $out[] = [
            'id'           => (int) $r['id'],
            'developer'    => $r['developer'],
            'project'      => $r['project'],
            'location'     => $r['project_location'],
            'unit_type'    => $r['unit_type'],
            'area'         => $r['area'] !== null ? (float) $r['area'] : null,
            'bedrooms'     => $r['bedrooms'] !== null ? (int) $r['bedrooms'] : null,
            'price'        => $r['price'] !== null ? (float) $r['price'] : null,
            'down_payment' => $r['down_payment'] !== null ? (float) $r['down_payment'] : null,
            'installments' => $r['installments'],
            'score'        => (int) $r['match_score'],
        ];
    }
    echo json_encode(['searched' => property_matching_has_criteria($criteria), 'results' => $out]);
    exit();
}

// ---- Optional: prefill from an existing client's requirements ----
$prefill = ['location' => '', 'space' => '', 'price' => '', 'bedrooms' => '', 'payment' => 'any'];
$client = null;
if (!empty($_GET['client_id'])) {
    $client = fetch_accessible_client($conn, (int) $_GET['client_id']);
    if ($client) {
        $prefill['location'] = (string) ($client['project_location'] ?? '');
        $prefill['space']    = $client['unit_area'] !== null ? (string) $client['unit_area'] : '';
        $prefill['price']    = $client['unit_price'] !== null ? (string) (float) $client['unit_price'] : '';
    }
}

require_once __DIR__ . '/../src/templates/header.php';
?>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/property_matching.css">
<div class="container-fluid py-4 px-4">
    <div class="page-title-row mb-3">
        <h2 class="fw-bold mb-0"><i class="fa-solid fa-bullseye me-2"></i><span data-translate-key="property_matching_title">Property Matching</span></h2>
    </div>

    <?php if ($client): ?>
        <div class="alert alert-info py-2">
            <i class="fa-solid fa-user me-1"></i>
            <span data-translate-key="pm_matching_for">Matching for client:</span>
            <strong><?php echo htmlspecialchars($client['client_name']); ?></strong>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form id="matchForm" onsubmit="return false;" autocomplete="off">
                <div class="row g-3">
                    <div class="col-12 col-md-4 col-lg">
                        <label class="form-label" for="pmLocation" data-translate-key="pm_area">Area</label>
                        <input type="text" id="pmLocation" name="location" class="form-control"
                               placeholder="e.g. New Cairo" data-translate-key="pm_area_ph"
                               value="<?php echo htmlspecialchars($prefill['location']); ?>">
                    </div>
                    <div class="col-6 col-md-4 col-lg">
                        <label class="form-label" for="pmSpace" data-translate-key="pm_space">Space (m²)</label>
                        <input type="number" min="0" step="any" id="pmSpace" name="space" class="form-control"
                               value="<?php echo htmlspecialchars($prefill['space']); ?>">
                    </div>
                    <div class="col-6 col-md-4 col-lg">
                        <label class="form-label" for="pmPrice" data-translate-key="pm_price">Price</label>
                        <input type="number" min="0" step="any" id="pmPrice" name="price" class="form-control"
                               value="<?php echo htmlspecialchars($prefill['price']); ?>">
                    </div>
                    <div class="col-6 col-md-4 col-lg">
                        <label class="form-label" for="pmBedrooms" data-translate-key="pm_bedrooms">Bedrooms</label>
                        <input type="number" min="0" step="1" id="pmBedrooms" name="bedrooms" class="form-control"
                               value="<?php echo htmlspecialchars($prefill['bedrooms']); ?>">
                    </div>
                    <div class="col-6 col-md-4 col-lg">
                        <label class="form-label" for="pmPayment" data-translate-key="pm_payment">Payment method</label>
                        <select id="pmPayment" name="payment" class="form-select">
                            <option value="any" data-translate-key="pm_pay_any">Any</option>
                            <option value="cash" data-translate-key="pm_pay_cash">Cash</option>
                            <option value="installments" data-translate-key="pm_pay_installments">Installments</option>
                        </select>
                    </div>
                    <div class="col-12 col-lg-auto d-flex align-items-end">
                        <button type="button" id="pmReset" class="btn btn-outline-secondary w-100">
                            <i class="fa-solid fa-rotate-left me-1"></i> <span data-translate-key="pm_reset">Reset</span>
                        </button>
                    </div>
                </div>
                <div class="form-text mt-2" data-translate-key="pm_hint">
                    Space and price match within ±15%. Results update automatically as you type.
                </div>
            </form>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-2">
        <h5 class="mb-0"><span data-translate-key="pm_results">Matching properties</span> <span id="pmCount" class="badge bg-secondary"></span></h5>
    </div>
    <div id="pmState" class="text-muted py-4 text-center"></div>
    <div id="pmResults" class="row g-3"></div>
</div>

<script>
(function () {
    const form = document.getElementById('matchForm');
    const resultsEl = document.getElementById('pmResults');
    const stateEl = document.getElementById('pmState');
    const countEl = document.getElementById('pmCount');
    const t = (key, fallback) => {
        const lang = document.documentElement.lang || (localStorage.getItem('language') || 'en');
        return (typeof translations !== 'undefined' && translations[key] && translations[key][lang]) || fallback;
    };
    const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const num = (v, d = 0) => v == null ? '—' : Number(v).toLocaleString(undefined, {maximumFractionDigits: d});

    let timer = null, seq = 0;

    function render(data) {
        resultsEl.innerHTML = '';
        if (!data.searched) {
            countEl.textContent = '';
            stateEl.textContent = t('pm_enter_criteria', 'Enter at least one requirement to see matching properties.');
            return;
        }
        countEl.textContent = data.results.length;
        stateEl.textContent = data.results.length ? '' : t('pm_no_results', 'No available properties match these requirements.');

        resultsEl.innerHTML = data.results.map(r => {
            const badge = r.score >= 85 ? 'bg-success' : (r.score >= 60 ? 'bg-warning text-dark' : 'bg-secondary');
            return `
            <div class="col-12 col-md-6 col-xl-4">
                <div class="card h-100 shadow-sm pm-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <div class="fw-bold">${esc(r.project)}</div>
                                <div class="text-muted small">${esc(r.developer)}${r.location ? ' • ' + esc(r.location) : ''}</div>
                            </div>
                            <span class="badge ${badge}">${r.score}%</span>
                        </div>
                        <div class="pm-price mb-2">${num(r.price)}</div>
                        <ul class="list-unstyled small mb-0">
                            <li><strong>${t('pm_unit_type', 'Unit type')}:</strong> ${esc(r.unit_type)}</li>
                            <li><strong>${t('pm_space', 'Space (m²)')}:</strong> ${num(r.area, 2)}</li>
                            <li><strong>${t('pm_bedrooms', 'Bedrooms')}:</strong> ${r.bedrooms == null ? '—' : r.bedrooms}</li>
                            <li><strong>${t('col_down_payment', 'Down Payment')}:</strong> ${num(r.down_payment)}</li>
                            <li><strong>${t('col_installments', 'Installments')}:</strong> ${esc(r.installments) || '—'}</li>
                        </ul>
                    </div>
                </div>
            </div>`;
        }).join('');
    }

    function search() {
        const mine = ++seq;
        const params = new URLSearchParams(new FormData(form));
        params.set('ajax', '1');
        fetch('property_matching.php?' + params.toString(), {headers: {'Accept': 'application/json'}})
            .then(r => r.json())
            .then(data => { if (mine === seq) render(data); })
            .catch(() => { if (mine === seq) { stateEl.textContent = t('pm_error', 'Could not load matches. Please try again.'); } });
    }

    function schedule() { clearTimeout(timer); timer = setTimeout(search, 250); }

    form.addEventListener('input', schedule);
    form.addEventListener('change', schedule);
    document.getElementById('pmReset').addEventListener('click', () => {
        form.querySelectorAll('input').forEach(i => { i.value = ''; });
        document.getElementById('pmPayment').value = 'any';
        search();
    });
    search();
})();
</script>
<?php require_once __DIR__ . '/../src/templates/footer.php'; ?>
