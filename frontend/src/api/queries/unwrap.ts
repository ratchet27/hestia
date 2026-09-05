import { ApiError } from "../client";

type SuccessPayload<R> = R extends {
  status: 200 | 201;
  data: { data?: infer D };
}
  ? D
  : never;

// `apiFetch` (the Orval mutator) throws `ApiError` for every non-2xx status, so
// a resolved generated call is always its success branch. The generated return
// type is still a success|error union; this narrows it and strips the
// `{ data: { data } }` envelope in one place instead of a status check per hook.
export function unwrap<R extends { status: number; data: unknown }>(
  response: R,
): SuccessPayload<R> {
  const body = response.data as { data?: SuccessPayload<R> } | undefined;
  if (body?.data === undefined) {
    throw new ApiError(response.status, "Empty response body");
  }
  return body.data;
}
