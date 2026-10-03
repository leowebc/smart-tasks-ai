interface SmartTasksRuntimeConfig {
  apiUrl?: string;
}

type SmartTasksGlobal = typeof globalThis & {
  __SMART_TASKS_CONFIG__?: SmartTasksRuntimeConfig;
};

const runtimeConfig = (globalThis as SmartTasksGlobal).__SMART_TASKS_CONFIG__;

export const environment = {
  production: true,
  apiUrl: runtimeConfig?.apiUrl?.trim() || '/api',
} as const;
