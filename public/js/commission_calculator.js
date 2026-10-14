// commission_calculator.js
// Instantly computes Company Commission and Sales Commission based on the
// selected property's stored commission rules (percent or fixed amount),
// using the property price by default or a manual override price.
document.addEventListener('DOMContentLoaded', function() {
    const select = document.getElementById('cc_property_select');
    if (!select) {
        return; // Not on the commission calculator page.
    }

    const noSelectionEl = document.getElementById('cc_no_selection');
    const detailsEl = document.getElementById('cc_details');
    const priceDisplay = document.getElementById('cc_price_display');
    const companyRuleDisplay = document.getElementById('cc_company_rule_display');
    const salesRuleDisplay = document.getElementById('cc_sales_rule_display');
    const overrideInput = document.getElementById('cc_override_price');

    const companyResultEl = document.getElementById('cc_company_result');
    const companyResultSubEl = document.getElementById('cc_company_result_sub');
    const salesResultEl = document.getElementById('cc_sales_result');
    const salesResultSubEl = document.getElementById('cc_sales_result_sub');
    const totalResultEl = document.getElementById('cc_total_result');

    function tr(key, fallback) {
        return (window.tr ? window.tr(key, fallback) : fallback);
    }

    function formatMoney(value) {
        if (isNaN(value)) {
            return '0.00';
        }
        return value.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function ruleLabel(type, value) {
        if (value === '' || value === null || isNaN(parseFloat(value))) {
            return tr('cc_not_set', 'Not set');
        }
        const num = parseFloat(value);
        if (type === 'fixed') {
            return formatMoney(num) + ' ' + tr('cc_fixed_amount_suffix', '(fixed)');
        }
        return num + '%';
    }

    function computeCommission(type, value, basePrice) {
        const num = parseFloat(value);
        if (isNaN(num) || num < 0) {
            return 0;
        }
        if (type === 'fixed') {
            return num;
        }
        // percent
        if (isNaN(basePrice)) {
            return 0;
        }
        return (basePrice * num) / 100;
    }

    function render() {
        const opt = select.options[select.selectedIndex];

        if (!opt || !opt.value) {
            noSelectionEl.classList.remove('d-none');
            detailsEl.classList.add('d-none');
            companyResultEl.textContent = '0.00';
            salesResultEl.textContent = '0.00';
            totalResultEl.textContent = '0.00';
            companyResultSubEl.textContent = '—';
            salesResultSubEl.textContent = '—';
            return;
        }

        noSelectionEl.classList.add('d-none');
        detailsEl.classList.remove('d-none');

        const listedPrice = parseFloat(opt.dataset.price);
        const companyType = opt.dataset.companyType || 'percent';
        const companyValue = opt.dataset.companyValue;
        const salesType = opt.dataset.salesType || 'percent';
        const salesValue = opt.dataset.salesValue;

        const overrideRaw = overrideInput.value.trim();
        const basePrice = overrideRaw !== '' ? parseFloat(overrideRaw) : listedPrice;

        priceDisplay.textContent = !isNaN(listedPrice) ? formatMoney(listedPrice) : '—';
        companyRuleDisplay.textContent = ruleLabel(companyType, companyValue);
        salesRuleDisplay.textContent = ruleLabel(salesType, salesValue);

        const companyCommission = computeCommission(companyType, companyValue, basePrice);
        const salesCommission = computeCommission(salesType, salesValue, basePrice);
        const total = companyCommission + salesCommission;

        companyResultEl.textContent = formatMoney(companyCommission);
        salesResultEl.textContent = formatMoney(salesCommission);
        totalResultEl.textContent = formatMoney(total);

        companyResultSubEl.textContent = ruleLabel(companyType, companyValue);
        salesResultSubEl.textContent = ruleLabel(salesType, salesValue);
    }

    select.addEventListener('change', render);
    overrideInput.addEventListener('input', render);

    // Re-render on language change so "%", "(fixed)", "Not set" labels
    // and number formatting reflect the active language immediately.
    document.addEventListener('languageChanged', render);

    render();
});
