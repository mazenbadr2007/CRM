<?php
require_once __DIR__ . '/../src/auth/auth_check.php';
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/access_helper.php';
$user_id = $_SESSION['user_id'];
// Role-based scope (access_helper.php): company_admin = whole company,
// sales_manager = own + own team's leads, sales_employee = own assigned leads only.
$company_id = $_SESSION['company_id'] ?? null;
$sql = "SELECT c.id, c.client_name, c.phone_number, c.call_outcome, c.note, c.event_title, c.event_date, c.event_time FROM clients c WHERE c.event_date IS NOT NULL AND c.company_id = ? ";
$filter = access_user_filter($conn, 'c.user_id');
$sql .= $filter['sql'];
$stmt = $conn->prepare($sql);
$types = 'i' . $filter['types'];
$params = array_merge([$company_id], $filter['params']);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$events = [];
while ($row = $result->fetch_assoc()) {
    $events[] = [
        'title' => $row['client_name'], 
        'start' => $row['event_date'] . ($row['event_time'] ? 'T' . $row['event_time'] : ''),
        'extendedProps' => [
            'clientId'    => $row['id'],
            'eventTitle'  => $row['event_title'],
            'clientName'  => $row['client_name'],
            'phone'       => $row['phone_number'],
            'callOutcome' => $row['call_outcome'],
            'note'        => $row['note']
        ]
    ];
}
$stmt->close();

require_once __DIR__ . '/../src/templates/header.php';
?>


<script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js'></script>
<link rel="stylesheet" href="css/calendar.css">

<div class="container-fluid">
    <div class="page-header">
        <h1 class="page-title" data-translate-key="calendar_title">Calendar</h1>
        <p class="page-subtitle" data-translate-key="calendar_subtitle">View and manage all your scheduled events and follow-ups.</p>
    </div>
    <div class="card calendar-card">
        <div class="card-body">
            <div id='calendar'></div>
        </div>
    </div>
</div>


<div class="modal fade" id="eventModal" tabindex="-1" aria-labelledby="eventModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="eventModalLabel" data-translate-key="modal_follow_up_details">Follow-up Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <h4 id="modal-event-title" class="mb-4"></h4>
        <p><strong data-translate-key="modal_client_name">Client Name:</strong> <span id="modal-client-name"></span></p>
        <p><strong data-translate-key="modal_phone">Phone:</strong> <span id="modal-phone"></span></p>
        <p><strong data-translate-key="modal_call_outcome">Call Outcome:</strong> <span id="modal-call-outcome"></span></p>
        <p><strong data-translate-key="modal_note">Note:</strong></p>
        <p id="modal-note" class="note-box"></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" data-translate-key="modal_close_btn">Close</button>
        <a href="#" id="update-event-btn" class="btn btn-primary">
            <i class="fa-solid fa-pen-to-square me-2"></i><span data-translate-key="modal_update_client_btn">Update Client</span>
        </a>
      </div>
    </div>
  </div>
</div>


<script>
document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');
    if (calendarEl) {
        var eventModal = new bootstrap.Modal(document.getElementById('eventModal'));
        var updateBtn = document.getElementById('update-event-btn');

       
        const currentLang = localStorage.getItem('language') || 'en';
        
       
        const arLocale = {
            code: 'ar',
            week: { dow: 6, doy: 12 }, 
            direction: 'rtl',
            buttonText: {
                prev: 'السابق',
                next: 'التالي',
                today: 'اليوم',
                month: 'شهر',
                week: 'أسبوع',
                day: 'يوم',
                list: 'قائمة',
            },
            weekText: 'أسبوع',
            allDayText: 'طوال اليوم',
            moreLinkText: 'أخرى',
            noEventsText: 'لا توجد أحداث لعرضها',
        };

        var calendar = new FullCalendar.Calendar(calendarEl, {
            
            locales: [arLocale],
            
            locale: currentLang,
            direction: currentLang === 'ar' ? 'rtl' : 'ltr',

            initialView: 'dayGridMonth',
            headerToolbar: {
                
                start: 'prev,next today',
                center: 'title',
                end: 'dayGridMonth,timeGridWeek,listWeek'
            },
            events: <?php echo json_encode($events); ?>,
            
            eventClick: function(info) {
                const lang = document.documentElement.lang || 'en';
                const noTitleText = (window.tr ? window.tr('no_title', 'No Title') : 'No Title');
                const noNotesText = (window.tr ? window.tr('no_notes', 'No notes.') : 'No notes.');
                document.getElementById('modal-event-title').innerText = info.event.extendedProps.eventTitle || noTitleText;
                document.getElementById('modal-client-name').innerText = info.event.extendedProps.clientName;
                document.getElementById('modal-phone').innerText = info.event.extendedProps.phone;
                document.getElementById('modal-call-outcome').innerText = info.event.extendedProps.callOutcome;
                document.getElementById('modal-note').innerText = info.event.extendedProps.note || noNotesText;
                updateBtn.href = `client_registration.php?id=${info.event.extendedProps.clientId}`;
                eventModal.show();
            }
        });

        calendar.render();

        
        document.addEventListener('languageChanged', (e) => {
            const newLang = e.detail.lang;
            calendar.setOption('locale', newLang);
            calendar.setOption('direction', newLang === 'ar' ? 'rtl' : 'ltr');
        });
    }
});
</script>

<?php
require_once __DIR__ . '/../src/templates/footer.php';
?>