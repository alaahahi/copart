const baghdadFormatter = new Intl.DateTimeFormat('en-GB', {
  timeZone: 'Asia/Baghdad',
  year: 'numeric',
  month: '2-digit',
  day: '2-digit',
  hour: '2-digit',
  minute: '2-digit',
  second: '2-digit',
  hour12: false,
});

export function formatBaghdadTimestamp(value, fallback = '—') {
  if (!value) {
    return fallback;
  }

  try {
    const date = value instanceof Date ? value : new Date(value);
    if (Number.isNaN(date.getTime())) {
      return String(value).replace('T', ' ');
    }
    return baghdadFormatter.format(date).replace(',', '');
  } catch (error) {
    return String(value).replace('T', ' ');
  }
}
