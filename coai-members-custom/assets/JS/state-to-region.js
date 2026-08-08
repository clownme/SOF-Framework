document.addEventListener('DOMContentLoaded', function () {
  const stateToRegion = {
    'AL': 'South',
    'AR': 'South',
    'DE': 'South',
    'DC': 'South',
    'FL': 'South',
    'GA': 'South',
    'KY': 'South',
    'LA': 'South',
    'MD': 'South',
    'MS': 'South',
    'NC': 'South',
    'OK': 'South',
    'SC': 'South',
    'TN': 'South',
    'TX': 'South',
    'VA': 'South',
    'WV': 'South',
    'CT': 'Northeast',
    'ME': 'Northeast',
    'MA': 'Northeast',
    'NH': 'Northeast',
    'NJ': 'Northeast',
    'NY': 'Northeast',
    'PA': 'Northeast',
    'RI': 'Northeast',
    'VT': 'Northeast',
    'IL': 'Midwest',
    'IN': 'Midwest',
    'IA': 'Midwest',
    'KS': 'Midwest',
    'MI': 'Midwest',
    'MN': 'Midwest',
    'NE': 'Midwest',
    'ND': 'Midwest',
    'OH': 'Midwest',
    'SD': 'Midwest',
    'WI': 'Midwest',
    'AK': 'West',
    'AZ': 'West',
    'CA': 'West',
    'CO': 'West',
    'HI': 'West',
    'ID': 'West',
    'MT': 'West',
    'NV': 'West',
    'NM': 'West',
    'OR': 'West',
    'UT': 'West',
    'WA': 'West',
    'WY': 'West',
  };

  const stateField = document.getElementById('member_state');
  const regionField = document.getElementById('member_region');

  if (stateField && regionField) {
    stateField.addEventListener('change', function () {
      const state = stateField.value.toUpperCase();
      regionField.value = stateToRegion[state] || '';
    });
  }
});