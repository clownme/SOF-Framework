<?php
function coai_render_finance_admin_tools($me, $members) {
    if (!is_object($me) || !property_exists($me, 'usergroup')) {
        return '<p style="color:red;">Invalid member object.</p>';
    }

    error_log('✅ Finance function reached. Usergroup: ' . ($me->usergroup ?? 'null'));
    error_log('✅ Members passed: ' . count($members));

if (empty($members)) {
    return '<p style="color:red;">Finance tools are not available yet.</p>';
}

    ob_start(); ?>
    <div class="coai-admin-tools">
      <h3>Finance Tools</h3>
      <button id="export-finance-csv" style="margin-bottom:1rem;">Export to CSV</button>
      <style>
      .coai-admin-table {
          width: 100%;
          border-collapse: collapse;
          font-size: 0.95rem;
    }
    .coai-admin-table th, .coai-admin-table td {
      border: 1px solid #ccc;
      padding: 0.5rem;
      text-align: left;
    }
    .coai-admin-table th {
      background-color: #f5f5f5;
    }
    .coai-admin-table tr:nth-child(even) {
      background-color: #fafafa;
    }
    </style>
      <table id="finance-admin-table" class="coai-admin-table">
        <thead>
          <tr>
            <th>Full Name</th>
            <th>Email</th>
            <th>Phone</th>
            <th>Membership Expiration</th>
            <th>Registered Date</th>
            <th>Renewal Date</th>
            <th>Insurance Status</th>
            <th>Membership Level</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($members as $member) : ?>
            <tr>
              <td><?php $edit_url = add_query_arg(['mid' => (int)$member->member_id], home_url('/member-edit/'));?>
              <a class="coai-edit-link"
                 href="<?php echo esc_url($edit_url); ?>"
                 onclick="event.stopPropagation();">
                 <?php echo esc_html($member->full_name); ?>
              </a>
            </td>
              <td><?php echo esc_html($member->email); ?></td>
              <td><?php echo esc_html($member->phone); ?></td>
              <td><?php echo esc_html($member->membership_expiration); ?></td>
              <td><?php echo esc_html($member->registered_date); ?></td>
              <td><?php echo esc_html($member->renewal_date); ?></td>
              <td><?php echo esc_html($member->insurance_status); ?></td>
              <td><?php echo esc_html($member->membership_level); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <script>
      document.addEventListener('DOMContentLoaded', function () {
        const table = document.getElementById('finance-admin-table');
        const headers = table.querySelectorAll('th');

       headers.forEach((header, index) => {
         header.style.cursor = 'pointer';
         header.addEventListener('click', () => {
            const rows = Array.from(table.querySelectorAll('tbody tr'));
            const sorted = rows.sort((a, b) => {
            const A = a.children[index].textContent.trim().toLowerCase();
            const B = b.children[index].textContent.trim().toLowerCase();
            return A.localeCompare(B);
          });
          const tbody = table.querySelector('tbody');
          tbody.innerHTML = '';
          sorted.forEach(row => tbody.appendChild(row));
        });
      });

      document.getElementById('export-finance-csv').addEventListener('click', function () {
        const rows = Array.from(table.querySelectorAll('tr'));
        const csv = rows.map(row => {
          return Array.from(row.querySelectorAll('th, td'))
            .map(cell => `"${cell.textContent.trim().replace(/"/g, '""')}"`)
            .join(',');
        }).join('\n');

        const blob = new Blob([csv], { type: 'text/csv' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'finance-members.csv';
        link.click();
      });
    });
</script>
    </div>
    <?php
    return ob_get_clean();
}
