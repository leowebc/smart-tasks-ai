import { Component, DestroyRef, inject } from '@angular/core';
import { HttpErrorResponse } from '@angular/common/http';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { MatButtonModule } from '@angular/material/button';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { AuthService } from '../../services/auth.service';

@Component({
  selector: 'app-login',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    RouterLink,
    MatFormFieldModule,
    MatInputModule,
    MatButtonModule,
  ],
  templateUrl: './login.component.html',
  styleUrl: './login.component.scss',
})
export class LoginComponent {
  private readonly authService = inject(AuthService);
  private readonly router = inject(Router);
  private readonly destroyRef = inject(DestroyRef);
  private readonly formBuilder = inject(FormBuilder);

  readonly form = this.formBuilder.nonNullable.group({
    username: ['', Validators.required],
    password: ['', Validators.required],
  });

  hidePassword = true;
  submitting = false;
  errorMessage = '';

  constructor() {
    const notice = sessionStorage.getItem('authNotice');
    if (notice) {
      this.errorMessage = notice;
      sessionStorage.removeItem('authNotice');
    }
  }

  togglePassword(): void {
    this.hidePassword = !this.hidePassword;
  }

  login(): void {
    if (this.submitting) {
      return;
    }

    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.submitting = true;
    this.errorMessage = '';
    const { username, password } = this.form.getRawValue();

    this.authService.login(username, password)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.submitting = false;
          const token = response?.token;
          if (typeof token !== 'string' || token.length === 0) {
            this.errorMessage = 'A autenticação não retornou um token.';
            return;
          }
          localStorage.setItem('token', token);
          this.router.navigate(['/tasks']);
        },
        error: (error: HttpErrorResponse) => {
          this.submitting = false;
          this.errorMessage = this.resolveError(error);
        },
      });
  }

  private resolveError(error: HttpErrorResponse): string {
    if (error.status === 0) {
      return 'Não foi possível conectar ao servidor.';
    }
    if (error.status === 401) {
      return 'Usuário ou senha inválidos.';
    }
    return 'Não foi possível entrar. Tente novamente.';
  }
}
