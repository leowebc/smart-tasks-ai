export interface TaskItem {
  id: number;
  title: string;
  description: string | null;
  created_at: string | null;
  updated_at: string | null;
}

export interface TaskState {
  items: TaskItem[];
  loading: boolean;
  loaded: boolean;
  saving: boolean;
  error: string;
  formError: string;
}

export const initialTaskState: TaskState = {
  items: [],
  loading: false,
  loaded: false,
  saving: false,
  error: '',
  formError: '',
};
