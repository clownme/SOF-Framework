jQuery(document).ready(function ($) {
  console.log('✅ admin-tools.js loaded');

  $(document).on('click', '#coai-export-button', function (e) {
    e.preventDefault();
    e.stopImmediatePropagation();
    console.log('✅ Export button clicked');

    $.post(coai_vars.ajax_url, {
      action: 'coai_export_members',
      nonce: coai_vars.nonce
    }, function (response) {
      console.log('✅ AJAX response:', response);
      if (response.success && response.data && response.data.url) {
        const link = document.createElement('a');
        link.href = response.data.url;
        link.download = 'members-export.csv';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
      } else {
        alert('Export failed.');
      }
    });
  });

  $(document).on('click', '#coai-manage-roles', function (e) {
    e.preventDefault();
    console.log('🛠 Manage roles clicked');
    $('#coai-role-manager').slideToggle();
  });

  // ✅ New: Click member row to open edit window
$(document).on('click', '.coai-member-row', function () {
  const memberId = $(this).data('id');
  console.log('🧠 Clicked member row with ID:', memberId);
  const editUrl = '/september/member-edit/' + memberId + '/';
  window.open(editUrl, '_blank');
});

  console.log('✅ End of admin-tools.js reached');
});