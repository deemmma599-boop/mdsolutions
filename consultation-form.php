<script>
(function () {
  const params = new URLSearchParams(location.search);
  const status = document.getElementById('consultation-status');
  if (!status) return;
  if (params.has('sent')) {
    status.style.cssText = 'display:block; margin-top:16px; padding:14px 18px; border-radius:16px; font-size:0.92rem; background: rgba(52,211,153,0.12); border: 1px solid rgba(52,211,153,0.35); color: #6EE7B7;';
    status.textContent = 'Thank you! Your consultation request has been sent — we will be in touch shortly.';
  } else if (params.has('error')) {
    status.style.cssText = 'display:block; margin-top:16px; padding:14px 18px; border-radius:16px; font-size:0.92rem; background: rgba(248,113,113,0.10); border: 1px solid rgba(248,113,113,0.35); color: #FCA5A5;';
    status.textContent = 'Sorry, your message could not be sent. Please email info@mdsolutions.co.za directly.';
  }
})();
</script>