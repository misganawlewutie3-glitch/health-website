/* =====================================================================
   UoGSH-PIMS  -  assets/js/app.js
   Plain JavaScript (no libraries). Every feature is attached by
   looking for data-* attributes in the HTML, so one file serves all pages.

   1. Mobile sidebar        5. Show/hide password
   2. Tabs                  6. Age & BMI calculators
   3. Form validation       7. Dynamic rows (labs / drugs)
   4. Confirm + print       8. Live patient search (fetch + JSON)
                            9. Password strength, dept->doctor filter,
                               auto-hide alerts, count-up numbers
   ===================================================================== */
document.addEventListener('DOMContentLoaded', () => {

  /* ---------- 1. Mobile sidebar toggle ---------- */
  const menuBtn = document.getElementById('menuBtn');
  const sidebar = document.getElementById('sidebar');
  if (menuBtn && sidebar) {
    menuBtn.addEventListener('click', () => sidebar.classList.toggle('open'));
  }

  /* ---------- 2. Tabs: <div data-tabs> buttons[data-tab=panelId] ---------- */
  document.querySelectorAll('[data-tabs]').forEach(group => {
    group.querySelectorAll('button[data-tab]').forEach(btn => {
      btn.addEventListener('click', () => {
        group.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
        btn.classList.add('active');
        document.getElementById(btn.dataset.tab).classList.add('active');
      });
    });
  });

  /* ---------- 3. Client-side form validation ----------
     (Convenience only - the PHP server ALWAYS validates again.) */
  document.querySelectorAll('form[data-validate]').forEach(form => {
    form.addEventListener('submit', ev => {
      let ok = true;
      form.querySelectorAll('.field-error').forEach(n => n.remove());
      form.querySelectorAll('[required], [pattern]').forEach(field => {
        field.classList.remove('invalid');
        const empty = field.hasAttribute('required') && field.value.trim() === '';
        const badPattern = field.pattern && field.value !== '' && !new RegExp('^(?:' + field.pattern + ')$').test(field.value);
        if (empty || badPattern) {
          ok = false;
          field.classList.add('invalid');
          const msg = document.createElement('div');
          msg.className = 'field-error';
          msg.textContent = empty ? 'This field is required.' : 'Wrong format.';
          field.insertAdjacentElement('afterend', msg);
        }
      });
      if (!ok) {
        ev.preventDefault();
        const first = form.querySelector('.invalid');
        if (first) first.focus();
      }
    });
  });

  /* ---------- 4. Confirm dialogs + print button ---------- */
  document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', ev => {
      if (!confirm(el.dataset.confirm)) ev.preventDefault();
    });
  });
  document.querySelectorAll('[data-print]').forEach(el =>
    el.addEventListener('click', () => window.print()));

  /* ---------- 5. Show / hide password ---------- */
  document.querySelectorAll('[data-toggle-pw]').forEach(btn => {
    btn.addEventListener('click', () => {
      const input = document.getElementById(btn.dataset.togglePw);
      const show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      btn.textContent = show ? 'Hide' : 'Show';
    });
  });

  /* ---------- 6a. Age display from date of birth ---------- */
  const dob = document.getElementById('dob'), ageOut = document.getElementById('ageOut');
  if (dob && ageOut) {
    const showAge = () => {
      if (!dob.value) { ageOut.textContent = ''; return; }
      const b = new Date(dob.value), t = new Date();
      let y = t.getFullYear() - b.getFullYear();
      const m = t.getMonth() - b.getMonth();
      if (m < 0 || (m === 0 && t.getDate() < b.getDate())) y--;
      ageOut.textContent = y >= 1 ? 'Age: ' + y + ' years' : 'Age: under 1 year';
    };
    dob.addEventListener('change', showAge);
    showAge();
  }

  /* ---------- 6b. BMI = kg / (m x m), with category ---------- */
  const wt = document.getElementById('wt'), ht = document.getElementById('ht'), bmi = document.getElementById('bmi');
  if (wt && ht && bmi) {
    const calc = () => {
      const w = parseFloat(wt.value), h = parseFloat(ht.value) / 100;
      if (!w || !h) { bmi.value = ''; return; }
      const v = w / (h * h);
      const cat = v < 18.5 ? 'Underweight' : v < 25 ? 'Normal' : v < 30 ? 'Overweight' : 'Obese';
      bmi.value = v.toFixed(1) + ' (' + cat + ')';
    };
    wt.addEventListener('input', calc);
    ht.addEventListener('input', calc);
  }

  /* ---------- 7. Add a new row to the lab / prescription lists ----------
     Clones the first row, clears the inputs, appends it. */
  document.querySelectorAll('[data-add-row]').forEach(btn => {
    btn.addEventListener('click', () => {
      const box = document.getElementById(btn.dataset.addRow);
      const clone = box.firstElementChild.cloneNode(true);
      clone.querySelectorAll('input').forEach(i => { i.value = i.type === 'number' ? 5 : ''; });
      box.appendChild(clone);
      clone.querySelector('input').focus();
    });
  });

  /* ---------- 8. Live patient search (AJAX with fetch) ----------
     Typing in #patientSearch calls api_patient_search.php, shows matches
     in #suggest and stores the chosen patient id in #patient_id. */
  const ps = document.getElementById('patientSearch');
  const box = document.getElementById('suggest');
  const pid = document.getElementById('patient_id');
  if (ps && box && pid) {
    let timer;
    ps.addEventListener('input', () => {
      pid.value = '';                                // typing again clears the old choice
      clearTimeout(timer);                           // debounce: wait 250 ms after last key
      const q = ps.value.trim();
      if (q.length < 2) { box.innerHTML = ''; return; }
      timer = setTimeout(async () => {
        try {
          const res = await fetch('api_patient_search.php?q=' + encodeURIComponent(q));
          const list = await res.json();
          const ul = document.createElement('ul');
          list.forEach(p => {
            const li = document.createElement('li');
            li.textContent = `${p.first_name} ${p.middle_name} ${p.last_name} (${p.mrn})`;   // textContent = safe from XSS
            li.addEventListener('click', () => {
              pid.value = p.id;
              ps.value = li.textContent;
              box.innerHTML = '';
            });
            ul.appendChild(li);
          });
          box.innerHTML = '';
          if (list.length) box.appendChild(ul);
        } catch (err) { box.innerHTML = ''; }
      }, 250);
    });
    document.addEventListener('click', ev => { if (ev.target !== ps) box.innerHTML = ''; });
    // Do not allow submit without a chosen patient
    ps.closest('form').addEventListener('submit', ev => {
      if (!pid.value) { ev.preventDefault(); alert('Please choose a patient from the list.'); ps.focus(); }
    });
  }

  /* ---------- 9a. Password strength meter (users.php) ---------- */
  const npw = document.getElementById('newpw'), bar = document.getElementById('meterBar'), txt = document.getElementById('meterTxt');
  if (npw && bar) {
    npw.addEventListener('input', () => {
      const v = npw.value;
      let score = 0;
      if (v.length >= 8) score++;
      if (/[A-Z]/.test(v) && /[a-z]/.test(v)) score++;
      if (/\d/.test(v)) score++;
      if (/[^A-Za-z0-9]/.test(v)) score++;
      const colors = ['#c0392b', '#d98a0b', '#d98a0b', '#2e9d6b', '#2e9d6b'];
      const names = ['Very weak', 'Weak', 'Fair', 'Good', 'Strong'];
      bar.style.width = (score * 25) + '%';
      bar.style.background = colors[score];
      txt.textContent = v ? names[score] : '';
    });
  }

  /* ---------- 9b. Appointment: only show doctors of chosen department ---------- */
  const dept = document.getElementById('apptDept'), doc = document.getElementById('apptDoctor');
  if (dept && doc) {
    dept.addEventListener('change', () => {
      doc.value = '';
      [...doc.options].forEach(o => {
        if (!o.dataset.dept) return;
        o.hidden = o.disabled = (o.dataset.dept !== dept.value);
      });
    });
  }

  /* ---------- 9c. Auto-hide flash messages after 5 s ---------- */
  document.querySelectorAll('[data-autohide]').forEach(a =>
    setTimeout(() => { a.style.transition = 'opacity .5s'; a.style.opacity = 0; setTimeout(() => a.remove(), 500); }, 5000));

  /* ---------- 9d. Count-up animation for dashboard numbers ---------- */
  document.querySelectorAll('[data-count]').forEach(el => {
    const target = parseInt(el.dataset.count, 10);
    if (!target) return;
    let n = 0; const step = Math.max(1, Math.ceil(target / 30));
    const t = setInterval(() => { n = Math.min(target, n + step); el.textContent = n; if (n >= target) clearInterval(t); }, 25);
  });
});
