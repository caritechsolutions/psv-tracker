'use strict';

// Rider account: submit a rating for an un-rated ride (vehicle + driver stars +
// optional comment) without a full reload. The driver score the rider gives is
// their own input; aggregate driver ratings are never shown to riders.
(function () {
  document.addEventListener('click', function (e) {
    var btn = e.target.closest && e.target.closest('.rate-submit');
    if (!btn) return;

    var form = btn.closest('.rate-form');
    var msg = form.querySelector('.rate-msg');
    var rideId = form.getAttribute('data-ride');
    var v = form.querySelector('.rate-vehicle').value;
    var d = form.querySelector('.rate-driver').value;
    var comment = form.querySelector('.rate-comment').value;

    if (!v || !d) { msg.textContent = 'Pick vehicle and driver stars.'; return; }

    btn.disabled = true;
    msg.textContent = 'Saving…';
    var body = new URLSearchParams({
      csrf: window.RIDER_CSRF || '', ride_id: rideId,
      vehicle_stars: v, driver_stars: d, comment: comment
    });
    fetch('rate.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body, credentials: 'same-origin'
    })
      .then(function (r) { return r.json().then(function (j) { return { status: r.status, j: j }; }); })
      .then(function (res) {
        var j = res.j || {};
        if (j.ok && (j.status === 'rated' || j.status === 'already_rated')) {
          var slot = form.closest('.rate-slot');
          var st = function (n) { return '★★★★★☆☆☆☆☆'.slice(5 - n, 10 - n); };
          slot.innerHTML = '<div class="rated">You rated · Vehicle <span class="stars">' +
            st(+v) + '</span> · Driver <span class="stars">' + st(+d) + '</span></div>';
        } else if (res.status === 401) {
          msg.textContent = 'Please sign in again.';
          btn.disabled = false;
        } else {
          msg.textContent = (j.error === 'ride_not_found') ? 'That ride is not available to rate.'
            : (j.error || 'Could not save. Try again.');
          btn.disabled = false;
        }
      })
      .catch(function () { msg.textContent = 'Could not save. Try again.'; btn.disabled = false; });
  });
})();
