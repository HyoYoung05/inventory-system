<?= $this->extend('layouts/admin_layout') ?>

<?= $this->section('content') ?>

<style>
    .chart-shell {
        position: relative;
        min-height: 280px;
    }

    .chart-shell canvas {
        width: 100% !important;
        height: 280px !important;
    }

    .chart-empty-note {
        display: none;
        padding: 1rem 0 0;
        color: #6b7280;
        font-size: 0.95rem;
    }

</style>

<div class="row mb-4">
    <h3><i class="bi bi-speedometer2"></i> Dashboard Overview</h3>
    <p class="text-muted">System statistics and key metrics</p>
</div>

<!-- Dashboard Cards -->
<div class="row mb-4">
    <div class="col-md-6 col-xl mb-3">
        <div class="card text-white bg-primary">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small mb-1">Total Products</div>
                        <h3 class="mb-0"><?= $total_products ?></h3>
                    </div>
                    <i class="bi bi-box-seam" style="font-size: 2rem; opacity: 0.3;"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-xl mb-3">
        <div class="card text-white bg-success">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small mb-1">Total Orders</div>
                        <h3 class="mb-0"><?= $total_orders ?></h3>
                    </div>
                    <i class="bi bi-receipt" style="font-size: 2rem; opacity: 0.3;"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-xl mb-3">
        <div class="card text-white bg-warning">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small mb-1">Low Stock Items</div>
                        <h3 class="mb-0"><?= $low_stock_count ?></h3>
                    </div>
                    <i class="bi bi-exclamation-triangle" style="font-size: 2rem; opacity: 0.3;"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-xl mb-3">
        <div class="card text-white bg-danger">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small mb-1">Out of Stock</div>
                        <h3 class="mb-0"><?= $out_of_stock_count ?></h3>
                    </div>
                    <i class="bi bi-x-octagon" style="font-size: 2rem; opacity: 0.3;"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-xl mb-3">
        <div class="card text-white bg-info">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small mb-1">Total Revenue</div>
                        <h3 class="mb-0">&#8369;<?= number_format((float) ($total_revenue ?? 0), 2) ?></h3>
                    </div>
                    <i class="bi bi-cash-coin" style="font-size: 2rem; opacity: 0.3;"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Sales Charts -->
