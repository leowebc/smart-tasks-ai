import { Component, inject } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Store } from '@ngrx/store';
import * as TaskActions from '../../state/tasks/task.actions';

@Component({
  selector: 'app-task',
  standalone: true,
  imports: [
    FormsModule
  ],
  templateUrl: './task.component.html',
  styleUrl: './task.component.scss'
})
export class TaskComponent {
  title: string = '';
  description: string = '';

  private readonly store = inject(Store);

  addTask() {
    const description = this.description.trim();
    this.store.dispatch(TaskActions.createTask({
      title: this.title.trim(),
      description: description === '' ? null : description,
    }));
    this.title = '';
    this.description = '';
  }
}
