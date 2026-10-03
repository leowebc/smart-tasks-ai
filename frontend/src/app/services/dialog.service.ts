import { Injectable } from '@angular/core';
import Swal from 'sweetalert2';

const popup = {
  heightAuto: false,
  background: '#1f2937',
  color: '#f9fafb',
  confirmButtonColor: '#7c3aed',
  cancelButtonColor: '#4b5563',
};

@Injectable({
  providedIn: 'root',
})
export class DialogService {
  confirm(text: string): Promise<boolean> {
    return Swal.fire({
      ...popup,
      title: 'Tem certeza?',
      text,
      icon: 'warning',
      showCancelButton: true,
      focusCancel: true,
      confirmButtonText: 'Sim, excluir',
      cancelButtonText: 'Cancelar',
      confirmButtonColor: '#dc2626',
    }).then((result) => result.isConfirmed);
  }

  error(text: string): Promise<void> {
    return Swal.fire({
      ...popup,
      title: 'Atenção',
      text,
      icon: 'error',
      confirmButtonText: 'Ok',
    }).then(() => undefined);
  }
}
