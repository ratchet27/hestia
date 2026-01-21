const BASE_URL = import.meta.env.VITE_API_BASE_URL || "https://localhost";

export interface ApiErrorResponse {
  status: number;
  message: string;
  violations?: Array<{ propertyPath: string; message: string }>;
}

export class ApiError extends Error {
  constructor(
    public status: number,
    message: string,
    public violations?: Array<{ propertyPath: string; message: string }>,
    public productName?: string,
  ) {
    super(message);
    this.name = "ApiError";
  }

  get isConflict(): boolean {
    return this.status === 409;
  }

  get isValidationError(): boolean {
    return this.status === 422;
  }

  get isNotFound(): boolean {
    return this.status === 404;
  }

  get isServerError(): boolean {
    return this.status >= 500;
  }
}

// CSRF token placeholder - will be implemented with session auth
function getCsrfToken(): string | null {
  // TODO: Implement when session auth is added
  // Options: read from cookie, meta tag, or dedicated endpoint
  return null;
}

export async function apiFetch<T>(
  url: string,
  options?: RequestInit,
): Promise<T> {
  const reqHeaders: HeadersInit = {
    "Content-Type": "application/json",
    Accept: "application/json",
    ...options?.headers,
  };

  // Add CSRF token if available
  const csrfToken = getCsrfToken();
  if (csrfToken) {
    (reqHeaders as Record<string, string>)["X-CSRF-Token"] = csrfToken;
  }

  const response = await fetch(`${BASE_URL}${url}`, {
    ...options,
    headers: reqHeaders,
    credentials: "include", // Include cookies for session auth
  });

  // Handle 204 No Content
  if (response.status === 204) {
    return {
      data: undefined,
      status: response.status,
      headers: response.headers,
    } as T;
  }

  // Parse response body
  let data: unknown;
  try {
    data = await response.json();
  } catch {
    // Response body is not JSON
    if (!response.ok) {
      throw new ApiError(response.status, "Request failed");
    }
    return {
      data: undefined,
      status: response.status,
      headers: response.headers,
    } as T;
  }

  // Handle errors - throw so they don't reach calling code
  if (!response.ok) {
    const errorData = data as {
      detail?: string;
      message?: string;
      title?: string;
      violations?: Array<{ propertyPath: string; message: string }>;
      productName?: string;
    };
    throw new ApiError(
      response.status,
      errorData.detail ||
        errorData.message ||
        errorData.title ||
        "Request failed",
      errorData.violations,
      errorData.productName,
    );
  }

  // Wrap successful response as Orval expects: { data, status, headers }
  return {
    data,
    status: response.status,
    headers: response.headers,
  } as T;
}
