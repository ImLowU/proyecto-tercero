(function () {
  const target = document.getElementById('calendar');
  if (!target) return;

  const torneos = Array.isArray(window.TORNEOS_CAL) ? window.TORNEOS_CAL : [];

  function normalizeDateValue(value) {
    if (value === null || value === undefined) return '';
    const text = String(value).trim();
    if (!text) return '';
    return text.split('T')[0].split(' ')[0];
  }

  function parseYmd(value) {
    const text = normalizeDateValue(value);
    if (!/^\d{4}-\d{2}-\d{2}$/.test(text)) return null;
    const [year, month, day] = text.split('-').map(Number);
    return { year, month, day };
  }

  // Formatea YYYY-MM-DD -> DD/MM sin crear Date para evitar desplazamientos por zona horaria.
  function fmtDM(value) {
    const parsed = parseYmd(value);
    if (!parsed) return '';
    const { day, month } = parsed;
    return `${String(day).padStart(2, '0')}/${String(month).padStart(2, '0')}`;
  }

  function formatRange(inicio, fin) {
    const start = normalizeDateValue(inicio);
    if (!start) return '';

    const end = normalizeDateValue(fin);
    if (!end) return fmtDM(start);
    if (start === end) return fmtDM(start);
    return `${fmtDM(start)} – ${fmtDM(end)}`;
  }

  let currentMonth = new Date();
  currentMonth.setDate(1);

  function render() {
    target.innerHTML = '';

    const header = document.createElement('div');
    header.style.display = 'flex';
    header.style.justifyContent = 'space-between';
    header.style.alignItems = 'center';
    header.style.marginBottom = '.6rem';

    const prev = document.createElement('button');
    prev.className = 'btn';
    prev.textContent = '<';
    prev.onclick = () => {
      currentMonth.setMonth(currentMonth.getMonth() - 1);
      render();
    };

    const next = document.createElement('button');
    next.className = 'btn';
    next.textContent = '>';
    next.onclick = () => {
      currentMonth.setMonth(currentMonth.getMonth() + 1);
      render();
    };

    const monthTitle = document.createElement('div');
    monthTitle.innerHTML = '<strong>' + currentMonth.toLocaleString(undefined, { month: 'long', year: 'numeric' }) + '</strong>';

    header.appendChild(prev);
    header.appendChild(monthTitle);
    header.appendChild(next);
    target.appendChild(header);

    const grid = document.createElement('div');
    grid.style.display = 'grid';
    grid.style.gridTemplateColumns = 'repeat(7, 1fr)';
    grid.style.gap = '4px';

    ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'].forEach((dayLabel) => {
      const head = document.createElement('div');
      head.style.fontSize = '.8rem';
      head.style.textAlign = 'center';
      head.style.color = 'var(--muted)';
      head.textContent = dayLabel;
      grid.appendChild(head);
    });

    const firstWeekday = (new Date(currentMonth.getFullYear(), currentMonth.getMonth(), 1).getDay() + 6) % 7;
    const daysInMonth = new Date(currentMonth.getFullYear(), currentMonth.getMonth() + 1, 0).getDate();

    for (let i = 0; i < firstWeekday; i++) {
      grid.appendChild(document.createElement('div'));
    }

    const today = new Date();
    const todayStr = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;

    for (let day = 1; day <= daysInMonth; day++) {
      const cell = document.createElement('div');
      cell.style.minHeight = '80px';
      cell.style.padding = '8px';
      cell.style.borderRadius = '6px';
      cell.style.background = 'transparent';
      cell.style.border = '1px solid rgba(0,0,0,0.04)';
      cell.style.display = 'flex';
      cell.style.flexDirection = 'column';
      cell.style.gap = '6px';
      cell.style.boxSizing = 'border-box';

      const dateStr = `${currentMonth.getFullYear()}-${String(currentMonth.getMonth() + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;

      const dayTitle = document.createElement('div');
      dayTitle.style.fontSize = '.85rem';
      dayTitle.style.marginBottom = '2px';
      dayTitle.style.color = 'var(--text)';
      dayTitle.textContent = day;
      cell.appendChild(dayTitle);

      if (dateStr === todayStr) {
        cell.style.background = 'linear-gradient(90deg, rgba(255,245,157,0.12), rgba(255,245,157,0.06))';
        cell.style.border = '1px solid rgba(255,213,79,0.25)';
      }

      const matches = torneos.filter((torneo) => {
        const inicio = normalizeDateValue(torneo && torneo.inicio);
        return inicio === dateStr;
      });

      if (matches.length) {
        const list = document.createElement('div');
        list.style.display = 'flex';
        list.style.flexDirection = 'column';
        list.style.gap = '6px';
        list.style.width = '100%';

        matches.forEach((torneo) => {
          const href = torneo && torneo.id !== undefined && torneo.id !== null && torneo.id !== ''
            ? `/torneo/${torneo.id}`
            : '#';

          const link = document.createElement('a');
          link.href = href;
          link.style.display = 'block';
          link.style.textDecoration = 'none';
          link.style.color = 'inherit';
          link.style.width = '100%';
          link.style.boxSizing = 'border-box';

          const card = document.createElement('div');
          card.style.padding = '.4rem .5rem';
          card.style.borderRadius = '8px';
          card.style.background = 'rgba(255,255,255,0.03)';
          card.style.border = '1px solid rgba(255,255,255,0.03)';
          card.style.display = 'flex';
          card.style.flexDirection = 'column';
          card.style.gap = '4px';
          card.style.overflow = 'hidden';

          const nombre = document.createElement('div');
          nombre.style.fontSize = '0.9rem';
          nombre.style.fontWeight = '700';
          nombre.style.color = 'var(--text)';
          nombre.style.whiteSpace = 'nowrap';
          nombre.style.overflow = 'hidden';
          nombre.style.textOverflow = 'ellipsis';
          nombre.textContent = torneo && torneo.nombre ? torneo.nombre : '';

          const rango = document.createElement('div');
          rango.style.fontSize = '.8rem';
          rango.style.color = 'var(--muted)';
          rango.textContent = formatRange(torneo && torneo.inicio, torneo && torneo.fin);

          card.appendChild(nombre);
          card.appendChild(rango);
          link.appendChild(card);
          list.appendChild(link);
        });

        cell.appendChild(list);
      }

      grid.appendChild(cell);
    }

    target.appendChild(grid);
  }

  render();
})();