<div class="row mb-4">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-graph-up"></i> 7 Days Sales</h5>
            </div>
            <div class="card-body">
                <div class="chart-shell">
                    <canvas id="sevenDaysChart"></canvas>
                </div>
                <div id="sevenDaysChartEmpty" class="chart-empty-note">No sales recorded in the last 7 days yet.</div>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-graph-up"></i> 6 Months Sales</h5>
            </div>
            <div class="card-body">
                <div class="chart-shell">
                    <canvas id="sixMonthsChart"></canvas>
                </div>
                <div id="sixMonthsChartEmpty" class="chart-empty-note">No sales recorded in the last 6 months yet.</div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-receipt"></i> Recent Orders</h5>
                <a href="<?= site_url('admin/orders') ?>" class="btn btn-sm btn-primary">Manage Orders</a>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Customer Name</th>
                            <th>Total Amount</th>
                            <th>Status</th>
                            <th>Date &amp; Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent_orders)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">No orders yet</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recent_orders as $order): ?>
                                <?php
                                    $statusClass = match($order['status']) {
                                        'to_be_packed' => 'badge-to-be-packed',
                                        'to_be_shipped' => 'badge-to-be-shipped',
                                        'to_be_delivered' => 'badge-to-be-delivered',
                                        'completed' => 'badge-completed',
                                        'cancelled' => 'badge-cancelled',
                                        default => 'badge-pending'
                                    };
                                    $statusLabel = match($order['status']) {
                                        'to_be_packed' => 'To Be Packed',
                                        'to_be_shipped' => 'To Be Shipped',
                                        'to_be_delivered' => 'To Be Delivered',
                                        default => ucwords(str_replace('_', ' ', $order['status']))
                                    };
                                ?>
                                <tr>
                                    <td><strong>#<?= esc($order['id']) ?></strong></td>
                                    <td><?= esc($order['customer_name']) ?></td>
                                    <td><strong>&#8369;<?= number_format((float) ($order['total_amount'] ?? 0), 2) ?></strong></td>
                                    <td><span class="badge <?= $statusClass ?>"><?= esc($statusLabel) ?></span></td>
                                    <td><?= date('M d, Y H:i', strtotime($order['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    const sevenDaysLabels = <?= $sevenDaysLabels ?>;
    const sevenDaysSales = <?= $sevenDaysSales ?>;
    const sixMonthsLabels = <?= $sixMonthsLabels ?>;
    const sixMonthsSales = <?= $sixMonthsSales ?>;
    let sevenDaysChartInstance = null;
    let sixMonthsChartInstance = null;

    function initSalesCharts() {
        if (typeof Chart === 'undefined') {
            const sevenDaysEmpty = document.getElementById('sevenDaysChartEmpty');
            const sixMonthsEmpty = document.getElementById('sixMonthsChartEmpty');

            if (sevenDaysEmpty) {
                sevenDaysEmpty.style.display = 'block';
                sevenDaysEmpty.textContent = 'Chart library failed to load.';
            }

            if (sixMonthsEmpty) {
                sixMonthsEmpty.style.display = 'block';
                sixMonthsEmpty.textContent = 'Chart library failed to load.';
            }

            return;
        }

        const sevenDaysCanvas = document.getElementById('sevenDaysChart');
        const sixMonthsCanvas = document.getElementById('sixMonthsChart');
        const sevenDaysEmpty = document.getElementById('sevenDaysChartEmpty');
        const sixMonthsEmpty = document.getElementById('sixMonthsChartEmpty');

        if (sevenDaysChartInstance) {
            sevenDaysChartInstance.destroy();
        }

        if (sixMonthsChartInstance) {
            sixMonthsChartInstance.destroy();
        }

        if (sevenDaysCanvas) {
            const hasSevenDaySales = sevenDaysSales.some((value) => Number(value) > 0);
            if (sevenDaysEmpty) {
                sevenDaysEmpty.style.display = hasSevenDaySales ? 'none' : 'block';
            }

            sevenDaysChartInstance = new Chart(sevenDaysCanvas.getContext('2d'), {
                type: 'line',
                data: {
                    labels: sevenDaysLabels,
                    datasets: [{
                        label: 'Sales Revenue (PHP)',
                        data: sevenDaysSales,
                        borderColor: '#e94560',
                        backgroundColor: 'rgba(233, 69, 96, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#e94560',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 6,
                        pointHoverRadius: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return 'PHP ' + value.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });
        }

        if (sixMonthsCanvas) {
            const hasSixMonthSales = sixMonthsSales.some((value) => Number(value) > 0);
            if (sixMonthsEmpty) {
                sixMonthsEmpty.style.display = hasSixMonthSales ? 'none' : 'block';
            }

            sixMonthsChartInstance = new Chart(sixMonthsCanvas.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: sixMonthsLabels,
                    datasets: [{
                        label: 'Monthly Revenue (PHP)',
                        data: sixMonthsSales,
                        backgroundColor: [
                            'rgba(233, 69, 96, 0.8)',
                            'rgba(52, 152, 219, 0.8)',
                            'rgba(39, 174, 96, 0.8)',
                            'rgba(241, 196, 15, 0.8)',
                            'rgba(155, 89, 182, 0.8)',
                            'rgba(52, 152, 219, 0.8)'
                        ],
                        borderColor: [
                            '#e94560',
                            '#3498db',
                            '#27ae60',
                            '#f1c40f',
                            '#9b59b6',
                            '#3498db'
                        ],
                        borderWidth: 2,
                        borderRadius: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return 'PHP ' + value.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        window.requestAnimationFrame(initSalesCharts);
        window.setTimeout(initSalesCharts, 250);
    });

    window.addEventListener('load', initSalesCharts);
</script>

<?= $this->endSection() ?>
