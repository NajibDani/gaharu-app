@props(['startName' => 'start_date', 'endName' => 'end_date'])

<div class="col-lg-4 col-md-5 position-relative">
    <label class="form-label fw-semibold small mb-1" style="font-size:12px;">Filter Tanggal</label>
    <input type="hidden" name="{{ $startName }}" id="filter_dari" value="{{ request($startName) }}">
    <input type="hidden" name="{{ $endName }}" id="filter_sampai" value="{{ request($endName) }}">
    
    <button type="button" class="btn btn-sm btn-outline-secondary bg-white text-dark w-100 d-flex align-items-center justify-content-between py-1 px-2 rounded-3 shadow-none border" id="btn-date-range-trigger" style="min-height: 31px;">
        <span id="date-range-label" class="small text-truncate">
            <i class="bi bi-calendar3 me-1 text-primary"></i> <span id="text-date-display">Semua Tanggal</span>
        </span>
        <i class="bi bi-chevron-down small text-muted ms-1"></i>
    </button>

    {{-- POPOVER DATE RANGE PICKER --}}
    <div id="date-range-popover" class="card border-0 shadow-lg rounded-4 p-3 position-absolute" style="display:none; z-index:1060; width: 680px; max-width: 90vw; top: 105%; left: 0; background: #fff; border: 1px solid #e2e8f0 !important;">
        <div class="d-flex gap-3">
            <!-- LEFT PRESETS -->
            <div class="d-flex flex-column gap-1 flex-shrink-0" style="width: 130px;">
                <button type="button" class="btn btn-sm btn-outline-secondary text-start fw-medium btn-preset-range py-1 px-2" style="font-size:0.78rem;" data-preset="today">Hari Ini</button>
                <button type="button" class="btn btn-sm btn-outline-secondary text-start fw-medium btn-preset-range py-1 px-2" style="font-size:0.78rem;" data-preset="yesterday">Kemarin</button>
                <button type="button" class="btn btn-sm btn-outline-secondary text-start fw-medium btn-preset-range py-1 px-2" style="font-size:0.78rem;" data-preset="this_week">Minggu Ini</button>
                <button type="button" class="btn btn-sm btn-outline-secondary text-start fw-medium btn-preset-range py-1 px-2" style="font-size:0.78rem;" data-preset="last_week">Minggu Lalu</button>
                <button type="button" class="btn btn-sm btn-outline-secondary text-start fw-medium btn-preset-range py-1 px-2" style="font-size:0.78rem;" data-preset="this_month">Bulan Ini</button>
                <button type="button" class="btn btn-sm btn-outline-secondary text-start fw-medium btn-preset-range py-1 px-2" style="font-size:0.78rem;" data-preset="last_month">Bulan Lalu</button>
                <button type="button" class="btn btn-sm btn-outline-secondary text-start fw-medium btn-preset-range py-1 px-2" style="font-size:0.78rem;" data-preset="this_year">Tahun Ini</button>
                <button type="button" class="btn btn-sm btn-outline-secondary text-start fw-medium btn-preset-range py-1 px-2" style="font-size:0.78rem;" data-preset="last_year">Tahun Lalu</button>
            </div>

            <!-- MIDDLE CALENDAR -->
            <div class="flex-grow-1 px-2 border-start border-end">
                <div class="d-flex justify-content-between align-items-center mb-2 px-1">
                    <button type="button" class="btn btn-xs btn-light border rounded-circle p-1" id="cal-prev-month" title="Bulan Sebelumnya">
                        <i class="bi bi-chevron-left"></i>
                    </button>
                    <div class="fw-bold text-dark font-monospace" id="cal-month-year-title" style="font-size: 0.9rem;"></div>
                    <button type="button" class="btn btn-xs btn-light border rounded-circle p-1" id="cal-next-month" title="Bulan Selanjutnya">
                        <i class="bi bi-chevron-right"></i>
                    </button>
                </div>

                <div class="d-grid mb-1 text-center fw-bold text-muted" style="grid-template-columns: repeat(7, 1fr); font-size: 0.7rem;">
                    <div>MIN</div><div>SEN</div><div>SEL</div><div>RAB</div><div>KAM</div><div>JUM</div><div>SAB</div>
                </div>

                <div class="d-grid text-center" id="cal-days-grid" style="grid-template-columns: repeat(7, 1fr); gap: 2px;">
                </div>
            </div>

            <!-- RIGHT SUMMARY & ACTIONS -->
            <div class="d-flex flex-column justify-content-between flex-shrink-0" style="width: 140px;">
                <div>
                    <div class="mb-2">
                        <label class="form-label text-muted small mb-1" style="font-size:0.72rem;">Mulai</label>
                        <input type="text" id="display-range-start" class="form-control form-control-sm text-center bg-light fw-bold" style="font-size:0.75rem;" readonly placeholder="dd/mm/yyyy">
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small mb-1" style="font-size:0.72rem;">Selesai</label>
                        <input type="text" id="display-range-end" class="form-control form-control-sm text-center bg-light fw-bold" style="font-size:0.75rem;" readonly placeholder="dd/mm/yyyy">
                    </div>
                </div>

                <div class="d-grid gap-1">
                    <button type="button" class="btn btn-primary btn-sm fw-bold shadow-sm py-1" id="btn-apply-date-range" style="background-color: #d88656; border-color: #d88656;">
                        Apply
                    </button>
                    <button type="button" class="btn btn-light btn-sm text-muted py-1" id="btn-reset-date-range" style="font-size:0.75rem;">
                        Reset
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.day-cell { transition: all 0.15s ease-in-out; }
.day-cell:hover { background-color: #f8f9fa; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const btnTrigger   = document.getElementById('btn-date-range-trigger');
    const popover      = document.getElementById('date-range-popover');
    const inputDari    = document.getElementById('filter_dari');
    const inputSampai  = document.getElementById('filter_sampai');
    const textDisplay  = document.getElementById('text-date-display');
    const displayStart = document.getElementById('display-range-start');
    const displayEnd   = document.getElementById('display-range-end');

    const calTitle     = document.getElementById('cal-month-year-title');
    const calDaysGrid  = document.getElementById('cal-days-grid');
    const btnPrevMonth = document.getElementById('cal-prev-month');
    const btnNextMonth = document.getElementById('cal-next-month');
    const btnApply     = document.getElementById('btn-apply-date-range');
    const btnReset     = document.getElementById('btn-reset-date-range');

    const monthNamesIndo = ['JANUARI', 'FEBRUARI', 'MARET', 'APRIL', 'MEI', 'JUNI', 'JULI', 'AGUSTUS', 'SEPTEMBER', 'OKTOBER', 'NOVEMBER', 'DESEMBER'];

    let activeYear  = new Date().getFullYear();
    let activeMonth = new Date().getMonth();
    let selStart    = inputDari ? inputDari.value : '';
    let selEnd      = inputSampai ? inputSampai.value : '';

    function formatDateToYMD(d) {
        if (!d) return '';
        let y = d.getFullYear();
        let m = String(d.getMonth() + 1).padStart(2, '0');
        let day = String(d.getDate()).padStart(2, '0');
        return `${y}-${m}-${day}`;
    }

    function formatDateToDMY(ymdStr) {
        if (!ymdStr) return '';
        let parts = ymdStr.split('-');
        if (parts.length !== 3) return ymdStr;
        return `${parts[2]}/${parts[1]}/${parts[0]}`;
    }

    function updateTriggerDisplay() {
        let dVal = inputDari ? inputDari.value : '';
        let sVal = inputSampai ? inputSampai.value : '';
        if (dVal && sVal) {
            if (dVal === sVal) {
                textDisplay.innerText = formatDateToDMY(dVal);
            } else {
                textDisplay.innerText = `${formatDateToDMY(dVal)} - ${formatDateToDMY(sVal)}`;
            }
        } else if (dVal) {
            textDisplay.innerText = `Dari ${formatDateToDMY(dVal)}`;
        } else if (sVal) {
            textDisplay.innerText = `Sampai ${formatDateToDMY(sVal)}`;
        } else {
            textDisplay.innerText = 'Semua Tanggal';
        }
    }

    function updateSummaryInputs() {
        if (displayStart) displayStart.value = formatDateToDMY(selStart);
        if (displayEnd) displayEnd.value = formatDateToDMY(selEnd || selStart);
    }

    function renderCalendar() {
        if (!calTitle || !calDaysGrid) return;
        calTitle.innerText = `${monthNamesIndo[activeMonth]} ${activeYear}`;
        calDaysGrid.innerHTML = '';

        let firstDayIndex = new Date(activeYear, activeMonth, 1).getDay();
        let totalDaysInMonth = new Date(activeYear, activeMonth + 1, 0).getDate();
        let prevMonthTotalDays = new Date(activeYear, activeMonth, 0).getDate();

        // Prev month days
        for (let x = firstDayIndex; x > 0; x--) {
            let dayNum = prevMonthTotalDays - x + 1;
            let el = document.createElement('div');
            el.className = 'py-1 text-muted opacity-25 small';
            el.innerText = dayNum;
            calDaysGrid.appendChild(el);
        }

        // Current month days
        for (let i = 1; i <= totalDaysInMonth; i++) {
            let monthStr = String(activeMonth + 1).padStart(2, '0');
            let dayStr = String(i).padStart(2, '0');
            let ymd = `${activeYear}-${monthStr}-${dayStr}`;

            let el = document.createElement('div');
            el.className = 'py-1 rounded-2 small cursor-pointer day-cell fw-semibold';
            el.innerText = i;
            el.style.cursor = 'pointer';

            let isStart = (ymd === selStart);
            let isEnd = (ymd === (selEnd || selStart));
            let inRange = false;

            if (selStart && selEnd && ymd > selStart && ymd < selEnd) {
                inRange = true;
            }

            if (isStart || isEnd) {
                el.classList.add('bg-primary', 'text-white', 'shadow-sm');
            } else if (inRange) {
                el.classList.add('bg-primary-subtle', 'text-primary-emphasis');
            } else {
                el.classList.add('text-dark');
                el.addEventListener('mouseenter', () => el.classList.add('bg-light'));
                el.addEventListener('mouseleave', () => el.classList.remove('bg-light'));
            }

            el.addEventListener('click', function () {
                if (!selStart || (selStart && selEnd)) {
                    selStart = ymd;
                    selEnd = '';
                } else if (selStart && !selEnd) {
                    if (ymd < selStart) {
                        selEnd = selStart;
                        selStart = ymd;
                    } else {
                        selEnd = ymd;
                    }
                }
                updateSummaryInputs();
                renderCalendar();
            });

            calDaysGrid.appendChild(el);
        }
    }

    function applyPreset(presetKey) {
        let now = new Date();
        let y = now.getFullYear();
        let m = now.getMonth();
        let d = now.getDate();
        let dayOfWeek = now.getDay();

        let startDate, endDate;

        switch (presetKey) {
            case 'today':
                startDate = new Date(y, m, d);
                endDate = new Date(y, m, d);
                break;
            case 'yesterday':
                startDate = new Date(y, m, d - 1);
                endDate = new Date(y, m, d - 1);
                break;
            case 'this_week':
                let diffMon = d - (dayOfWeek === 0 ? 6 : dayOfWeek - 1);
                startDate = new Date(y, m, diffMon);
                endDate = new Date(y, m, diffMon + 6);
                break;
            case 'last_week':
                let diffLastMon = d - (dayOfWeek === 0 ? 6 : dayOfWeek - 1) - 7;
                startDate = new Date(y, m, diffLastMon);
                endDate = new Date(y, m, diffLastMon + 6);
                break;
            case 'this_month':
                startDate = new Date(y, m, 1);
                endDate = new Date(y, m + 1, 0);
                break;
            case 'last_month':
                startDate = new Date(y, m - 1, 1);
                endDate = new Date(y, m, 0);
                break;
            case 'this_year':
                startDate = new Date(y, 0, 1);
                endDate = new Date(y, 11, 31);
                break;
            case 'last_year':
                startDate = new Date(y - 1, 0, 1);
                endDate = new Date(y - 1, 11, 31);
                break;
        }

        selStart = formatDateToYMD(startDate);
        selEnd = formatDateToYMD(endDate);

        activeYear = startDate.getFullYear();
        activeMonth = startDate.getMonth();

        document.querySelectorAll('.btn-preset-range').forEach(b => {
            b.classList.remove('btn-primary', 'text-white');
            b.classList.add('btn-outline-secondary');
        });

        let activeBtn = document.querySelector(`.btn-preset-range[data-preset="${presetKey}"]`);
        if (activeBtn) {
            activeBtn.classList.remove('btn-outline-secondary');
            activeBtn.classList.add('btn-primary', 'text-white');
        }

        updateSummaryInputs();
        renderCalendar();
    }

    if (btnTrigger) {
        btnTrigger.addEventListener('click', function (e) {
            e.stopPropagation();
            if (popover.style.display === 'none') {
                popover.style.display = 'block';
                updateSummaryInputs();
                renderCalendar();
            } else {
                popover.style.display = 'none';
            }
        });
    }

    document.querySelectorAll('.btn-preset-range').forEach(btn => {
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            applyPreset(this.getAttribute('data-preset'));
        });
    });

    if (btnPrevMonth) {
        btnPrevMonth.addEventListener('click', function (e) {
            e.stopPropagation();
            activeMonth--;
            if (activeMonth < 0) {
                activeMonth = 11;
                activeYear--;
            }
            renderCalendar();
        });
    }

    if (btnNextMonth) {
        btnNextMonth.addEventListener('click', function (e) {
            e.stopPropagation();
            activeMonth++;
            if (activeMonth > 11) {
                activeMonth = 0;
                activeYear++;
            }
            renderCalendar();
        });
    }

    if (btnApply) {
        btnApply.addEventListener('click', function () {
            if (inputDari) inputDari.value = selStart;
            if (inputSampai) inputSampai.value = selEnd;
            updateTriggerDisplay();
            popover.style.display = 'none';
            btnApply.closest('form').submit();
        });
    }

    if (btnReset) {
        btnReset.addEventListener('click', function () {
            selStart = '';
            selEnd = '';
            if (inputDari) inputDari.value = '';
            if (inputSampai) inputSampai.value = '';
            updateSummaryInputs();
            updateTriggerDisplay();
            popover.style.display = 'none';
            btnReset.closest('form').submit();
        });
    }

    if (popover) {
        popover.addEventListener('click', function (e) {
            e.stopPropagation();
        });
    }

    document.addEventListener('click', function (e) {
        if (popover && popover.style.display === 'block') {
            popover.style.display = 'none';
        }
    });

    updateTriggerDisplay();
});
</script>
