/* ==========================================================================
   Mi usuario — editar perfil (nombre/foto) y cambiar contraseña
   ========================================================================== */

document.addEventListener('DOMContentLoaded', () => {

  /* ---- Previsualización de la foto al elegir un archivo ---- */
  const inputFoto = document.getElementById('p_foto');
  const previewWrap = document.getElementById('avatar-preview-wrap');

  inputFoto.addEventListener('change', () => {
    const file = inputFoto.files[0];
    if (!file) return;
    if (file.size > 2 * 1024 * 1024) {
      showToast('bad', 'Foto demasiado pesada', 'El máximo es 2 MB.');
      inputFoto.value = '';
      return;
    }
    const url = URL.createObjectURL(file);
    previewWrap.innerHTML = `<img src="${url}" alt="" class="avatar-perfil" id="avatar-preview">`;
  });

  /* ---- Guardar perfil (nombre + foto opcional) ---- */
  const formPerfil = document.getElementById('form-perfil');
  const btnPerfil = document.getElementById('btn-perfil-submit');
  const btnPerfilText = btnPerfil.querySelector('.btn-text');
  const btnPerfilSpinner = btnPerfil.querySelector('.btn-spinner');

  formPerfil.addEventListener('submit', async (e) => {
    e.preventDefault();
    formPerfil.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
    formPerfil.querySelectorAll('.field-error').forEach(el => el.textContent = '');

    const fd = new FormData(formPerfil);
    fd.append('accion', 'actualizar_perfil');

    btnPerfil.disabled = true; btnPerfilSpinner.classList.remove('d-none'); btnPerfilText.classList.add('d-none');
    try {
      const res = await fetch('api/perfil.php', { method: 'POST', body: fd });
      const data = await res.json();
      if (data.success) {
        showToast('ok', 'Perfil actualizado', data.message);
        if (data.foto_path) {
          previewWrap.innerHTML = `<img src="${data.foto_path}?t=${Date.now()}" alt="" class="avatar-perfil" id="avatar-preview">`;
        }
        inputFoto.value = '';
      } else {
        showToast('bad', 'No se pudo actualizar el perfil', data.message);
        Object.entries(data.errors || {}).forEach(([campo, msg]) => {
          if (campo === 'foto') { document.getElementById('err-foto').textContent = msg; return; }
          const input = formPerfil.querySelector(`[name="${campo}"]`);
          if (input) {
            input.classList.add('is-invalid');
            const err = input.closest('.mb-3')?.querySelector('.field-error');
            if (err) err.textContent = msg;
          }
        });
      }
    } catch (err) {
      showToast('bad', 'Error de conexión', 'No se pudo actualizar el perfil.');
    } finally {
      btnPerfil.disabled = false; btnPerfilSpinner.classList.add('d-none'); btnPerfilText.classList.remove('d-none');
    }
  });

  /* ---- Cambiar contraseña ---- */
  const formPassword = document.getElementById('form-password');
  const btnPassword = document.getElementById('btn-password-submit');
  const btnPasswordText = btnPassword.querySelector('.btn-text');
  const btnPasswordSpinner = btnPassword.querySelector('.btn-spinner');

  formPassword.addEventListener('submit', async (e) => {
    e.preventDefault();
    formPassword.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
    formPassword.querySelectorAll('.field-error').forEach(el => el.textContent = '');

    const payload = Object.fromEntries(new FormData(formPassword).entries());
    payload.accion = 'cambiar_password';

    btnPassword.disabled = true; btnPasswordSpinner.classList.remove('d-none'); btnPasswordText.classList.add('d-none');
    try {
      const res = await fetch('api/perfil.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      const data = await res.json();
      if (data.success) {
        showToast('ok', 'Contraseña actualizada', data.message);
        formPassword.reset();
      } else {
        showToast('bad', 'No se pudo actualizar la contraseña', data.message);
        Object.entries(data.errors || {}).forEach(([campo, msg]) => {
          const input = formPassword.querySelector(`[name="${campo}"]`);
          if (input) {
            input.classList.add('is-invalid');
            const err = input.closest('.mb-3')?.querySelector('.field-error');
            if (err) err.textContent = msg;
          }
        });
      }
    } catch (err) {
      showToast('bad', 'Error de conexión', 'No se pudo actualizar la contraseña.');
    } finally {
      btnPassword.disabled = false; btnPasswordSpinner.classList.add('d-none'); btnPasswordText.classList.remove('d-none');
    }
  });
});
